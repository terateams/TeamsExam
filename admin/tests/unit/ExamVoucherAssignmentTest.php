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
        $result = $this->post($path, [
            csrf_token()      => csrf_hash(),
            'recipient_type'  => 'internal',
            'recipient_id'    => (string) $recipient,
            'recipient_name'  => 'Ignored external name',
            'recipient_email' => 'ignored@example.com',
            'code'            => 'private-voucher-code',
        ]);
        $result->assertRedirect();
        $this->assertSame('success', session('alert.type'));
        $this->assertSame($recipient, (int) $this->recipient($voucher));
        $stored = db_connect()->table('exam_vouchers')->where('id', $voucher)->get()->getRowArray();
        $this->assertNull($stored['recipient_name']);
        $this->assertNull($stored['recipient_email']);
        $list = $this->get('/en/admin/exam-vouchers')->response()->getBody();
        $this->assertStringContainsString('student-one', $list);
        $this->assertStringNotContainsString('private-voucher-code', $list);
        $this->assertStringNotContainsString('private-voucher-code', $result->response()->getHeaderLine('Location'));
        $this->assertStringContainsString('cannot be reassigned', $this->get($path)->response()->getBody());
        $logs = db_connect()->table('operation_audit_logs')->orderBy('id')->get()->getResultArray();
        $this->assertNotEmpty($logs);
        $this->assertStringNotContainsString('private-voucher-code', json_encode($logs));
        $this->assertStringNotContainsString('Ignored external name', json_encode($logs));
        $this->assertStringNotContainsString('ignored@example.com', json_encode($logs));
        $log = end($logs);
        $this->assertSame('POST', $log['action']);
        $this->assertSame(ltrim($path, '/'), $log['path']);
        $this->assertSame('success', $log['result']);
        $this->assertSame((string) $voucher, $log['target_id']);
        $this->assertSame('exam-voucher.assign', $log['operation']);
        $this->assertSame('exam-voucher', $log['target_type']);
        $this->assertSame(['recipient_id' => (string) $recipient, 'recipient_type' => 'internal'], json_decode($log['submission'], true)['fields']);

        auth()->user()->addPermission('operation-audit.view');
        $page = $this->get('/zh-Hans/admin/audit');
        $page->assertOK();
        $page->assertSee('exam-voucher.assign');
        $page->assertSee('提交内容');
        $page->assertSee('recipient_type');
        $this->assertStringNotContainsString('private-voucher-code', $page->response()->getBody());

        $other = $this->user('student-two');
        $this->post($path, [csrf_token() => csrf_hash(), 'recipient_type' => 'internal', 'recipient_id' => (string) $other])->assertRedirect();
        $this->assertSame('danger', session('alert.type'));
        $this->assertSame($recipient, (int) $this->recipient($voucher));
        $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertSame('POST', $log['action']);
        $this->assertSame(ltrim($path, '/'), $log['path']);
        $this->assertSame('failed', $log['result']);
        $this->assertSame('exam-voucher.assign', $log['operation']);
        $this->assertSame(['recipient_id' => (string) $other, 'recipient_type' => 'internal'], json_decode($log['submission'], true)['fields']);
        $this->post($path, [csrf_token() => csrf_hash(), 'recipient_type' => 'internal', 'recipient_id' => (string) $recipient])->assertRedirect();
        $this->assertSame('danger', session('alert.type'));
        $this->assertSame($recipient, (int) $this->recipient($voucher));
        $this->assertSame('failed', db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray()['result']);
        $this->post($path, [
            csrf_token()      => csrf_hash(),
            'recipient_type'  => 'external',
            'recipient_name'  => 'External Student',
            'recipient_email' => 'external@example.com',
        ])->assertRedirect();
        $this->assertSame('danger', session('alert.type'));
        $stored = db_connect()->table('exam_vouchers')->where('id', $voucher)->get()->getRowArray();
        $this->assertSame('internal', $stored['recipient_type']);
        $this->assertSame($recipient, (int) $stored['recipient_id']);
        $this->assertNull($stored['recipient_name']);
        $this->assertNull($stored['recipient_email']);
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
        $this->post($path, [csrf_token() => csrf_hash(), 'recipient_type' => 'internal', 'recipient_id' => (string) $deleted])->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
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
            $result = $this->post($path, [csrf_token() => csrf_hash(), 'recipient_type' => 'internal', 'recipient_id' => $invalid]);
            $result->assertRedirect();
            $this->assertSame('danger', session('alert.type'));
            $this->assertStringNotContainsString('private-voucher-code', session('alert.message'));
            $this->assertNull($this->recipient($voucher));
            $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame('failed', $log['result']);
            $this->assertSame('exam-voucher.assign', $log['operation']);
            $this->assertSame((string) $voucher, $log['target_id']);
            $this->assertSame(['recipient_id' => $invalid, 'recipient_type' => 'internal'], json_decode($log['submission'], true)['fields']);
        }
        $this->post('/en/admin/exam-vouchers/999999/assign', [csrf_token() => csrf_hash(), 'recipient_type' => 'internal', 'recipient_id' => '1'])->assertStatus(404);

        try {
            db_connect()->table('exam_vouchers')->where('id', $voucher)->update(['recipient_id' => 999999, 'recipient_type' => 'internal']);
            $this->fail('The database must reject a nonexistent recipient.');
        } catch (DatabaseException) {
            $this->assertNull($this->recipient($voucher));
        }
    }

    public function testPartialUsernameSearchListsMatchingActiveUsers(): void
    {
        $voucher   = $this->voucher();
        $first     = $this->user('search-student-one');
        $second    = $this->user('search-student-two');
        $unrelated = $this->user('another-user');
        $deleted   = $this->user('search-student-deleted');
        db_connect()->table('users')->where('id', $first)->update(['avatar' => 'student-avatar.png']);
        auth()->getProvider()->delete($deleted);
        $this->loginAs('search-manager', ['exam-vouchers.manage']);
        $path = '/en/admin/exam-vouchers/' . $voucher . '/assign';

        $page = $this->get($path . '?username=STUDENT')->response()->getBody();
        $this->assertStringContainsString('>search-student-one</option>', $page);
        $this->assertStringContainsString('>search-student-two</option>', $page);
        $this->assertStringContainsString('<option value="' . $first . '" data-custom-properties=', $page);
        $this->assertStringContainsString('<option value="' . $second . '" data-custom-properties=', $page);
        $this->assertStringNotContainsString('<option value="' . $unrelated . '"', $page);
        $this->assertStringNotContainsString('<option value="' . $deleted . '"', $page);
        $this->assertStringContainsString('name="recipient_id" required', $page);
        $this->assertStringContainsString('Confirm the selected account belongs to the student', $page);
        $document = new DOMDocument();
        $this->assertTrue($document->loadHTML($page, LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath         = new DOMXPath($document);
        $withAvatar    = $xpath->query('//select[@id="recipient"]/option[@value="' . $first . '"]')->item(0)->getAttribute('data-custom-properties');
        $withoutAvatar = $xpath->query('//select[@id="recipient"]/option[@value="' . $second . '"]')->item(0)->getAttribute('data-custom-properties');
        $this->assertStringContainsString('class="avatar"', $withAvatar);
        $this->assertStringContainsString('/admin/avatars/' . $first, html_entity_decode($withAvatar, ENT_QUOTES | ENT_HTML5));
        $this->assertStringContainsString('class="avatar"', $withoutAvatar);
        $this->assertStringContainsString('>se</span>', $withoutAvatar);

        $empty = $this->get($path)->response()->getBody();
        $this->assertStringNotContainsString('<option value="' . $first . '"', $empty);
        $literal = $this->get($path . '?username=%25')->response()->getBody();
        $this->assertStringNotContainsString('<option value="' . $first . '"', $literal);
        $this->assertStringContainsString('No matching internal account', $literal);

        $this->post($path, [csrf_token() => csrf_hash(), 'recipient_type' => 'internal', 'recipient_id' => (string) $second])->assertRedirect();
        $this->assertSame($second, (int) $this->recipient($voucher));
        $assigned = $this->get($path . '?username=student')->response()->getBody();
        $this->assertStringNotContainsString('name="recipient_id"', $assigned);
        $this->assertStringNotContainsString('tom-select.base.min.js', $assigned);
    }

    public function testExternalRecipientIsStoredDisplayedAndCannotBeReassigned(): void
    {
        $voucher = $this->voucher();
        db_connect()->table('exam_vouchers')->where('id', $voucher)->update([
            'code_ciphertext' => base64_encode(service('encrypter')->encrypt('display-code')),
        ]);
        $this->loginAs('external-recipient-manager', ['exam-vouchers.manage']);
        $internalRecipient = $this->user('external-mixed-internal');
        $path              = '/en/admin/exam-vouchers/' . $voucher . '/assign';
        $page              = $this->get($path)->response()->getBody();
        $this->assertStringContainsString('name="recipient_name"', $page);
        $this->assertStringContainsString('name="recipient_email"', $page);

        $result = $this->post($path, [
            csrf_token()      => csrf_hash(),
            'recipient_type'  => 'external',
            'recipient_name'  => '  <b>External Student</b>  ',
            'recipient_email' => '  external.student@example.com  ',
            'recipient_id'    => (string) $internalRecipient,
        ]);
        $result->assertRedirect();
        $this->assertSame('success', session('alert.type'));
        $stored = db_connect()->table('exam_vouchers')->where('id', $voucher)->get()->getRowArray();
        $this->assertSame('external', $stored['recipient_type']);
        $this->assertNull($stored['recipient_id']);
        $this->assertSame('<b>External Student</b>', $stored['recipient_name']);
        $this->assertSame('external.student@example.com', $stored['recipient_email']);
        $this->assertSame(['unassigned' => 0, 'assigned' => 1, 'verified' => 0], (new VoucherModel())->inventoryCounts());

        $list = $this->get('/en/admin/exam-vouchers')->response()->getBody();
        $this->assertStringContainsString('&lt;b&gt;External Student&lt;/b&gt;', $list);
        $this->assertStringNotContainsString('<b>External Student</b>', $list);
        $this->assertStringContainsString('external.student@example.com', $list);
        $detail = $this->get('/en/admin/exam-vouchers/' . $voucher)->response()->getBody();
        $this->assertStringContainsString('&lt;b&gt;External Student&lt;/b&gt;', $detail);
        $this->assertStringNotContainsString('<b>External Student</b>', $detail);
        $this->assertStringContainsString('external.student@example.com', $detail);

        $logs = db_connect()->table('operation_audit_logs')->orderBy('id')->get()->getResultArray();
        $log  = end($logs);
        $this->assertSame([
            'recipient_id'   => (string) $internalRecipient,
            'recipient_type' => 'external',
        ], json_decode($log['submission'], true)['fields']);
        $this->assertStringNotContainsString('External Student', json_encode($logs));
        $this->assertStringNotContainsString('external.student@example.com', json_encode($logs));

        $this->post($path, [
            csrf_token()      => csrf_hash(),
            'recipient_type'  => 'external',
            'recipient_name'  => 'Another Student',
            'recipient_email' => 'another@example.com',
        ])->assertRedirect();
        $this->assertSame('danger', session('alert.type'));
        $stored = db_connect()->table('exam_vouchers')->where('id', $voucher)->get()->getRowArray();
        $this->assertSame('<b>External Student</b>', $stored['recipient_name']);
        $this->assertSame('external.student@example.com', $stored['recipient_email']);

        $this->post($path, [
            csrf_token()     => csrf_hash(),
            'recipient_type' => 'internal',
            'recipient_id'   => (string) $internalRecipient,
        ])->assertRedirect();
        $this->assertSame('danger', session('alert.type'));
        $stored = db_connect()->table('exam_vouchers')->where('id', $voucher)->get()->getRowArray();
        $this->assertSame('external', $stored['recipient_type']);
        $this->assertNull($stored['recipient_id']);
        $this->assertSame('<b>External Student</b>', $stored['recipient_name']);
        $this->assertSame('external.student@example.com', $stored['recipient_email']);
    }

    public function testExternalRecipientRequiresNameAndValidEmail(): void
    {
        $voucher = $this->voucher();
        $this->loginAs('external-validation-manager', ['exam-vouchers.manage']);
        $path = '/en/admin/exam-vouchers/' . $voucher . '/assign';

        foreach ([
            ['recipient_type' => 'external', 'recipient_name' => '', 'recipient_email' => 'valid@example.com'],
            ['recipient_type' => 'external', 'recipient_name' => " \t ", 'recipient_email' => 'valid@example.com'],
            ['recipient_type' => 'external', 'recipient_name' => 'External Student', 'recipient_email' => 'invalid-email'],
            ['recipient_type' => 'external', 'recipient_name' => str_repeat('n', 256), 'recipient_email' => 'valid@example.com'],
            ['recipient_type' => 'external', 'recipient_name' => 'External Student', 'recipient_email' => str_repeat('a', 250) . '@example.com'],
            ['recipient_type' => 'external', 'recipient_name' => 'External Student'],
            ['recipient_type' => 'external', 'recipient_email' => 'valid@example.com'],
            ['recipient_type' => 'invalid', 'recipient_name' => 'External Student', 'recipient_email' => 'valid@example.com'],
            ['recipient_name' => 'External Student', 'recipient_email' => 'valid@example.com'],
        ] as $invalid) {
            $this->post($path, [csrf_token() => csrf_hash(), ...$invalid])->assertRedirect();
            $this->assertSame('danger', session('alert.type'));
            $stored = db_connect()->table('exam_vouchers')->where('id', $voucher)->get()->getRowArray();
            $this->assertNull($stored['recipient_type']);
            $this->assertNull($stored['recipient_name']);
            $this->assertNull($stored['recipient_email']);
            $this->assertNull($stored['recipient_id']);
            $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame('failed', $log['result']);
            $fields = json_decode($log['submission'], true)['fields'];
            $this->assertSame(array_intersect_key($invalid, ['recipient_type' => true]), $fields);
            $this->assertStringNotContainsString('External Student', json_encode($log));
            $this->assertStringNotContainsString('valid@example.com', json_encode($log));
        }
    }

    public function testSearchLimitsResultsAndPromptsToRefine(): void
    {
        $voucher = $this->voucher();

        for ($i = 0; $i < 21; $i++) {
            $this->user(sprintf('limit-student-%02d', $i));
        }
        $this->loginAs('limit-manager', ['exam-vouchers.manage']);
        $path = '/en/admin/exam-vouchers/' . $voucher . '/assign';

        $page = $this->get($path . '?username=limit-student')->response()->getBody();
        $this->assertSame(20, substr_count($page, 'data-custom-properties='));
        $this->assertStringContainsString('Only the first 20 matches', $page);
        $this->assertStringNotContainsString('>limit-student-20</option>', $page);

        $narrowed = $this->get($path . '?username=limit-student-20')->response()->getBody();
        $this->assertStringContainsString('>limit-student-20</option>', $narrowed);
        $this->assertStringNotContainsString('Only the first 20 matches', $narrowed);
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
