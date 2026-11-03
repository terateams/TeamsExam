<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\Notifications;
use Geminus\Admin\Models\NotificationModel;
use Geminus\Admin\Models\UserModel;
use Modules\ExamVouchers\Libraries\ExpiryReminders;
use Modules\ExamVouchers\Models\BatchModel;
use Modules\ExamVouchers\Models\VoucherModel;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @internal
 */
final class ExamVoucherExpiryRemindersTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testCalendarWindowsAndCatchupAreInclusiveAndIdempotent(): void
    {
        $user = $this->user('reminder-calendar', true);
        $ids  = [];

        foreach (['2024-02-29', '2024-03-30', '2024-03-31', '2024-04-01', '2025-03-31'] as $date) {
            $ids[$date] = $this->voucher($date);
        }
        $reminders = $this->reminders();
        $reminders->send(new DateTimeImmutable('2024-02-28 23:59:59 UTC'));
        $this->assertSame([$ids['2024-02-29']], $this->references($user));
        $reminders->send(new DateTimeImmutable('2024-02-29 07:59:59 +08:00'));
        $this->assertSame([$ids['2024-02-29']], $this->references($user));
        $reminders->send(new DateTimeImmutable('2024-02-29 12:00:00 UTC'));
        $this->assertSame([$ids['2024-02-29'], $ids['2024-03-30'], $ids['2024-03-31']], $this->references($user));
        $late = $this->voucher('2024-03-03');
        $reminders->send(new DateTimeImmutable('2024-03-03 12:00:00 UTC'));
        $this->assertSame([$ids['2024-02-29'], $ids['2024-03-30'], $ids['2024-03-31'], $ids['2024-04-01'], $late], $this->references($user));
        $reminders->send(new DateTimeImmutable('2024-03-04 UTC'));
        $this->assertSame([$ids['2024-02-29'], $ids['2024-03-30'], $ids['2024-03-31'], $ids['2024-04-01'], $late], $this->references($user));
        $reminders->send(new DateTimeImmutable('2025-02-27 UTC'));
        $this->assertCount(5, $this->references($user));
        $reminders->send(new DateTimeImmutable('2025-02-28 UTC'));
        $this->assertSame([$ids['2024-02-29'], $ids['2024-03-30'], $ids['2024-03-31'], $ids['2024-04-01'], $late, $ids['2025-03-31']], $this->references($user));
    }

    public function testPermissionsStatesOpenAndRevocation(): void
    {
        $direct = $this->user('reminder-direct', true);
        $group  = $this->user('reminder-group');
        $group->addGroup('superadmin');
        $inactive = $this->user('reminder-inactive', true, false);
        $outsider = $this->user('reminder-outsider');
        $deleted  = $this->user('reminder-deleted', true);
        (new UserModel())->delete($deleted->id);
        $id = $this->voucher('2026-03-31');
        db_connect()->table('exam_vouchers')->where('id', $id)->update([
            'recipient_id' => $direct->id, 'verification_note' => 'checked', 'note' => 'void',
        ]);
        $this->reminders()->send(new DateTimeImmutable('2026-03-15 UTC'));

        foreach ([$direct, $group, $inactive] as $user) {
            $this->assertSame([$id], $this->references($user));
        }
        $this->assertSame([], $this->references($outsider));
        $this->assertSame([], $this->references($deleted));
        $notification = (new NotificationModel())->where('user_id', $direct->id)->first();
        $this->assertSame(['exam-vouchers.manage'], json_decode($notification['permissions'], true));
        $this->assertSame('admin/exam-vouchers', $notification['target_route']);
        $this->assertStringNotContainsString('secret-code', json_encode($notification));
        auth()->login($direct);
        $this->post('/en/admin/notifications/' . $notification['id'] . '/open', [csrf_token() => csrf_hash()])
            ->assertRedirectTo('/en/admin/exam-vouchers');
        $this->get('/en/admin/exam-vouchers')->assertOK();
        $this->assertNotNull((new NotificationModel())->find($notification['id'])['read_at']);
        $direct->removePermission('exam-vouchers.manage');
        auth()->logout();
        $direct = (new UserModel())->findById($direct->id);
        auth()->login($direct);
        $this->post('/en/admin/notifications/' . $notification['id'] . '/open', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->get('/en/admin/exam-vouchers')->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $outsider->addPermission('exam-vouchers.manage');
        $this->reminders()->send(new DateTimeImmutable('2026-03-16 UTC'));
        $this->assertSame([$id], $this->references($outsider));
    }

    public function testExpiredAndNotYetDueVouchersAreNotReminded(): void
    {
        $user = $this->user('reminder-boundary', true);
        $this->voucher('2026-01-30');
        $due = $this->voucher('2026-02-28');
        $this->voucher('2026-03-01');
        $this->reminders()->send(new DateTimeImmutable('2026-01-31 UTC'));
        $this->assertSame([$due], $this->references($user));
    }

    public function testDeliveryFailureIsExposedAndNextRunRecovers(): void
    {
        $user = $this->user('reminder-failure', true);
        $id   = $this->voucher('2026-05-01');
        $db   = db_connect();
        $db->query("ALTER TABLE admin_notifications ADD CONSTRAINT reminder_test_failure CHECK (source <> 'exam-vouchers.expiry')");

        try {
            try {
                $this->reminders()->send(new DateTimeImmutable('2026-04-15 UTC'));
                $this->fail('Delivery failure must be exposed.');
            } catch (Throwable $exception) {
                $this->assertNotInstanceOf(AssertionFailedError::class, $exception);
                $this->assertSame([], $this->references($user));
            }
        } finally {
            $db->query('ALTER TABLE admin_notifications DROP CONSTRAINT reminder_test_failure');
        }
        $this->reminders()->send(new DateTimeImmutable('2026-04-16 UTC'));
        $this->assertSame([$id], $this->references($user));
    }

    private function reminders(): ExpiryReminders
    {
        return new ExpiryReminders(new VoucherModel(), new UserModel(), new Notifications(new NotificationModel()));
    }

    private function references(AdminUser $user): array
    {
        $rows = db_connect()->table('admin_notifications')->select('reference')->where('user_id', $user->id)->orderBy('id')->get()->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['reference'], $rows);
    }

    private function voucher(string $expiry): int
    {
        $db    = db_connect();
        $batch = (new BatchModel())->insert(['identifier' => uniqid('reminder-', true), 'issuer' => 'Issuer']);
        $db->table('exam_vouchers')->insert([
            'batch_id' => $batch, 'code_ciphertext' => 'secret-code', 'code_digest' => hash('sha256', uniqid('', true)), 'expires_on' => $expiry,
        ]);

        return (int) $db->insertID();
    }

    private function user(string $name, bool $permission = false, bool $active = true): AdminUser
    {
        $users = new UserModel();
        $user  = new AdminUser(['username' => $name, 'active' => (int) $active]);
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        if ($permission) {
            $user->addPermission('exam-vouchers.manage');
        }

        return $user;
    }
}
