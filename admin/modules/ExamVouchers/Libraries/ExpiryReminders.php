<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Libraries;

use DateTimeImmutable;
use DateTimeZone;
use Geminus\Admin\Libraries\Notifications;
use Geminus\Admin\Models\UserModel;
use Modules\ExamVouchers\Models\VoucherModel;

class ExpiryReminders
{
    public function __construct(
        private readonly VoucherModel $vouchers,
        private readonly UserModel $users,
        private readonly Notifications $notifications,
    ) {
    }

    public function send(DateTimeImmutable $now): void
    {
        $today    = $now->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
        $vouchers = $this->vouchers->expiringBetween($today->format('Y-m-d'), $today->modify('+31 days')->format('Y-m-d'));
        if ($vouchers === []) {
            return;
        }

        $recipients = array_filter($this->users->findAll(), static fn ($user): bool => $user->can('exam-vouchers.manage'));

        foreach ($vouchers as $voucher) {
            $expiry        = new DateTimeImmutable($voucher['expires_on'], new DateTimeZone('UTC'));
            $previousMonth = $expiry->modify('first day of previous month');
            $day           = min((int) $expiry->format('j'), (int) $previousMonth->format('t'));
            $start         = $previousMonth->setDate((int) $previousMonth->format('Y'), (int) $previousMonth->format('n'), $day);
            if ($today < $start) {
                continue;
            }

            foreach ($recipients as $recipient) {
                $this->notifications->send(
                    (int) $recipient->id,
                    'exam-vouchers.expiry',
                    (int) $voucher['id'],
                    'Exam voucher #' . $voucher['id'] . ' expires on ' . $voucher['expires_on'],
                    'admin/exam-vouchers',
                    ['exam-vouchers.manage'],
                );
            }
        }
    }
}
