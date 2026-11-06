<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;
use CodeIgniter\Queue\Events\QueueEvent;
use CodeIgniter\Queue\Events\QueueEventManager;
use Modules\ExamVouchers\Models\VoucherEmailDeliveryModel;

Events::on(QueueEventManager::JOB_FAILED, static function (QueueEvent $event): void {
    if ($event->getQueue() !== 'exam-voucher-email') {
        return;
    }

    (new VoucherEmailDeliveryModel())->markFailed((int) $event->getJobId());
});
