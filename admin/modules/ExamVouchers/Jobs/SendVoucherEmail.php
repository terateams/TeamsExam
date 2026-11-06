<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Jobs;

use CodeIgniter\Queue\BaseJob;
use Modules\ExamVouchers\Models\VoucherEmailDeliveryModel;
use RuntimeException;
use Throwable;

final class SendVoucherEmail extends BaseJob
{
    protected int $tries = 3;

    public function process(): void
    {
        $model    = new VoucherEmailDeliveryModel();
        $id       = (int) $this->data['delivery_id'];
        $delivery = $model->queued($id);
        if ($delivery === null) {
            return;
        }

        $failureReason = null;

        try {
            $ciphertext = base64_decode($delivery['snapshot_ciphertext'], true);
            if ($ciphertext === false || $ciphertext === '') {
                throw new RuntimeException('Invalid email snapshot.');
            }
            $snapshot = json_decode(service('encrypter')->decrypt($ciphertext), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($snapshot) || ! isset($snapshot['body']) || ! is_string($snapshot['body'])) {
                throw new RuntimeException('Invalid email snapshot.');
            }
        } catch (Throwable) {
            $failureReason = 'Email snapshot could not be decoded.';
        }

        if ($failureReason === null) {
            try {
                $email = service('email');
                $email->clear();
                $email->mailType = 'html';
                $email->setTo($delivery['recipient']);
                $email->setSubject($delivery['subject']);
                $email->setMessage($snapshot['body']);
                if (! $email->sendDirect()) {
                    $failureReason = $email->auditFailureReason($email->printDebugger([]));
                }
            } catch (Throwable $exception) {
                $failureReason = isset($email) ? $email->auditFailureReason($exception->getMessage()) : 'Email service unavailable.';
            }
        }

        if (! $model->recordAttempt($id, (int) $delivery['attempts'] + 1, $failureReason)) {
            throw new RuntimeException('Could not update voucher email delivery.');
        }

        if ($failureReason !== null) {
            throw new RuntimeException('Voucher email delivery failed.');
        }
    }
}
