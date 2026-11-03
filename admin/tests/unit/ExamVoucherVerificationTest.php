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
final class ExamVoucherVerificationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testVerificationCorrectionRevocationAndAudit(): void
    {
        $id = $this->voucher();
        $this->login(['exam-vouchers.manage']);
        $path = "/en/admin/exam-vouchers/{$id}/verification";

        foreach (['en', 'zh-Hans', 'zh-Hant'] as $locale) {
            $page = $this->get("/{$locale}/admin/exam-vouchers/{$id}/verification");
            $page->assertOK();
            $this->assertStringNotContainsString('secret-code-123', $page->response()->getBody());
            $this->assertStringContainsString('name="' . csrf_token() . '"', $page->response()->getBody());
        }
        $this->assertStringContainsString('Not verified', $this->get('/en/admin/exam-vouchers')->response()->getBody());

        $this->submit($path, '1', '0', 'Official booking checked', ['code' => 'secret-code-123']);
        $this->assertSame('success', session('alert.type'));
        $this->assertRecord($id, 'Official booking checked', 1);
        $list = $this->get('/en/admin/exam-vouchers')->response()->getBody();
        $this->assertStringContainsString('Verified used', $list);
        $this->assertStringNotContainsString('secret-code-123', $list);
        $this->assertStringNotContainsString('Official booking checked', $list);
        $this->assertStringContainsString('Official&#x20;booking&#x20;checked', $this->get($path)->response()->getBody());

        $this->submit($path, '1', '1', 'Corrected basis');
        $this->assertSame('success', session('alert.type'));
        $this->assertRecord($id, 'Corrected basis', 2);
        $this->submit($path, '0', '2', 'ignored when revoked');
        $this->assertSame('success', session('alert.type'));
        $this->assertRecord($id, null, 3);
        $this->assertStringContainsString('Not verified', $this->get('/en/admin/exam-vouchers')->response()->getBody());

        $logs = db_connect()->table('operation_audit_logs')->orderBy('id')->get()->getResultArray();
        $this->assertCount(3, $logs);

        foreach ($logs as $log) {
            $this->assertSame('POST', $log['action']);
            $this->assertSame('success', $log['result']);
            $this->assertSame((string) $id, $log['target_id']);
            $this->assertSame(ltrim($path, '/'), $log['path']);
        }
        $encoded = json_encode($logs);

        foreach (['secret-code-123', 'Official booking checked', 'Corrected basis', 'ignored when revoked'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $encoded);
        }
    }

    public function testInvalidPermissionCsrfNotFoundAndFailedSave(): void
    {
        $id   = $this->voucher();
        $path = "/en/admin/exam-vouchers/{$id}/verification";
        $this->get($path)->assertRedirect();
        $this->login(['announcements.manage']);
        $this->get($path)->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $this->post($path, [csrf_token() => csrf_hash(), 'verified' => '1', 'version' => '0', 'note' => 'basis'])
            ->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $this->assertRecord($id, null, 0);
        auth()->user()->addPermission('exam-vouchers.manage');

        try {
            $this->post($path, ['verified' => '1', 'version' => '0', 'note' => 'basis']);
            $this->fail('CSRF token is required.');
        } catch (SecurityException) {
            $this->assertRecord($id, null, 0);
        }

        $invalid = [
            ['1', '0', ''], ['1', '0', '   '], ['1', '0', str_repeat('x', 256)],
            ['1', '0', "secret-code-123\n"], ['bogus', '0', 'secret-code-123'],
            ['', '0', 'secret-code-123'], ['1', '-1', 'secret-code-123'], ['1', 'bogus', 'secret-code-123'],
            [['1'], '0', 'secret-code-123'], ['1', '0', ['secret-code-123']],
        ];

        foreach ($invalid as [$verified, $version, $note]) {
            $this->submit($path, $verified, $version, $note);
            $this->assertSame('danger', session('alert.type'));
            $this->assertRecord($id, null, 0);
            $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame('failed', $log['result']);
        }
        $this->post($path, [csrf_token() => csrf_hash(), 'verified' => '1', 'version' => '0'])->assertRedirect();
        $this->assertSame('danger', session('alert.type'));
        $this->assertRecord($id, null, 0);
        $this->post($path, [csrf_token() => csrf_hash(), 'note' => 'secret-code-123'])->assertRedirect();
        $this->assertSame('danger', session('alert.type'));
        $this->assertRecord($id, null, 0);
        $this->get('/en/admin/exam-vouchers/999999/verification')->assertStatus(404);
        $this->post('/en/admin/exam-vouchers/999999/verification', [csrf_token() => csrf_hash(), 'verified' => '1', 'version' => '0', 'note' => 'secret-code-123'])->assertStatus(404);
        $this->assertStringNotContainsString('secret-code-123', json_encode(db_connect()->table('operation_audit_logs')->get()->getResultArray()));

        $db = db_connect();

        try {
            $db->table('exam_vouchers')->where('id', $id)->update(['verification_note' => '   ']);
            $this->fail('A blank verification basis must be rejected by the database.');
        } catch (DatabaseException) {
            $this->assertRecord($id, null, 0);
        }
        $db->query("ALTER TABLE exam_vouchers ADD CONSTRAINT test_reject_note CHECK (verification_note IS NULL OR verification_note <> 'rejected basis')");

        try {
            $this->submit($path, '1', '0', 'rejected basis');
            $this->assertSame('danger', session('alert.type'));
            $this->assertRecord($id, null, 0);
            $log = $db->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame('failed', $log['result']);
            $this->assertStringNotContainsString('rejected basis', json_encode($log));
        } finally {
            $db->query('ALTER TABLE exam_vouchers DROP CONSTRAINT test_reject_note');
        }
    }

    public function testConcurrentUpdatesCannotOverwriteAndKeepBasisConsistent(): void
    {
        $id     = $this->voucher();
        $db     = db_connect();
        $config = config('Database')->tests;
        $other  = pg_connect(sprintf(
            'host=%s port=%s dbname=%s user=%s password=%s',
            $config['hostname'],
            $config['port'],
            $config['database'],
            $config['username'],
            $config['password'],
        ), PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($other);

        try {
            $db->transBegin();
            $this->assertTrue((new VoucherModel())->recordVerification($id, 0, 'First basis'));
            $this->assertTrue(pg_send_query_params($other, 'UPDATE exam_vouchers SET verification_note = $1, verification_version = verification_version + 1 WHERE id = $2 AND verification_version = $3', ['Second basis', $id, 0]));
            $pid     = pg_get_pid($other);
            $waiting = false;

            for ($i = 0; $i < 100; $i++) {
                if (($db->query('SELECT wait_event_type FROM pg_stat_activity WHERE pid = ?', [$pid])->getRowArray()['wait_event_type'] ?? null) === 'Lock') {
                    $waiting = true;
                    break;
                }
                usleep(10000);
            }
            $this->assertTrue($waiting);
            $db->transCommit();
            $result = pg_get_result($other);
            $this->assertNotFalse($result);
            $this->assertSame(0, pg_affected_rows($result));
            $this->assertRecord($id, 'First basis', 1);
            $this->login(['exam-vouchers.manage']);
            $this->submit("/en/admin/exam-vouchers/{$id}/verification", '0', '0', '');
            $this->assertSame('danger', session('alert.type'));
            $this->assertRecord($id, 'First basis', 1);
            $log = $db->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame('failed', $log['result']);
            $this->assertFalse((new VoucherModel())->recordVerification($id, 0, 'Stale basis'));
            $this->assertRecord($id, 'First basis', 1);
        } finally {
            $db->transRollback();
            pg_close($other);
        }
    }

    public function testVerificationPreservesRecipientAndEscapesBasis(): void
    {
        $id = $this->voucher();
        $this->login(['exam-vouchers.manage']);
        $recipient = (int) auth()->user()->id;
        $this->assertTrue((new VoucherModel())->assignRecipient($id, $recipient));
        $path = "/en/admin/exam-vouchers/{$id}/verification";
        $this->submit($path, '1', '0', '<script>alert(1)</script>');
        $this->assertSame('success', session('alert.type'));
        $row = db_connect()->table('exam_vouchers')->where('id', $id)->get()->getRowArray();
        $this->assertSame($recipient, (int) $row['recipient_id']);
        $this->assertSame('<script>alert(1)</script>', $row['verification_note']);
        $page = $this->get($path)->response()->getBody();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringContainsString('&lt;script&gt;', $page);
    }

    private function submit(string $path, mixed $verified, mixed $version, mixed $note, array $extra = []): void
    {
        $this->post($path, array_merge([csrf_token() => csrf_hash(), 'verified' => $verified, 'version' => $version, 'note' => $note], $extra))->assertRedirect();
    }

    private function voucher(): int
    {
        $batch = (new BatchModel())->insert(['identifier' => 'verification-batch', 'issuer' => 'Issuer']);
        $db    = db_connect();
        $db->table('exam_vouchers')->insert([
            'batch_id'    => $batch, 'code_ciphertext' => base64_encode(service('encrypter')->encrypt('secret-code-123')),
            'code_digest' => hash('sha256', 'secret-code-123'), 'expires_on' => '2020-01-01',
        ]);

        return (int) $db->insertID();
    }

    private function login(array $permissions): void
    {
        $user        = new AdminUser(['username' => 'verification-manager']);
        $user->email = 'verification-manager@example.com';
        $user->setPassword('A-local-password-123!');
        $provider = auth()->getProvider();
        $provider->save($user);
        $user = $provider->findById($provider->getInsertID());
        $user->addGroup('admin');
        $user->addPermission(...$permissions);
        auth()->login($user);
    }

    private function assertRecord(int $id, ?string $note, int $version): void
    {
        $row = db_connect()->table('exam_vouchers')->where('id', $id)->get()->getRowArray();
        $this->assertSame($note, $row['verification_note']);
        $this->assertSame($version, (int) $row['verification_version']);
        $this->assertNull($row['recipient_id']);
    }
}
