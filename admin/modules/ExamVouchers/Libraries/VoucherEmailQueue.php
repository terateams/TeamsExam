<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Encryption\EncrypterInterface;
use CodeIgniter\Queue\Config\Queue as QueueConfig;
use CodeIgniter\Queue\Handlers\DatabaseHandler;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use InvalidArgumentException;
use Modules\ExamVouchers\Models\VoucherEmailDeliveryModel;
use Modules\ExamVouchers\Models\VoucherModel;
use RuntimeException;
use Throwable;

final class VoucherEmailQueue
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly EncrypterInterface $encrypter,
        private readonly QueueInterface $queue,
        private readonly QueueConfig $config,
        private readonly ?BaseConnection $queueDb,
        private readonly VoucherMailTemplate $templates,
        private readonly VoucherEmailDeliveryModel $deliveries,
        private readonly VoucherModel $vouchers,
    ) {
    }

    public function enqueue(array $voucherIds, array $confirmedSentIds, array $template, string $batchKey): array
    {
        if (! $this->queue instanceof DatabaseHandler || $this->config->defaultHandler !== 'database'
                                                      || ! $this->config->database['getShared'] || $this->queueDb !== $this->db
                                                      || ! isset($template['subject'], $template['body']) || ! is_string($template['subject']) || ! is_string($template['body'])
                                                      || ! $this->templates->valid($template['subject'], $template['body'])) {
            throw new RuntimeException('Voucher email configuration is invalid.');
        }
        if ($voucherIds === [] || count($voucherIds) !== count(array_unique($voucherIds))) {
            throw new InvalidArgumentException('Invalid voucher selection.');
        }

        foreach ($voucherIds as $voucherId) {
            if (! is_int($voucherId) || $voucherId < 1) {
                throw new InvalidArgumentException('Invalid voucher selection.');
            }
        }
        sort($voucherIds, SORT_NUMERIC);
        if (! $this->db->transBegin()) {
            throw new RuntimeException('Could not start email transaction.');
        }

        try {
            $errors  = [];
            $pending = [];

            foreach ($voucherIds as $voucherId) {
                $voucher = $this->vouchers->lockForEmail($voucherId);
                if ($voucher === null || $voucher['recipient_type'] === null) {
                    $errors[$voucherId] = 'unassigned';

                    continue;
                }
                $states = $this->deliveries->states($voucherId);
                if ($states['active']) {
                    $errors[$voucherId] = 'active';

                    continue;
                }
                if ($states['sent'] && ! in_array($voucherId, $confirmedSentIds, true)) {
                    $errors[$voucherId] = 'sent';

                    continue;
                }
                $recipient = $voucher['recipient_email'];
                $name      = $voucher['recipient_name'];
                if ($voucher['recipient_type'] === 'internal') {
                    $current   = $this->vouchers->internalEmailRecipient((int) $voucher['recipient_id']);
                    $recipient = $current['email'];
                    $name      = $current['name'];
                }
                if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL) || strlen($recipient) > 254) {
                    $errors[$voucherId] = 'recipient';

                    continue;
                }
                $ciphertext = base64_decode($voucher['code_ciphertext'], true);
                if ($ciphertext === false || $ciphertext === '') {
                    $errors[$voucherId] = 'code';

                    continue;
                }

                try {
                    $code = $this->encrypter->decrypt($ciphertext);
                    if ($code === '') {
                        throw new RuntimeException('Empty voucher code.');
                    }
                } catch (Throwable) {
                    $errors[$voucherId] = 'code';

                    continue;
                }
                $body = strtr($template['body'], [
                    '{name}'      => esc($name ?? ''), '{code}' => esc($code),
                    '{exam}'      => esc($voucher['exam'] ?? ''), '{region}' => esc($voucher['region'] ?? ''),
                    '{expiresOn}' => esc($voucher['expires_on']),
                ]);
                $snapshot  = json_encode(['body' => $body], JSON_THROW_ON_ERROR);
                $pending[] = [
                    'voucher_id'          => $voucherId, 'batch_key' => $batchKey, 'recipient' => $recipient, 'subject' => $template['subject'],
                    'snapshot_ciphertext' => base64_encode($this->encrypter->encrypt($snapshot)),
                    'status'              => VoucherEmailDeliveryModel::QUEUED, 'created_at' => gmdate('Y-m-d H:i:s'),
                ];
            }
            if ($errors !== []) {
                $this->db->transRollback();

                return $errors;
            }

            foreach ($pending as $delivery) {
                $deliveryId = $this->deliveries->createDelivery($delivery);
                $result     = $this->queue->push('exam-voucher-email', 'send-voucher-email', ['delivery_id' => $deliveryId]);
                if (! $result->getStatus() || $result->getJobId() === null) {
                    throw new RuntimeException('Could not enqueue email delivery.');
                }
                if (! $this->deliveries->assignJob($deliveryId, $result->getJobId())) {
                    throw new RuntimeException('Could not associate email job.');
                }
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Could not commit email transaction.');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();

            throw new RuntimeException('Could not enqueue voucher emails.', 0, $exception);
        }

        return [];
    }
}
