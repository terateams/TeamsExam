<?php

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Geminus\Admin\Entities\AdminUser;
use Modules\ExamVouchers\Models\BatchModel;
use Modules\ExamVouchers\Models\VoucherModel;

/**
 * @internal
 */
final class ExamVoucherAssignmentTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testSearchAssignmentAndAudit(): void
    {
        $voucher   = $this->voucher();
        $recipient = $this->user('student-one');
        $this->loginAs('manager-one', ['exam-vouchers.manage']);
        $path = '/en/admin/exam-vouchers/' . $voucher . '/assign';

        foreach (['en', 'zh-Hans', 'zh-Hant'] as $locale) {
            $page = $this->get('/' . $locale . '/admin/exam-vouchers/' . $voucher . '/assign?username=student-one');
            $page->assertOK();
            $this->assertStringContainsString('student-one', $page->response()->getBody());
            $this->assertStringNotContainsString('private-voucher-code', $page->response()->getBody());
        }
        $this->assertStringContainsString('No matching internal account', $this->get($path . '?username=missing')->response()->getBody());
        $result = $this->post($path, [csrf_token() => csrf_hash(), 'recipient_id' => (string) $recipient, 'code' => 'private-voucher-code']);
        $result->assertRedirect();
        $this->assertSame('success', session('alert.type'));
        $this->assertSame($recipient, (int) $this->recipient($voucher));
        $list = $this->get('/en/admin/exam-vouchers')->response()->getBody();
        $this->assertStringContainsString('student-one', $list);
        $this->assertStringNotContainsString('private-voucher-code', $list);
        $this->assertStringNotContainsString('private-voucher-code', $result->response()->getHeaderLine('Location'));
        $this->assertStringContainsString('cannot be reassigned', $this->get($path)->response()->getBody());
        $logs = db_connect()->table('operation_audit_logs')->orderBy('id')->get()->getResultArray();
        $this->assertNotEmpty($logs);
        $this->assertStringNotContainsString('private-voucher-code', json_encode($logs));
        $log = end($logs);
        $this->assertSame('POST', $log['action']);
        $this->assertSame(ltrim($path, '/'), $log['path']);
        $this->assertSame('success', $log['result']);
        $this->assertSame((string) $voucher, $log['target_id']);

        $other = $this->user('student-two');
        $this->post($path, [csrf_token() => csrf_hash(), 'recipient_id' => (string) $other])->assertRedirect();
        $this->assertSame('danger', session('alert.type'));
        $this->assertSame($recipient, (int) $this->recipient($voucher));
        $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertSame('POST', $log['action']);
        $this->assertSame(ltrim($path, '/'), $log['path']);
        $this->assertSame('failed', $log['result']);
        $this->post($path, [csrf_token() => csrf_hash(), 'recipient_id' => (string) $recipient])->assertRedirect();
        $this->assertSame('danger', session('alert.type'));
        $this->assertSame($recipient, (int) $this->recipient($voucher));
        $this->assertSame('failed', db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray()['result']);
    }

    public function testInvalidRecipientMissingVoucherPermissionAndCsrf(): void
    {
        $voucher = $this->voucher();
        $deleted = $this->user('deleted-user');
        auth()->getProvider()->delete($deleted);
        $path = '/en/admin/exam-vouchers/' . $voucher . '/assign';
        $this->get($path)->assertRedirect();
        $this->loginAs('manager-two', ['announcements.manage']);
        $denied = $this->get($path . '?username=deleted-user');
        $denied->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $this->assertStringNotContainsString('deleted-user', $denied->response()->getBody() ?? '');
        $this->post($path, [csrf_token() => csrf_hash(), 'recipient_id' => (string) $deleted])->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $this->assertNull($this->recipient($voucher));
        auth()->user()->addPermission('exam-vouchers.manage');
        $this->assertStringContainsString('No matching internal account', $this->get($path . '?username=deleted-user')->response()->getBody());

        try {
            $this->post($path, ['recipient_id' => (string) $deleted]);
            $this->fail('A request without CSRF must be rejected.');
        } catch (SecurityException) {
            $this->assertNull($this->recipient($voucher));
        }

        foreach (['0', '-1', '9999999999', '999999', (string) $deleted, 'private-voucher-code'] as $invalid) {
            $result = $this->post($path, [csrf_token() => csrf_hash(), 'recipient_id' => $invalid]);
            $result->assertRedirect();
            $this->assertSame('danger', session('alert.type'));
            $this->assertStringNotContainsString('private-voucher-code', session('alert.message'));
            $this->assertNull($this->recipient($voucher));
        }
        $this->post('/en/admin/exam-vouchers/999999/assign', [csrf_token() => csrf_hash(), 'recipient_id' => '1'])->assertStatus(404);
        $this->assertStringNotContainsString('private-voucher-code', json_encode(db_connect()->table('operation_audit_logs')->get()->getResultArray()));

        try {
            db_connect()->table('exam_vouchers')->where('id', $voucher)->update(['recipient_id' => 999999]);
            $this->fail('The database must reject a nonexistent recipient.');
        } catch (DatabaseException) {
            $this->assertNull($this->recipient($voucher));
        }
    }

    public function testConcurrentUpdatesAllowOnlyOneWinner(): void
    {
        $voucher    = $this->voucher();
        $recipient  = $this->user('race-student');
        $other      = $this->user('race-other');
        $db         = db_connect();
        $config     = config('Database')->tests;
        $connection = pg_connect(sprintf(
            'host=%s port=%s dbname=%s user=%s password=%s',
            $config['hostname'],
            $config['port'],
            $config['database'],
            $config['username'],
            $config['password'],
        ), PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($connection);

        try {
            $db->transBegin();
            $this->assertTrue((new VoucherModel())->assignRecipient($voucher, $recipient));
            $sql = $db->getLastQuery()->getQuery();
            $sql = str_replace('SET recipient_id = ' . $recipient, 'SET recipient_id = ' . $other, $sql, $setCount);
            $sql = str_replace('WHERE id = ' . $recipient . ' AND deleted_at IS NULL', 'WHERE id = ' . $other . ' AND deleted_at IS NULL', $sql, $whereCount);
            $this->assertSame(1, $setCount);
            $this->assertSame(1, $whereCount);
            $this->assertTrue(pg_send_query($connection, $sql));
            $pid     = pg_get_pid($connection);
            $waiting = false;

            for ($i = 0; $i < 100; $i++) {
                $row = $db->query('SELECT wait_event_type FROM pg_stat_activity WHERE pid = ?', [$pid])->getRowArray();
                if (($row['wait_event_type'] ?? null) === 'Lock') {
                    $waiting = true;
                    break;
                }
                usleep(10000);
            }
            $this->assertTrue($waiting, 'The competing update must wait on the first transaction.');
            $db->transCommit();
            $result = pg_get_result($connection);
            $this->assertNotFalse($result);
            $this->assertSame(0, pg_affected_rows($result));
            $this->assertSame($recipient, (int) $this->recipient($voucher));
        } finally {
            $db->transRollback();
            pg_close($connection);
        }
    }

    private function voucher(): int
    {
        $batch = (new BatchModel())->insert(['identifier' => 'assignment-batch', 'issuer' => 'Issuer']);
        $db    = db_connect();
        $db->table('exam_vouchers')->insert([
            'batch_id'   => $batch, 'code_ciphertext' => 'encrypted', 'code_digest' => hash('sha256', 'private-voucher-code'),
            'expires_on' => '2030-01-01',
        ]);

        return (int) $db->insertID();
    }

    private function user(string $username): int
    {
        $user        = new AdminUser(['username' => $username]);
        $user->email = $username . '@example.com';
        $user->setPassword('A-local-password-123!');
        $provider = auth()->getProvider();
        $provider->save($user);

        return (int) $provider->getInsertID();
    }

    private function loginAs(string $username, array $permissions): void
    {
        $id   = $this->user($username);
        $user = auth()->getProvider()->findById($id);
        $user->addGroup('admin');
        $user->addPermission(...$permissions);
        auth()->login($user);
    }

    private function recipient(int $voucher): mixed
    {
        return db_connect()->table('exam_vouchers')->where('id', $voucher)->get()->getRowArray()['recipient_id'];
    }
}
