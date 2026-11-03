<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Commands;

use CodeIgniter\CLI\BaseCommand;
use DateTimeImmutable;
use DateTimeZone;
use Geminus\Admin\Models\UserModel;
use Modules\ExamVouchers\Libraries\ExpiryReminders;
use Modules\ExamVouchers\Models\VoucherModel;

class RemindExpiry extends BaseCommand
{
    protected $group       = 'Exam Vouchers';
    protected $name        = 'exam-vouchers:remind-expiry';
    protected $description = 'Send expiry reminders for exam vouchers.';

    public function run(array $params)
    {
        $reminders = new ExpiryReminders(new VoucherModel(), new UserModel(), service('notifications'));
        $reminders->send(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        return EXIT_SUCCESS;
    }
}
