<?php

use CodeIgniter\Encryption\EncrypterInterface;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestLogger;
use Config\Encryption;
use Config\Services;
use Geminus\Admin\Entities\AdminUser;
use Modules\ExamVouchers\Controllers\VoucherImports;
use Modules\ExamVouchers\Libraries\VoucherCsvImport;
use Modules\ExamVouchers\Models\BatchModel;

/**
 * @internal
 */
final class ExamVoucherQueryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testInventoryCountsAllVouchersAndIndependentUsage(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $empty = $this->get('/en/admin/exam-vouchers')->response()->getBody();
        $this->assertInventory($empty, 0, 0, 0);

        $unassigned = $this->voucher('STOCK-ONE', 'Issuer', 'stock-one', 'Exam', 'Region');
        $assigned   = $this->voucher('STOCK-TWO', 'Issuer', 'stock-two', 'Exam', 'Region');
        $both       = $this->voucher('STOCK-THREE', 'Issuer', 'stock-three', 'Exam', 'Region');
        $db         = db_connect();
        $db->table('exam_vouchers')->where('id', $unassigned)->update(['verification_note' => 'Manual check']);
        $db->table('exam_vouchers')->whereIn('id', [$assigned, $both])->update(['recipient_id' => auth()->id()]);
        $db->table('exam_vouchers')->where('id', $both)->update(['verification_note' => 'Manual check']);

        $this->assertInventory($this->get('/en/admin/exam-vouchers')->response()->getBody(), 1, 2, 2);
        $this->assertInventory($this->get('/en/admin/exam-vouchers?q=STOCK-ONE')->response()->getBody(), 1, 2, 2);
        $batch = $db->table('exam_vouchers')->where('id', $unassigned)->get()->getRowArray()['batch_id'];
        $this->assertInventory($this->get('/en/admin/exam-vouchers?batch=' . $batch)->response()->getBody(), 1, 2, 2);
    }

    private function assertInventory(string $html, int $unassigned, int $assigned, int $verified): void
    {
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        foreach (['unassigned' => $unassigned, 'assigned' => $assigned, 'verified' => $verified] as $key => $expected) {
            $nodes = $xpath->query('//*[@id="inventory-' . $key . '"]');
            $this->assertSame(1, $nodes->length);
            $this->assertSame((string) $expected, trim($nodes->item(0)->textContent));
        }
    }

    public function testAuthorizedListAndDetailKeepCodeOutOfListUrlsAndAudit(): void
    {
        $id    = $this->voucher('BATCH-ONE', 'Supplier One', 'secret-<one>', 'Example Exam', 'EU');
        $other = $this->voucher('BATCH-TWO', 'Supplier Two', 'secret-two', 'Other Exam', 'US');
        $row   = db_connect()->table('exam_vouchers')->where('id', $id)->get()->getRowArray();
        $this->assertStringNotContainsString('secret-<one>', json_encode($row));
        $this->loginAs(['exam-vouchers.manage']);

        $list = $this->get('/en/admin/exam-vouchers')->response()->getBody();

        foreach (['BATCH-ONE', 'Supplier One', 'Example Exam', 'EU', '2030-01-31', 'BATCH-TWO'] as $expected) {
            $this->assertStringContainsString($expected, $list);
        }

        foreach (['secret-<one>', 'secret-&lt;one&gt;', 'secret-two', $row['code_ciphertext'], $row['code_digest']] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $list);
        }
        $this->assertStringContainsString('/en/admin/exam-vouchers/' . $id, $list);
        $this->assertStringContainsString('/en/admin/exam-vouchers/' . $other, $list);
        $document = new DOMDocument();
        $document->loadHTML($list, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " card ")]/div[contains(concat(" ", normalize-space(@class), " "), " table-responsive ")]/table[contains(concat(" ", normalize-space(@class), " "), " card-table ")]')->length);
        $filtered = $this->get('/en/admin/exam-vouchers?q=Example')->response()->getBody();
        $this->assertStringContainsString('BATCH-ONE', $filtered);
        $this->assertStringNotContainsString('BATCH-TWO', $filtered);
        $batch    = $row['batch_id'];
        $filtered = $this->get('/en/admin/exam-vouchers?batch=' . $batch)->response()->getBody();
        $this->assertStringContainsString('BATCH-ONE', $filtered);
        $this->assertStringNotContainsString('BATCH-TWO', $filtered);
        $batchPage = $this->get('/en/admin/exam-voucher-batches/show/' . $batch)->response()->getBody();
        $this->assertStringContainsString('/en/admin/exam-vouchers?batch=' . $batch, $batchPage);

        $detail = $this->get('/en/admin/exam-vouchers/' . $id);
        $detail->assertOK();
        $response = $detail->response();
        $this->assertStringContainsString('secret-&lt;one&gt;', $response->getBody());
        $this->assertStringNotContainsString('secret-<one>', json_encode(service('renderer')->getData()));
        $this->assertStringNotContainsString('secret-two', $response->getBody());
        $this->assertStringNotContainsString($row['code_ciphertext'], $response->getBody());
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertStringNotContainsString('secret', $response->getHeaderLine('Location'));
        $this->assertStringNotContainsString('secret', json_encode(db_connect()->table('operation_audit_logs')->get()->getResultArray()));
        $this->get('/en/admin/exam-vouchers/999999999')->assertStatus(404);
    }

    public function testLocalizedListAndDetail(): void
    {
        $id = $this->voucher('LOCALIZED', 'Issuer', 'locale-secret', 'Exam', 'Region');
        $this->loginAs(['exam-vouchers.manage']);

        foreach (['en' => 'Voucher code', 'zh-Hans' => '券码', 'zh-Hant' => '券碼'] as $locale => $label) {
            $list = $this->get('/' . $locale . '/admin/exam-vouchers')->response()->getBody();
            $this->assertStringContainsString('/' . $locale . '/admin/exam-vouchers/' . $id, $list);
            $this->assertStringNotContainsString('locale-secret', $list);
            $detail = $this->get('/' . $locale . '/admin/exam-vouchers/' . $id);
            $detail->assertOK();
            $this->assertStringContainsString($label, $detail->response()->getBody());
            $this->assertStringContainsString('locale-secret', $detail->response()->getBody());
        }
    }

    public function testAssignmentStatusAndRecipientAreShownOnListAndDetail(): void
    {
        $assigned   = $this->voucher('ASSIGNED', 'Issuer', 'assigned-code', 'Exam', 'Region');
        $unassigned = $this->voucher('UNASSIGNED', 'Issuer', 'unassigned-code', 'Exam', 'Region');
        $this->loginAs(['exam-vouchers.manage']);

        $recipient        = new AdminUser(['username' => 'voucher-recipient']);
        $recipient->email = 'voucher-recipient@example.com';
        $recipient->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($recipient);
        db_connect()->table('exam_vouchers')->where('id', $assigned)->update(['recipient_id' => $users->getInsertID()]);

        foreach ([
            'en'      => ['Recipient', 'Assigned', 'Unassigned'],
            'zh-Hans' => ['接收人', '已分发', '未分发'],
            'zh-Hant' => ['接收人', '已分發', '未分發'],
        ] as $locale => [$recipientLabel, $assignedLabel, $unassignedLabel]) {
            $list = $this->get("/{$locale}/admin/exam-vouchers");
            $list->assertOK();
            $document = new DOMDocument();
            $document->loadHTML($list->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);

            foreach ([
                $assigned   => [$assignedLabel, 'bg-green-lt', true],
                $unassigned => [$unassignedLabel, 'bg-yellow-lt', false],
            ] as $id => [$label, $color, $hasRecipient]) {
                $rows = $xpath->query("//table/tbody/tr[td//a[@href='/{$locale}/admin/exam-vouchers/{$id}']]");
                $this->assertSame(1, $rows->length);
                $row   = $rows->item(0);
                $badge = $xpath->query("./td[7]/span[contains(concat(' ', normalize-space(@class), ' '), ' badge ') and contains(concat(' ', normalize-space(@class), ' '), ' {$color} ')]", $row);
                $this->assertSame(1, $badge->length);
                $this->assertSame($label, trim($badge->item(0)->textContent));
                $this->assertSame($hasRecipient ? 1 : 0, $xpath->query("./td[7]/div[text()='voucher-recipient']", $row)->length);
                $this->assertSame($hasRecipient ? 0 : 1, $xpath->query(".//a[@href='/{$locale}/admin/exam-vouchers/{$id}/assign']", $row)->length);

                $detail = $this->get("/{$locale}/admin/exam-vouchers/{$id}");
                $detail->assertOK();
                $detailDocument = new DOMDocument();
                $detailDocument->loadHTML($detail->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
                $detailXPath = new DOMXPath($detailDocument);
                $field       = $detailXPath->query("//dt[text()='{$recipientLabel}']/following-sibling::dd[1]");
                $this->assertSame($hasRecipient ? 1 : 0, $field->length);
                if ($hasRecipient) {
                    $this->assertSame('voucher-recipient', trim($field->item(0)->textContent));
                }
            }
        }
    }

    public function testVoucherNumbersIdentifyListRowsAndIndividualPages(): void
    {
        $first  = $this->voucher('NUMBER-ONE', 'Issuer', 'number-secret-one', 'Exam', 'Region');
        $second = $this->voucher('NUMBER-TWO', 'Issuer', 'number-secret-two', 'Exam', 'Region');
        $db     = db_connect();
        $batch  = $db->table('exam_vouchers')->where('id', $first)->get()->getRowArray()['batch_id'];
        $db->table('exam_vouchers')->where('id', $second)->update(['batch_id' => $batch]);
        $this->loginAs(['exam-vouchers.manage']);

        foreach (['en' => 'Voucher number', 'zh-Hans' => '考试券编号', 'zh-Hant' => '考試券編號'] as $locale => $label) {
            $list = $this->get("/{$locale}/admin/exam-vouchers?batch={$batch}");
            $list->assertOK();
            $html = $list->response()->getBody();
            $this->assertStringNotContainsString('number-secret-one', $html);
            $this->assertStringNotContainsString('number-secret-two', $html);
            $document = new DOMDocument();
            $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $this->assertSame($label, trim($xpath->query('//table/thead/tr/th[1]')->item(0)->textContent));

            foreach ([$first, $second] as $id) {
                $rows = $xpath->query("//table/tbody/tr[td//a[@href='/{$locale}/admin/exam-vouchers/{$id}']]");
                $this->assertSame(1, $rows->length);
                $this->assertSame("#{$id}", trim($xpath->query('./td[1]', $rows->item(0))->item(0)->textContent));
                $detail = $this->get("/{$locale}/admin/exam-vouchers/{$id}");
                $detail->assertOK();
                $detailDocument = new DOMDocument();
                $detailDocument->loadHTML($detail->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
                $detailXPath = new DOMXPath($detailDocument);
                $number      = $detailXPath->query("//dt[text()='{$label}']/following-sibling::dd[1]");
                $this->assertSame(1, $number->length);
                $this->assertSame("#{$id}", trim($number->item(0)->textContent));

                foreach (['assign', 'verification', 'note'] as $action) {
                    $page = $this->get("/{$locale}/admin/exam-vouchers/{$id}/{$action}");
                    $page->assertOK();
                    $this->assertStringContainsString("{$label}: #{$id}", $page->response()->getBody());
                    $this->assertStringNotContainsString('number-secret-one', $page->response()->getBody());
                    $this->assertStringNotContainsString('number-secret-two', $page->response()->getBody());
                }
            }
        }
    }

    public function testDirectUrlsRequirePermission(): void
    {
        $id = $this->voucher('ACCESS', 'Issuer', 'private-code', 'Exam', 'Region');

        foreach (['/en/admin/exam-vouchers', '/en/admin/exam-vouchers/' . $id] as $path) {
            $this->get($path)->assertRedirect();
        }
        $this->loginAs(['announcements.manage']);

        foreach (['/en/admin/exam-vouchers', '/en/admin/exam-vouchers/' . $id] as $path) {
            $result = $this->get($path);
            $result->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
            $this->assertStringNotContainsString('private-code', $result->response()->getBody() ?? '');
        }
    }

    public function testCorruptedCiphertextReturnsSafeErrorAndDiagnostic(): void
    {
        $id = $this->voucher('ERROR', 'Issuer', 'private-code', 'Exam', 'Region');
        db_connect()->table('exam_vouchers')->where('id', $id)->update(['code_ciphertext' => 'invalid-sensitive-ciphertext!']);
        $this->loginAs(['exam-vouchers.manage']);
        $result = $this->get('/en/admin/exam-vouchers/' . $id);
        $result->assertStatus(500);
        $response = $result->response();
        $this->assertSame('Could not decrypt the voucher. Contact an administrator.', $response->getBody());
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertTrue(TestLogger::didLog('error', 'Voucher decryption failed for ID ' . $id . ': RuntimeException'));
        $this->assertFalse(TestLogger::didLog('error', 'invalid-sensitive-ciphertext', false));
        $this->assertFalse(TestLogger::didLog('error', 'private-code', false));
    }

    public function testDecryptionFailureDoesNotExposeCiphertextOrKey(): void
    {
        $id = $this->voucher('CORRUPT', 'Issuer', 'private-code', 'Exam', 'Region');
        db_connect()->table('exam_vouchers')->where('id', $id)->update(['code_ciphertext' => base64_encode('not-valid-encrypted-data')]);
        $this->loginAs(['exam-vouchers.manage']);
        $result = $this->get('/en/admin/exam-vouchers/' . $id);
        $result->assertStatus(500);
        $this->assertSame('Could not decrypt the voucher. Contact an administrator.', $result->response()->getBody());
        $this->assertTrue(TestLogger::didLog('error', 'Voucher decryption failed for ID ' . $id . ':', false));
        $this->assertFalse(TestLogger::didLog('error', 'not-valid-encrypted-data', false));
        $this->assertFalse(TestLogger::didLog('error', 'test-only-encryption-key-32-bytes-long', false));
    }

    public function testWrongKeyReturnsSafeError(): void
    {
        $id          = $this->voucher('WRONG-KEY', 'Issuer', 'private-code', 'Exam', 'Region');
        $config      = new Encryption();
        $config->key = 'another-test-only-key-with-32-bytes';
        $ciphertext  = base64_encode(Services::encrypter($config)->encrypt('private-code'));
        db_connect()->table('exam_vouchers')->where('id', $id)->update(['code_ciphertext' => $ciphertext]);
        $this->loginAs(['exam-vouchers.manage']);
        $result = $this->get('/en/admin/exam-vouchers/' . $id);
        $result->assertStatus(500);
        $this->assertSame('Could not decrypt the voucher. Contact an administrator.', $result->response()->getBody());
        $this->assertFalse(TestLogger::didLog('error', $ciphertext, false));
        $this->assertFalse(TestLogger::didLog('error', $config->key, false));
    }

    public function testImportEncryptionFailureIsAtomicAndSafelyReported(): void
    {
        $batch = (new BatchModel())->insert(['identifier' => 'ENCRYPT-ERROR', 'issuer' => 'Issuer']);
        $this->loginAs(['exam-vouchers.manage']);
        $path = WRITEPATH . 'voucher-encryption-test-' . bin2hex(random_bytes(8)) . '.csv';
        $csv  = "code,exam,region,expires_on\nprivate-code,Exam,EU,2030-01-31\n";
        file_put_contents($path, $csv);

        try {
            $upload = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$path, 'vouchers.csv', 'text/csv', strlen($csv), UPLOAD_ERR_OK])
                ->onlyMethods(['isValid'])->getMock();
            $upload->expects($this->atLeastOnce())->method('isValid')->willReturn(true);
            $request = $this->getMockBuilder(IncomingRequest::class)
                ->setConstructorArgs([config('App'), service('uri'), null, new UserAgent()])
                ->onlyMethods(['getFile'])->getMock();
            $request->expects($this->once())->method('getFile')->willReturn($upload);
            $encrypter = $this->createMock(EncrypterInterface::class);
            $encrypter->expects($this->once())->method('encrypt')->willThrowException(new RuntimeException('private-code and test-only-encryption-key-32-bytes-long'));
            Services::injectMock('encrypter', $encrypter);
            $controller = new VoucherImports();
            $controller->initController($request, Services::response(null, false), service('logger'));
            $controller->import($batch);
            $this->assertSame('Import failed; no vouchers were saved.', session('alert.message'));
            $this->assertSame(0, db_connect()->table('exam_vouchers')->countAllResults());
            $this->assertTrue(TestLogger::didLog('error', 'Voucher import failed: RuntimeException; cause: RuntimeException'));
            $this->assertFalse(TestLogger::didLog('error', 'private-code', false));
            $this->assertFalse(TestLogger::didLog('error', 'test-only-encryption-key-32-bytes-long', false));
        } finally {
            Services::resetSingle('encrypter');
            unlink($path);
        }
    }

    private function voucher(string $batch, string $issuer, string $code, string $exam, string $region): int
    {
        $batchId = (new BatchModel())->insert(['identifier' => $batch, 'issuer' => $issuer]);
        $db      = db_connect();
        $stream  = fopen('php://memory', 'w+b');
        fputcsv($stream, ['code', 'exam', 'region', 'expires_on'], escape: '');
        fputcsv($stream, [$code, $exam, $region, '2030-01-31'], escape: '');
        rewind($stream);

        try {
            $import = new VoucherCsvImport($db, service('encrypter'), config('Encryption')->key);
            $this->assertSame('created', $import->import($stream, (int) $batchId)[0]['result']);
        } finally {
            fclose($stream);
        }

        return (int) $db->table('exam_vouchers')->where('batch_id', $batchId)->get()->getRowArray()['id'];
    }

    private function loginAs(array $permissions): void
    {
        $user        = new AdminUser(['username' => 'querymanager']);
        $user->email = 'querymanager@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('admin');
        $user->addPermission(...$permissions);
        auth()->login($user);
    }
}
