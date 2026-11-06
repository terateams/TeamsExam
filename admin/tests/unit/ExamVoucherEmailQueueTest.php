<?php

use CodeIgniter\Config\Services;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Encryption\EncrypterInterface;
use CodeIgniter\Queue\Commands\QueueWork;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Events\QueueEventManager;
use CodeIgniter\Queue\Handlers\DatabaseHandler;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\QueuePushResult;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Encryption;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\QueuedEmail;
use Geminus\Admin\Models\EmailDeliveryLogModel;
use Modules\ExamVouchers\Jobs\SendVoucherEmail;
use Modules\ExamVouchers\Libraries\VoucherEmailQueue;
use Modules\ExamVouchers\Libraries\VoucherMailTemplate;
use Modules\ExamVouchers\Models\BatchModel;
use Modules\ExamVouchers\Models\VoucherEmailDeliveryModel;
use Modules\ExamVouchers\Models\VoucherModel;

/**
 * @internal
 */
final class ExamVoucherEmailQueueTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        Services::resetSingle('email');
        Services::resetSingle('encrypter');
        Services::resetSingle('queue');
        parent::tearDown();
    }

    public function testQueueContainsOnlyIdentifierAndEncryptedSnapshot(): void
    {
        $voucherId = $this->voucher('voucher-secret', true);
        $service   = $this->emailQueue();
        $this->assertSame([], $service->enqueue([$voucherId], [], ['subject' => 'Your voucher', 'body' => 'Hello {name}: {code}'], str_repeat('a', 32)));

        $delivery = $this->db->table('exam_voucher_email_deliveries')->get()->getRowArray();
        $job      = $this->db->table('queue_jobs')->where('id', $delivery['job_id'])->get()->getRowArray();
        $this->assertSame('exam-voucher-email', $job['queue']);
        $this->assertSame('queued', $delivery['status']);
        $this->assertSame(str_repeat('a', 32), $delivery['batch_key']);
        $this->assertSame('person@example.com', $delivery['recipient']);
        $this->assertSame(['delivery_id' => (int) $delivery['id']], json_decode($job['payload'], true)['data']);
        $this->assertStringNotContainsString('voucher-secret', json_encode([$job, $delivery]));
        $snapshot = json_decode($this->encrypter()->decrypt(base64_decode($delivery['snapshot_ciphertext'], true)), true);
        $this->assertSame('Hello Student: voucher-secret', $snapshot['body']);
        $this->assertSame([$voucherId => 'active'], $service->enqueue([$voucherId], [], ['subject' => 'New', 'body' => '{code}'], str_repeat('b', 32)));
    }

    public function testBatchCreatesSeparateRecipientSnapshotsAndJobs(): void
    {
        $first  = $this->voucher('first-batch-secret', true);
        $second = $this->voucher('second-batch-secret', true);
        $this->db->table('exam_vouchers')->where('id', $second)->update(['recipient_email' => 'second@example.com', 'recipient_name' => 'Second']);
        $this->assertSame([], $this->emailQueue()->enqueue([$first, $second], [], ['subject' => 'Subject', 'body' => '{name}: {code}'], str_repeat('b', 32)));
        $rows = $this->db->table('exam_voucher_email_deliveries')->orderBy('voucher_id')->get()->getResultArray();
        $this->assertCount(2, $rows);
        $this->assertNotSame($rows[0]['job_id'], $rows[1]['job_id']);

        foreach ([$first => ['person@example.com', 'Student: first-batch-secret'], $second => ['second@example.com', 'Second: second-batch-secret']] as $id => [$recipient, $body]) {
            $delivery = array_values(array_filter($rows, static fn ($row) => (int) $row['voucher_id'] === $id))[0];
            $job      = $this->db->table('queue_jobs')->where('id', $delivery['job_id'])->get()->getRowArray();
            $snapshot = json_decode($this->encrypter()->decrypt(base64_decode($delivery['snapshot_ciphertext'], true)), true);
            $this->assertSame(str_repeat('b', 32), $delivery['batch_key']);
            $this->assertSame($recipient, $delivery['recipient']);
            $this->assertSame($body, $snapshot['body']);
            $this->assertSame(['delivery_id' => (int) $delivery['id']], json_decode($job['payload'], true)['data']);
        }
    }

    public function testBusinessValidationRejectsInvalidTemplateAndConnection(): void
    {
        $voucher = $this->voucher('validation-secret', true);

        foreach ([['subject' => 'Subject', 'body' => 'Missing code'], ['subject' => "Bad\nsubject", 'body' => '{code}']] as $template) {
            try {
                $this->emailQueue()->enqueue([$voucher], [], $template, str_repeat('a', 32));
                $this->fail('Invalid templates must be rejected before insertion.');
            } catch (RuntimeException) {
                $this->assertSame(0, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
            }
        }
        $config                        = clone config('Queue');
        $config->database['getShared'] = false;
        $service                       = new VoucherEmailQueue($this->db, $this->encrypter(), service('queue'), $config, $this->db, new VoucherMailTemplate(service('settings')), new VoucherEmailDeliveryModel($this->db), new VoucherModel($this->db));

        try {
            $service->enqueue([$voucher], [], ['subject' => 'Subject', 'body' => '{code}'], str_repeat('a', 32));
            $this->fail('A non-shared queue connection must be rejected.');
        } catch (RuntimeException) {
            $this->assertSame(0, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
            $this->assertSame(0, $this->db->table('queue_jobs')->where('queue', 'exam-voucher-email')->countAllResults());
        }
    }

    public function testInvalidSelectionLeavesEveryVoucherUnqueued(): void
    {
        $assigned   = $this->voucher('first-secret', true);
        $unassigned = $this->voucher('second-secret', false);
        $service    = $this->emailQueue();
        $this->assertSame([$unassigned => 'unassigned'], $service->enqueue([$assigned, $unassigned], [], ['subject' => 'Subject', 'body' => '{code}'], str_repeat('a', 32)));
        $this->assertSame(0, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
        $this->assertSame(0, $this->db->table('queue_jobs')->where('queue', 'exam-voucher-email')->countAllResults());
    }

    public function testInvalidRecipientOrCodeAbortsWholeBatch(): void
    {
        $valid   = $this->voucher('valid-secret', true);
        $invalid = $this->voucher('invalid-secret', true);
        $this->db->table('exam_vouchers')->where('id', $invalid)->update(['recipient_email' => 'invalid']);
        $service  = $this->emailQueue();
        $template = ['subject' => 'Subject', 'body' => '{code}'];
        $this->assertSame([$invalid => 'recipient'], $service->enqueue([$valid, $invalid], [], $template, str_repeat('a', 32)));
        $this->db->table('exam_vouchers')->where('id', $invalid)->update(['recipient_email' => 'valid@example.com', 'code_ciphertext' => base64_encode('not encrypted')]);
        $this->assertSame([$invalid => 'code'], $service->enqueue([$valid, $invalid], [], $template, str_repeat('a', 32)));
        $this->assertSame(0, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
        $this->assertSame(0, $this->db->table('queue_jobs')->where('queue', 'exam-voucher-email')->countAllResults());
    }

    public function testInternalRecipientUsesCurrentIdentityEmail(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $voucher = $this->voucher('internal-secret', true);
        $this->db->table('exam_vouchers')->where('id', $voucher)->update(['recipient_type' => 'internal', 'recipient_id' => auth()->id(), 'recipient_email' => null, 'recipient_name' => null]);
        $this->assertSame([], $this->emailQueue()->enqueue([$voucher], [], ['subject' => 'Subject', 'body' => '{name} {code}'], str_repeat('a', 32)));
        $delivery = $this->db->table('exam_voucher_email_deliveries')->get()->getRowArray();
        $this->assertSame('voucher-mail-manager@example.com', $delivery['recipient']);
        $snapshot = json_decode($this->encrypter()->decrypt(base64_decode($delivery['snapshot_ciphertext'], true)), true);
        $this->assertSame('voucher-mail-manager internal-secret', $snapshot['body']);
    }

    public function testPartiallyConfirmedSentBatchDoesNotEnqueue(): void
    {
        $first    = $this->voucher('sent-first', true);
        $second   = $this->voucher('sent-second', true);
        $service  = $this->emailQueue();
        $template = ['subject' => 'Subject', 'body' => '{code}'];
        $this->assertSame([], $service->enqueue([$first, $second], [], $template, str_repeat('a', 32)));
        $this->db->table('exam_voucher_email_deliveries')->update(['status' => 'sent']);
        $this->assertSame([$second => 'sent'], $service->enqueue([$first, $second], [$first], $template, str_repeat('b', 32)));
        $this->assertSame(2, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
        $this->assertSame(2, $this->db->table('queue_jobs')->where('queue', 'exam-voucher-email')->countAllResults());
    }

    public function testDatabaseRejectsSecondActiveDeliveryForVoucher(): void
    {
        $voucher = $this->voucher('unique-secret', true);
        $this->emailQueue()->enqueue([$voucher], [], ['subject' => 'Subject', 'body' => '{code}'], str_repeat('a', 32));
        $delivery = $this->db->table('exam_voucher_email_deliveries')->get()->getRowArray();
        unset($delivery['id']);

        try {
            $inserted = $this->db->table('exam_voucher_email_deliveries')->insert($delivery);
            $this->assertFalse($inserted);
        } catch (DatabaseException) {
            $this->assertSame(1, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
        }
        $this->assertSame(1, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
    }

    public function testQueueFailureRollsBackEarlierDelivery(): void
    {
        $first  = $this->voucher('first-secret', true);
        $second = $this->voucher('second-secret', true);
        $queue  = $this->createStub(DatabaseHandler::class);
        $queue->method('push')->willReturnOnConsecutiveCalls(QueuePushResult::success(123), QueuePushResult::failure('failed'));
        $service = $this->emailQueue($queue);

        try {
            $service->enqueue([$first, $second], [], ['subject' => 'Subject', 'body' => '{code}'], str_repeat('a', 32));
            $this->fail('Queue failure must abort the batch.');
        } catch (RuntimeException) {
            $this->assertSame(0, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
        }
    }

    public function testRealQueueInsertionIsRolledBackWhenLaterDeliveryFails(): void
    {
        $first  = $this->voucher('first-real', true);
        $second = $this->voucher('second-real', true);
        $this->db->table('exam_vouchers')->where('id', $second)->update(['recipient_email' => 'reject@example.com']);
        $this->db->query("ALTER TABLE exam_voucher_email_deliveries ADD CONSTRAINT test_voucher_email_reject CHECK (recipient <> 'reject@example.com')");

        try {
            try {
                $this->emailQueue()->enqueue([$first, $second], [], ['subject' => 'Subject', 'body' => '{code}'], str_repeat('a', 32));
                $this->fail('A later failure must roll back queue jobs and deliveries.');
            } catch (RuntimeException) {
                $this->assertSame(0, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
                $this->assertSame(0, $this->db->table('queue_jobs')->where('queue', 'exam-voucher-email')->countAllResults());
            }
        } finally {
            $this->db->query('ALTER TABLE exam_voucher_email_deliveries DROP CONSTRAINT test_voucher_email_reject');
        }
    }

    public function testConsumerUsesSnapshotAndRecordsSuccess(): void
    {
        $voucherId = $this->voucher('snapshot-secret', true);
        $encrypter = $this->encrypter();
        Services::injectMock('encrypter', $encrypter);
        $this->emailQueue()->enqueue([$voucherId], [], ['subject' => 'Original', 'body' => 'Hello {name}: {code}'], str_repeat('a', 32));
        $delivery = $this->db->table('exam_voucher_email_deliveries')->get()->getRowArray();
        $this->db->table('exam_vouchers')->where('id', $voucherId)->update(['recipient_email' => 'changed@example.com', 'recipient_name' => 'Changed']);
        $email = $this->getMockBuilder(QueuedEmail::class)->setConstructorArgs([new EmailDeliveryLogModel(), service('queue')])->onlyMethods(['setTo', 'setSubject', 'setMessage', 'sendDirect'])->getMock();
        $email->expects($this->once())->method('setTo')->with('person@example.com')->willReturnSelf();
        $email->expects($this->once())->method('setSubject')->with('Original')->willReturnSelf();
        $email->expects($this->once())->method('setMessage')->with('Hello Student: snapshot-secret')->willReturnSelf();
        $email->expects($this->once())->method('sendDirect')->willReturn(true);
        Services::injectMock('email', $email);
        (new SendVoucherEmail(['delivery_id' => $delivery['id']]))->process();
        $stored = $this->db->table('exam_voucher_email_deliveries')->where('id', $delivery['id'])->get()->getRowArray();
        $this->assertSame('sent', $stored['status']);
        $this->assertSame(1, (int) $stored['attempts']);
        $this->assertSame('', $stored['snapshot_ciphertext']);
    }

    public function testMailPermissionsAndCsrf(): void
    {
        $voucher = $this->voucher('private-code', true);
        $this->loginAs(['exam-vouchers.manage']);
        $templatePage = $this->get('/en/admin/exam-voucher-mail/template');
        $templatePage->assertStatus(200);
        $this->assertStringContainsString(esc((new VoucherMailTemplate(service('settings')))->get()['subject'], 'attr'), $templatePage->response()->getBody());
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $templatePage->response()->getBody());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//iframe[@id="template-preview" and @sandbox="" and @referrerpolicy="no-referrer"]')->length);
        $this->get('/en/admin/exam-voucher-mail/deliveries')->assertStatus(200);
        $this->get('/en/admin/mail/deliveries')->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $this->get('/en/admin/mail/voucher-deliveries')->assertRedirectTo(config('Auth')->permissionDeniedRedirect());

        try {
            $this->post('/en/admin/exam-voucher-mail/send', ['selection' => json_encode([$voucher])]);
            $this->fail('A request without CSRF must be rejected.');
        } catch (SecurityException) {
            $this->assertSame(0, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
        }
        auth()->user()->removePermission('exam-vouchers.manage');
        auth()->user()->addPermission('email-deliveries.view');
        $dashboard = $this->get('/en/admin/dashboard');
        $dashboard->assertStatus(200);
        $this->assertStringNotContainsString('href="/en/admin/exam-voucher-mail/template"', $dashboard->response()->getBody());
        $this->get('/en/admin/exam-voucher-mail/template')->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $this->get('/en/admin/exam-voucher-mail/deliveries')->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $this->emailQueue()->enqueue([$voucher], [], ['subject' => 'Visible subject', 'body' => 'Private {code}'], str_repeat('a', 32));
        $operations = $this->get('/en/admin/mail/voucher-deliveries');
        $operations->assertOK();
        $this->assertStringContainsString('person@example.com', $operations->response()->getBody());
        $this->assertStringContainsString('Visible subject', $operations->response()->getBody());
        $this->assertStringNotContainsString('private-code', $operations->response()->getBody());
        $this->assertStringNotContainsString('Private ', $operations->response()->getBody());
        $this->assertStringNotContainsString('/resend', $operations->response()->getBody());
        $delivery = $this->db->table('exam_voucher_email_deliveries')->get()->getRowArray();
        $this->assertStringNotContainsString($delivery['snapshot_ciphertext'], $operations->response()->getBody());
        $this->assertStringNotContainsString(base64_decode($delivery['snapshot_ciphertext'], true), $operations->response()->getBody());
    }

    public function testEveryMailPostRequiresCsrfAndManagePermission(): void
    {
        $voucher = $this->voucher('permission-secret', true);
        $this->loginAs(['exam-vouchers.manage']);
        $this->emailQueue()->enqueue([$voucher], [], ['subject' => 'Subject', 'body' => '{code}'], str_repeat('a', 32));
        $delivery = $this->db->table('exam_voucher_email_deliveries')->get()->getRowArray();
        $routes   = [
            '/en/admin/exam-voucher-mail/preview'                                   => ['selection' => json_encode([$voucher])],
            '/en/admin/exam-voucher-mail/send'                                      => ['selection' => json_encode([$voucher])],
            '/en/admin/exam-voucher-mail/deliveries/' . $delivery['id'] . '/resend' => [],
            '/en/admin/exam-voucher-mail/template'                                  => ['subject' => 'Unauthorized', 'body' => '{code}'],
        ];

        foreach ($routes as $route => $data) {
            try {
                $this->post($route, $data);
                $this->fail('A request without CSRF must be rejected: ' . $route);
            } catch (SecurityException) {
                $this->assertSame(1, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
            }
        }
        $original = (new VoucherMailTemplate(service('settings')))->get();
        auth()->user()->removePermission('exam-vouchers.manage');
        auth()->user()->addPermission('email-deliveries.view');

        foreach ($routes as $route => $data) {
            $this->post($route, [csrf_token() => csrf_hash()] + $data)->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        }
        $this->assertSame(1, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
        $this->assertSame($original, (new VoucherMailTemplate(service('settings')))->get());
    }

    public function testSendAndResendUseCurrentTemplateAndRecipient(): void
    {
        $voucher = $this->voucher('http-secret', true);
        Services::injectMock('encrypter', $this->encrypter());
        $this->loginAs(['exam-vouchers.manage']);
        $this->post('/en/admin/exam-voucher-mail/template', [csrf_token() => csrf_hash(), 'subject' => 'First subject', 'body' => 'First {code}'])->assertRedirect();
        $initialPreview = $this->post('/en/admin/exam-voucher-mail/preview', [csrf_token() => csrf_hash(), 'selection' => json_encode([$voucher])]);
        $initialPreview->assertStatus(200);
        $this->assertStringContainsString('person@example.com', $initialPreview->response()->getBody());
        $this->post('/en/admin/exam-voucher-mail/send', [csrf_token() => csrf_hash(), 'selection' => json_encode([$voucher])])->assertRedirect();
        $first = $this->db->table('exam_voucher_email_deliveries')->get()->getRowArray();
        $this->assertSame('First subject', $first['subject']);
        $this->assertSame('person@example.com', $first['recipient']);
        $activePreview = $this->post('/en/admin/exam-voucher-mail/send', [csrf_token() => csrf_hash(), 'selection' => json_encode([$voucher])]);
        $activePreview->assertStatus(200);
        $this->assertStringContainsString(lang('ExamVouchers.mailError_active'), $activePreview->response()->getBody());
        $this->assertSame(1, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
        $this->db->table('exam_voucher_email_deliveries')->where('id', $first['id'])->update(['status' => 'sent']);
        $this->db->table('exam_vouchers')->where('id', $voucher)->update(['recipient_email' => 'changed@example.com']);
        $this->post('/en/admin/exam-voucher-mail/template', [csrf_token() => csrf_hash(), 'subject' => 'Second subject', 'body' => 'Second {code}'])->assertRedirect();
        $storedFirst = $this->db->table('exam_voucher_email_deliveries')->where('id', $first['id'])->get()->getRowArray();
        $this->assertSame('First subject', $storedFirst['subject']);
        $this->assertSame('First http-secret', json_decode($this->encrypter()->decrypt(base64_decode($storedFirst['snapshot_ciphertext'], true)), true)['body']);
        $this->post('/en/admin/exam-voucher-mail/template', [csrf_token() => csrf_hash(), 'subject' => 'Invalid', 'body' => 'No code'])->assertRedirect();
        $invalidPage = $this->withSession()->get('/en/admin/exam-voucher-mail/template');
        $this->withSession([]);
        $invalidPage->assertStatus(200);
        $this->assertStringContainsString(lang('ExamVouchers.mailTemplateInvalid'), $invalidPage->response()->getBody());
        $this->assertSame(['subject' => 'Second subject', 'body' => 'Second {code}'], (new VoucherMailTemplate(service('settings')))->get());
        $preview = $this->post('/en/admin/exam-voucher-mail/deliveries/' . $first['id'] . '/resend', [csrf_token() => csrf_hash()]);
        $preview->assertStatus(200);
        $this->assertStringContainsString('Previously sent', $preview->response()->getBody());
        $this->assertStringContainsString((string) $voucher, $preview->response()->getBody());
        $unconfirmed = $this->post('/en/admin/exam-voucher-mail/send', [csrf_token() => csrf_hash(), 'selection' => json_encode([$voucher])]);
        $unconfirmed->assertStatus(200);
        $this->assertStringContainsString(lang('ExamVouchers.mailError_sent'), $unconfirmed->response()->getBody());
        $this->assertStringContainsString('name="confirm_sent"', $unconfirmed->response()->getBody());
        $this->assertSame(1, $this->db->table('exam_voucher_email_deliveries')->countAllResults());
        $this->post('/en/admin/exam-voucher-mail/send', [
            csrf_token() => csrf_hash(), 'selection' => json_encode([$voucher]), 'sent_ids' => json_encode([$voucher]), 'confirm_sent' => '1',
        ])->assertRedirect();
        $second = $this->db->table('exam_voucher_email_deliveries')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame('Second subject', $second['subject']);
        $this->assertSame('changed@example.com', $second['recipient']);
        $snapshot = json_decode($this->encrypter()->decrypt(base64_decode($second['snapshot_ciphertext'], true)), true);
        $this->assertSame('Second http-secret', $snapshot['body']);
        $page = $this->get('/en/admin/exam-voucher-mail/deliveries')->response()->getBody();
        $this->assertStringNotContainsString('http-secret', $page);
        $this->assertStringContainsString('First subject', $page);
        $this->assertStringContainsString('Second subject', $page);
        $firstBatchPage = $this->get('/en/admin/exam-voucher-mail/deliveries?batch=' . $first['batch_key'])->response()->getBody();
        $this->assertStringContainsString('First subject', $firstBatchPage);
        $this->assertStringNotContainsString('Second subject', $firstBatchPage);
        $this->assertStringNotContainsString($second['snapshot_ciphertext'], $page);
        $this->assertStringNotContainsString(base64_decode($second['snapshot_ciphertext'], true), $page);
        $this->post('/en/admin/exam-voucher-mail/deliveries/' . $second['id'] . '/resend', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->post('/en/admin/exam-voucher-mail/deliveries/999999999/resend', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->db->table('exam_voucher_email_deliveries')->where('id', $second['id'])->update(['status' => 'failed']);
        $this->post('/en/admin/exam-voucher-mail/deliveries/' . $second['id'] . '/resend', [csrf_token() => csrf_hash()])->assertStatus(200);
    }

    public function testFailedOnlyDeliveryCanBeResentWithoutSentConfirmation(): void
    {
        $voucher = $this->voucher('failed-only-secret', true);
        Services::injectMock('encrypter', $this->encrypter());
        $this->loginAs(['exam-vouchers.manage']);
        $this->emailQueue()->enqueue([$voucher], [], ['subject' => 'Subject', 'body' => '{code}'], str_repeat('a', 32));
        $first = $this->db->table('exam_voucher_email_deliveries')->get()->getRowArray();
        $this->db->table('exam_voucher_email_deliveries')->where('id', $first['id'])->update(['status' => 'failed']);
        $preview = $this->post('/en/admin/exam-voucher-mail/deliveries/' . $first['id'] . '/resend', [csrf_token() => csrf_hash()]);
        $preview->assertStatus(200);
        $this->assertStringNotContainsString('name="confirm_sent"', $preview->response()->getBody());
        $this->post('/en/admin/exam-voucher-mail/send', [csrf_token() => csrf_hash(), 'selection' => json_encode([$voucher])])->assertRedirect();
        $rows = $this->db->table('exam_voucher_email_deliveries')->orderBy('id')->get()->getResultArray();
        $this->assertCount(2, $rows);
        $this->assertSame('failed', $rows[0]['status']);
        $this->assertSame('queued', $rows[1]['status']);
    }

    public function testWorkerRetriesAndFailedRecordDoesNotExposeVoucherCode(): void
    {
        $voucher = $this->voucher('worker-private-code', true);
        Services::injectMock('encrypter', $this->encrypter());
        $this->emailQueue()->enqueue([$voucher], [], ['subject' => 'Subject', 'body' => '{code}'], str_repeat('a', 32));
        $email = $this->getMockBuilder(QueuedEmail::class)->setConstructorArgs([new EmailDeliveryLogModel(), service('queue')])->onlyMethods(['sendDirect'])->getMock();
        $email->expects($this->exactly(3))->method('sendDirect')->willThrowException(new RuntimeException('worker-private-code'));
        Services::injectMock('email', $email);
        $worker = new QueueWork(service('logger'), service('commands'));
        ob_start();

        try {
            $worker->run(['exam-voucher-email', 'stop-when-empty' => true, 'retry-after' => 0]);
        } finally {
            ob_end_clean();
        }
        $delivery = $this->db->table('exam_voucher_email_deliveries')->get()->getRowArray();
        $failed   = $this->db->table('queue_jobs_failed')->where('queue', 'exam-voucher-email')->get()->getRowArray();
        $this->assertSame('failed', $delivery['status']);
        $this->assertSame(3, (int) $delivery['attempts']);
        $this->assertSame('', $delivery['snapshot_ciphertext']);
        $this->assertNotNull($failed);
        $this->assertSame(['delivery_id' => (int) $delivery['id']], json_decode($failed['payload'], true)['data']);
        $this->assertStringNotContainsString('worker-private-code', json_encode($failed));
    }

    public function testFailureRemainsActiveUntilFinalFailureEvent(): void
    {
        $voucher = $this->voucher('failure-secret', true);
        Services::injectMock('encrypter', $this->encrypter());
        $this->emailQueue()->enqueue([$voucher], [], ['subject' => 'Subject', 'body' => '{code}'], str_repeat('a', 32));
        $delivery = $this->db->table('exam_voucher_email_deliveries')->get()->getRowArray();
        $email    = $this->getMockBuilder(QueuedEmail::class)->setConstructorArgs([new EmailDeliveryLogModel(), service('queue')])->onlyMethods(['sendDirect'])->getMock();
        $email->expects($this->exactly(3))->method('sendDirect')->willThrowException(new RuntimeException('failure-secret'));
        Services::injectMock('email', $email);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                (new SendVoucherEmail(['delivery_id' => $delivery['id']]))->process();
                $this->fail('A failed transport must be retried.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Voucher email delivery failed.', $exception->getMessage());
            }
            $stored = $this->db->table('exam_voucher_email_deliveries')->where('id', $delivery['id'])->get()->getRowArray();
            $this->assertSame('queued', $stored['status']);
            $this->assertSame($attempt, (int) $stored['attempts']);
            $this->assertStringNotContainsString('failure-secret', json_encode($stored));
        }
        $jobRow = $this->db->table('queue_jobs')->where('id', $delivery['job_id'])->get()->getRowArray();
        $job    = new QueueJob(['id' => (int) $jobRow['id'], 'payload' => json_decode($jobRow['payload'], true)]);
        QueueEventManager::jobFailed('database', 'another-queue', $job, new RuntimeException('Voucher email delivery failed.'));
        $this->assertSame('queued', $this->db->table('exam_voucher_email_deliveries')->where('id', $delivery['id'])->get()->getRowArray()['status']);
        QueueEventManager::jobFailed('database', 'exam-voucher-email', $job, new RuntimeException('Voucher email delivery failed.'));
        $stored = $this->db->table('exam_voucher_email_deliveries')->where('id', $delivery['id'])->get()->getRowArray();
        $this->assertSame('failed', $stored['status']);
        $this->assertSame('', $stored['snapshot_ciphertext']);
        $this->assertSame('Email delivery failed.', $stored['failure_reason']);
    }

    private function emailQueue(?QueueInterface $queue = null): VoucherEmailQueue
    {
        return new VoucherEmailQueue($this->db, $this->encrypter(), $queue ?? service('queue'), config('Queue'), $this->db, new VoucherMailTemplate(service('settings')), new VoucherEmailDeliveryModel($this->db), new VoucherModel($this->db));
    }

    private function loginAs(array $permissions): void
    {
        $user        = new AdminUser(['username' => 'voucher-mail-manager']);
        $user->email = 'voucher-mail-manager@example.com';
        $user->setPassword('A-local-password-123!');
        $provider = auth()->getProvider();
        $provider->save($user);
        $user = $provider->findById($provider->getInsertID());
        $user->addGroup('admin');
        $user->addPermission(...$permissions);
        auth()->login($user);
    }

    private function encrypter(): EncrypterInterface
    {
        $config      = new Encryption();
        $config->key = 'test-only-encryption-key-32-bytes-long';

        return Services::encrypter($config, false);
    }

    private function voucher(string $code, bool $assigned): int
    {
        $batch = (new BatchModel())->insert(['identifier' => $code, 'issuer' => 'Issuer']);
        $this->db->table('exam_vouchers')->insert([
            'batch_id'        => $batch, 'code_ciphertext' => base64_encode($this->encrypter()->encrypt($code)),
            'code_digest'     => hash('sha256', $code), 'expires_on' => '2030-01-01',
            'recipient_type'  => $assigned ? 'external' : null,
            'recipient_name'  => $assigned ? 'Student' : null,
            'recipient_email' => $assigned ? 'person@example.com' : null,
        ]);

        return (int) $this->db->insertID();
    }
}
