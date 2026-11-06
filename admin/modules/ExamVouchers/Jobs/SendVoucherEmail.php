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

        try {
            $ciphertext = base64_decode($delivery['snapshot_ciphertext'], true);
            if ($ciphertext === false || $ciphertext === '') {
                throw new RuntimeException('Invalid email snapshot.');
            }
            $snapshot = json_decode(service('encrypter')->decrypt($ciphertext), true, 512, JSON_THROW_ON_ERROR);
            $email    = service('email');
            $email->clear();
            $email->mailType = 'html';
            $email->setTo($delivery['recipient']);
            $email->setSubject($delivery['subject']);
            $email->setMessage($snapshot['body']);
            if (! $email->sendDirect()) {
                throw new RuntimeException('Email transport failed.');
            }
        } catch (Throwable) {
            $model->recordAttempt($id, (int) $delivery['attempts'] + 1, false);

            throw new RuntimeException('Voucher email delivery failed.');
        }

        if (! $model->recordAttempt($id, (int) $delivery['attempts'] + 1, true)) {
            throw new RuntimeException('Could not update voucher email delivery.');
        }
    }
}
