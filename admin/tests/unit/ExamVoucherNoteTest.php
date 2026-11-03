<?php

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
final class ExamVoucherNoteTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testEditClearAndExistingOperationsRemainIndependent(): void
    {
        $id    = $this->voucher();
        $other = $this->voucher('other-code');
        $this->login(['exam-vouchers.manage']);
        $path = "/en/admin/exam-vouchers/{$id}/note";

        foreach (['en' => 'Voucher note', 'zh-Hans' => '考试券备注', 'zh-Hant' => '考試券備註'] as $locale => $label) {
            $page = $this->get("/{$locale}/admin/exam-vouchers/{$id}/note");
            $page->assertOK();
            $this->assertStringContainsString($label, $page->response()->getBody());
            $this->assertStringContainsString('name="' . csrf_token() . '"', $page->response()->getBody());
            $this->assertStringContainsString('no-store', $page->response()->getHeaderLine('Cache-Control'));
            $this->assertStringContainsString("/{$locale}/admin/exam-vouchers/{$id}/note", $this->get("/{$locale}/admin/exam-vouchers")->response()->getBody());
        }
        $before = (new VoucherModel())->inventoryCounts();
        $this->submit($path, 'expired <script>secret-code</script>');
        $this->assertSame('success', session('alert.type'));
        $this->assertSame('expired <script>secret-code</script>', $this->row($id)['note']);
        $this->assertNull($this->row($other)['note']);
        $page = $this->get($path)->response()->getBody();
        $this->assertStringNotContainsString('<script>secret-code</script>', $page);
        $this->assertStringContainsString('&lt;script&gt;', $page);
        $this->assertStringNotContainsString('secret-code', json_encode(service('renderer')->getData()));
        $this->assertStringNotContainsString('secret-code', $this->get('/en/admin/exam-vouchers')->response()->getBody());
        $this->assertSame($before, (new VoucherModel())->inventoryCounts());

        $this->submit($path, 'void');
        $this->assertSame('void', $this->row($id)['note']);
        $this->assertTrue((new VoucherModel())->assignRecipient($id, (int) auth()->id()));
        $this->post("/en/admin/exam-vouchers/{$id}/verification", [
            csrf_token() => csrf_hash(), 'verified' => '1', 'version' => '0', 'note' => 'checked',
        ])->assertRedirect();
        $this->assertSame('success', session('alert.type'));
        $this->assertSame('void', $this->row($id)['note']);
        $this->submit($path, '');
        $row = $this->row($id);
        $this->assertNull($row['note']);
        $this->assertSame((int) auth()->id(), (int) $row['recipient_id']);
        $this->assertSame('checked', $row['verification_note']);
        $this->assertSame(1, (int) $row['verification_version']);

        $logs = db_connect()->table('operation_audit_logs')->where('path', ltrim($path, '/'))->orderBy('id')->get()->getResultArray();
        $this->assertCount(3, $logs);

        foreach ($logs as $log) {
            $this->assertSame('POST', $log['action']);
            $this->assertSame('success', $log['result']);
            $this->assertSame((string) $id, $log['target_id']);
        }

        foreach (['secret-code', 'expired', 'void'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, json_encode($logs));
        }
    }

    public function testValidationPermissionsCsrfAndMissingVoucher(): void
    {
        $id   = $this->voucher();
        $path = "/en/admin/exam-vouchers/{$id}/note";
        $this->get($path)->assertRedirect();
        $this->login(['announcements.manage']);
        $this->get($path)->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $this->post($path, [csrf_token() => csrf_hash(), 'note' => 'void'])->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $this->assertNull($this->row($id)['note']);
        auth()->user()->addPermission('exam-vouchers.manage');

        try {
            $this->post($path, ['note' => 'void']);
            $this->fail('CSRF token is required.');
        } catch (SecurityException) {
            $this->assertNull($this->row($id)['note']);
        }

        $this->submit($path, str_repeat('测', 255));
        $this->assertSame(str_repeat('测', 255), $this->row($id)['note']);
        $this->submit($path, str_repeat('A', 256));
        $this->assertSame('danger', session('alert.type'));
        $this->assertSame(str_repeat('A', 256), session('voucher_note_input'));
        $this->assertStringContainsString(str_repeat('A', 256), $this->withSession()->get($path)->response()->getBody());
        $this->withSession([]);

        foreach ([str_repeat('测', 256), ['secret-code'], "secret-code\n", "\xff"] as $invalid) {
            $this->submit($path, $invalid);
            $this->assertSame('danger', session('alert.type'));
            $this->assertSame(str_repeat('测', 255), $this->row($id)['note']);
            $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame('failed', $log['result']);
        }
        $this->post($path, [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame('danger', session('alert.type'));
        $this->assertSame(str_repeat('测', 255), $this->row($id)['note']);
        $this->get('/en/admin/exam-vouchers/999999/note')->assertStatus(404);
        $this->post('/en/admin/exam-vouchers/999999/note', [csrf_token() => csrf_hash(), 'note' => 'secret-code'])->assertStatus(404);
        $this->assertStringNotContainsString('secret-code', json_encode(db_connect()->table('operation_audit_logs')->get()->getResultArray()));
        $db = db_connect();
        $db->query("ALTER TABLE exam_vouchers ADD CONSTRAINT test_note_failure CHECK (note IS NULL OR note <> 'rejected-secret')");

        try {
            $this->submit($path, 'rejected-secret');
            $this->assertSame('danger', session('alert.type'));
            $this->assertSame(str_repeat('测', 255), $this->row($id)['note']);
            $log = $db->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame('failed', $log['result']);
            $this->assertStringNotContainsString('rejected-secret', json_encode($log));
        } finally {
            $db->query('ALTER TABLE exam_vouchers DROP CONSTRAINT test_note_failure');
        }
        $this->submit($path, '   ');
        $this->assertNull($this->row($id)['note']);
    }

    private function submit(string $path, mixed $note): void
    {
        $this->post($path, [csrf_token() => csrf_hash(), 'note' => $note])->assertRedirect();
    }

    private function row(int $id): array
    {
        return db_connect()->table('exam_vouchers')->where('id', $id)->get()->getRowArray();
    }

    private function voucher(string $code = 'secret-code'): int
    {
        $model = new BatchModel();
        $batch = $model->insert(['identifier' => 'note-batch-' . substr(hash('sha256', $code), 0, 12), 'issuer' => 'Issuer']);
        $db    = db_connect();
        $db->table('exam_vouchers')->insert([
            'batch_id'    => $batch, 'code_ciphertext' => base64_encode(service('encrypter')->encrypt($code)),
            'code_digest' => hash('sha256', $code), 'expires_on' => '2020-01-01',
        ]);

        return (int) $db->insertID();
    }

    private function login(array $permissions): void
    {
        $user        = new AdminUser(['username' => 'note-manager']);
        $user->email = 'note-manager@example.com';
        $user->setPassword('A-local-password-123!');
        $provider = auth()->getProvider();
        $provider->save($user);
        $user = $provider->findById($provider->getInsertID());
        $user->addGroup('admin');
        $user->addPermission(...$permissions);
        auth()->login($user);
    }
}
