<?php

use CodeIgniter\Config\Services;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Encryption\EncrypterInterface;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Encryption;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Filters\OperationAudit;
use Modules\ExamVouchers\Controllers\VoucherImports;
use Modules\ExamVouchers\Libraries\VoucherCsvImport;
use Modules\ExamVouchers\Models\BatchModel;

/**
 * @internal
 */
final class ExamVoucherImportTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    private const KEY = 'test-only-encryption-key-32-bytes-long';

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testValidImportEncryptsAndRejectsDuplicateAcrossBatches(): void
    {
        $batch  = $this->batch('first');
        $other  = $this->batch('second');
        $import = $this->importer();
        $report = $this->runImport($import, $batch, "code,exam,region,expires_on\n secret-A ,Exam A,EU,2030-01-31\nsecret-B,,,2031-02-01\n");
        $this->assertSame(['created', 'created'], array_column($report, 'result'));
        $rows = db_connect()->table('exam_vouchers')->orderBy('id')->get()->getResultArray();
        $this->assertCount(2, $rows);
        $this->assertSame($batch, (int) $rows[0]['batch_id']);
        $this->assertSame('Exam A', $rows[0]['exam']);
        $this->assertSame('EU', $rows[0]['region']);
        $this->assertSame('2030-01-31', $rows[0]['expires_on']);
        $this->assertNull($rows[1]['exam']);
        $this->assertNull($rows[1]['region']);
        $this->assertStringNotContainsString('secret-A', json_encode($rows));
        $this->assertStringNotContainsString('secret-A', json_encode($report));
        $this->assertSame('secret-A', $this->encrypter()->decrypt(base64_decode($rows[0]['code_ciphertext'], true)));

        try {
            $duplicate = $rows[0];
            unset($duplicate['id']);
            $duplicate['batch_id'] = $other;
            db_connect()->table('exam_vouchers')->insert($duplicate);
            $this->fail('The database must reject a duplicate digest.');
        } catch (DatabaseException) {
            $this->assertSame(2, db_connect()->table('exam_vouchers')->countAllResults());
        }
        $rejected = $this->runImport($import, $other, "code,exam,region,expires_on\nnew-code,,,2030-01-01\nsecret-A,,,2030-01-01\n");
        $this->assertSame(['not_imported', 'duplicate'], array_column($rejected, 'reason'));
        $this->assertSame(2, db_connect()->table('exam_vouchers')->countAllResults());
        $caseVariant = $this->runImport($import, $other, "code,exam,region,expires_on\nSECRET-A,,,2030-01-01\n");
        $this->assertSame(['created'], array_column($caseVariant, 'result'));
        $this->assertSame(3, db_connect()->table('exam_vouchers')->countAllResults());
    }

    public function testInvalidRowsRejectWholeFileWithoutExposingFields(): void
    {
        $id     = $this->batch('invalid');
        $report = $this->runImport($this->importer(), $id, "code,exam,region,expires_on\nsensitive-one,private,secret,2030-01-01\nsensitive-two,,,2030-02-30\nsensitive-one,,,2030-01-01\n");
        $this->assertSame([2, 3, 4], array_column($report, 'row'));
        $this->assertSame(['not_imported', 'invalid_date', 'duplicate'], array_column($report, 'reason'));
        $this->assertStringNotContainsString('sensitive', json_encode($report));
        $this->assertStringNotContainsString('private', json_encode($report));
        $this->assertSame(0, db_connect()->table('exam_vouchers')->countAllResults());
    }

    public function testMalformedAndEmptyCsvAreRejected(): void
    {
        $id = $this->batch('malformed');

        foreach (["code,region,exam,expires_on\nx,,,2030-01-01\n", "code,exam,region,expires_on\n", "code,exam,region,expires_on\n" . str_repeat("x,,,2030-01-01\n", 501)] as $csv) {
            try {
                $this->runImport($this->importer(), $id, $csv);
                $this->fail('Malformed, empty, and oversized CSVs must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertSame(0, db_connect()->table('exam_vouchers')->countAllResults());
            }
        }
        $report = $this->runImport($this->importer(), $id, "code,exam,region,expires_on\nvalid,,,2030-01-01\nbad,extra,column,2030-01-01,extra\n");
        $this->assertSame(['not_imported', 'invalid'], array_column($report, 'reason'));
        $this->assertSame(0, db_connect()->table('exam_vouchers')->countAllResults());
    }

    public function testWriteFailureRollsBackEarlierRows(): void
    {
        $id = $this->batch('rollback');
        $db = db_connect();
        $db->query("ALTER TABLE exam_vouchers ADD CONSTRAINT test_exam_check CHECK (exam IS DISTINCT FROM 'reject')");

        try {
            try {
                $this->runImport($this->importer(), $id, "code,exam,region,expires_on\nfirst,ok,,2030-01-01\nsecond,reject,,2030-01-01\n");
                $this->fail('A failed insert must reject the entire file.');
            } catch (RuntimeException) {
                $this->assertSame(0, $db->table('exam_vouchers')->countAllResults());
            }
        } finally {
            $db->query('ALTER TABLE exam_vouchers DROP CONSTRAINT test_exam_check');
        }
    }

    public function testChangedEncryptionKeyCannotBypassUniqueness(): void
    {
        $id = $this->batch('key-guard');
        $this->runImport($this->importer(), $id, "code,exam,region,expires_on\nsame,,,2030-01-01\n");
        $config      = new Encryption();
        $config->key = 'another-test-only-key-with-32-bytes';
        $other       = new VoucherCsvImport(db_connect(), Services::encrypter($config), $config->key);

        try {
            $this->runImport($other, $id, "code,exam,region,expires_on\nsame,,,2030-01-01\n");
            $this->fail('A different key must not bypass duplicate protection.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Voucher encryption key changed.', $exception->getPrevious()->getMessage());
            $this->assertSame(1, db_connect()->table('exam_vouchers')->countAllResults());
        }
    }

    public function testUploadRejectsExtensionMimeAndSize(): void
    {
        $id   = $this->batch('file-validation');
        $path = WRITEPATH . 'voucher-test-' . bin2hex(random_bytes(8)) . '.csv';

        try {
            $validCsv = "code,exam,region,expires_on\nx,,,2030-01-01\n";

            foreach ([
                ['wrong.txt', $validCsv, 'text/csv'],
                ['binary.csv', $validCsv, 'application/octet-stream'],
                ['large.csv', $validCsv . str_repeat("\n", 1024 * 1024), 'text/csv'],
            ] as [$name, $content, $mime]) {
                file_put_contents($path, $content);
                clearstatcache(true, $path);
                $upload = $this->getMockBuilder(UploadedFile::class)
                    ->setConstructorArgs([$path, $name, 'text/csv', strlen($content), UPLOAD_ERR_OK])
                    ->onlyMethods(['isValid', 'getMimeType'])->getMock();
                $upload->expects($this->atLeastOnce())->method('isValid')->willReturn(true);
                $upload->method('getMimeType')->willReturn($mime);
                $request = $this->getMockBuilder(IncomingRequest::class)
                    ->setConstructorArgs([config('App'), service('uri'), null, new UserAgent()])
                    ->onlyMethods(['getFile'])->getMock();
                $request->expects($this->once())->method('getFile')->willReturn($upload);
                $controller = new VoucherImports();
                $controller->initController($request, Services::response(null, false), service('logger'));
                $controller->import($id);
                $this->assertSame('Choose a valid CSV with the exact header and 1–500 rows.', session('errors.file'));
                $this->assertSame(0, db_connect()->table('exam_vouchers')->countAllResults());
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testUploadAndAuditDoNotDiscloseCsvDataOrFilename(): void
    {
        $id = $this->batch('upload');
        $this->loginAs(['exam-vouchers.manage']);
        $path = WRITEPATH . 'voucher-test-' . bin2hex(random_bytes(8)) . '.csv';
        $csv  = "code,exam,region,expires_on\nsuper-secret-voucher,private-exam,private-region,2030-01-01\n";
        file_put_contents($path, $csv);

        try {
            $upload = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$path, 'secret-filename.csv', 'text/csv', strlen($csv), UPLOAD_ERR_OK])
                ->onlyMethods(['isValid'])->getMock();
            $upload->expects($this->atLeastOnce())->method('isValid')->willReturn(true);
            $route   = '/en/admin/exam-voucher-batches/' . $id . '/import';
            $base    = $this->setupRequest('POST', $route);
            $request = $this->getMockBuilder(IncomingRequest::class)
                ->setConstructorArgs([config('App'), $base->getUri(), null, new UserAgent()])
                ->onlyMethods(['getFile'])->getMock();
            $request->setMethod('POST');
            $request->setHeader('Content-Type', 'multipart/form-data; boundary=test');
            $request->expects($this->atLeastOnce())->method('getFile')->willReturn($upload);
            $routes = service('routes');
            $routes->loadRoutes();
            $router = Services::router($routes, $request, false);
            $router->handle(ltrim($route, '/'));
            Services::injectMock('router', $router);
            $audit = new OperationAudit();
            $audit->before($request);
            $controller = new VoucherImports();
            $controller->initController($request, Services::response(null, false), service('logger'));
            $response = $controller->import($id);
            $audit->after($request, $response);
            $this->assertSame('success', session('alert.type'));
            $this->assertSame(1, db_connect()->table('exam_vouchers')->countAllResults());
            $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame('exam-voucher.import', $log['operation']);
            $this->assertSame((string) $id, $log['target_id']);
            $this->assertSame('success', $log['result']);

            foreach (['super-secret-voucher', 'private-exam', 'private-region', 'secret-filename'] as $sensitive) {
                $this->assertStringNotContainsString($sensitive, json_encode($log));
                $this->assertStringNotContainsString($sensitive, json_encode(session('voucher_import_report')));
            }
            file_put_contents($path, "code,exam,region,expires_on\nother-secret,hidden-exam,hidden-region,2030-02-30\n");
            session()->remove(['alert', 'voucher_import_report']);
            $flashState = session()->get('__ci_vars') ?? [];
            unset($flashState['alert'], $flashState['voucher_import_report']);
            session()->set('__ci_vars', $flashState);
            $audit->before($request);
            $response = $controller->import($id);
            $audit->after($request, $response);
            $this->assertSame('danger', session('alert.type'));
            $this->assertSame(1, db_connect()->table('exam_vouchers')->countAllResults());
            $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame('failed', $log['result']);
            $page = $controller->form($id);

            foreach (['other-secret', 'hidden-exam', 'hidden-region', 'secret-filename'] as $sensitive) {
                $this->assertStringNotContainsString($sensitive, $page);
                $this->assertStringNotContainsString($sensitive, json_encode($log));
            }
            $this->assertStringContainsString('Invalid expiry date', $page);
        } finally {
            unlink($path);
            Services::resetSingle('router');
        }
    }

    public function testBatchWithVouchersCannotBeDeletedAndShowsReason(): void
    {
        $id = $this->batch('protected');
        $this->runImport($this->importer(), $id, "code,exam,region,expires_on\nsecret,,,2030-01-01\n");

        try {
            db_connect()->table('exam_voucher_batches')->where('id', $id)->delete();
            $this->fail('The database foreign key must prevent deletion.');
        } catch (DatabaseException) {
            $this->assertNotNull((new BatchModel())->find($id));
        }
        $this->loginAs(['exam-vouchers.manage']);
        $this->post('/en/admin/exam-voucher-batches/delete/' . $id, [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertNotNull((new BatchModel())->find($id));
        $this->assertSame('A batch containing vouchers cannot be deleted.', session('alert.message'));
        $this->assertSame(1, db_connect()->table('exam_vouchers')->countAllResults());
    }

    public function testRoutesRequirePermissionAndCsrf(): void
    {
        $id    = $this->batch('access');
        $route = '/en/admin/exam-voucher-batches/' . $id . '/import';
        $this->get($route)->assertRedirect();
        $this->loginAs(['announcements.manage']);
        $this->get($route)->assertRedirect();
        $this->post($route, [csrf_token() => csrf_hash()])->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
        $this->assertSame(0, db_connect()->table('exam_vouchers')->countAllResults());
        auth()->user()->addPermission('exam-vouchers.manage');
        $page = $this->get($route);
        $page->assertOK();
        $page->assertSee('code,exam,region,expires_on');

        try {
            $this->post($route, []);
            $this->fail('Missing CSRF token must be rejected.');
        } catch (SecurityException) {
            $this->assertSame(0, db_connect()->table('exam_vouchers')->countAllResults());
        }
        $this->post($route, [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame('Choose a valid CSV with the exact header and 1–500 rows.', session('errors.file'));
    }

    private function batch(string $identifier): int
    {
        return (int) (new BatchModel())->insert(['identifier' => $identifier, 'issuer' => 'Supplier']);
    }

    private function encrypter(): EncrypterInterface
    {
        $config      = new Encryption();
        $config->key = self::KEY;

        return Services::encrypter($config);
    }

    private function importer(): VoucherCsvImport
    {
        return new VoucherCsvImport(db_connect(), $this->encrypter(), self::KEY);
    }

    private function runImport(VoucherCsvImport $importer, int $id, string $csv): array
    {
        $stream = fopen('php://memory', 'w+b');
        fwrite($stream, $csv);
        rewind($stream);

        try {
            return $importer->import($stream, $id);
        } finally {
            fclose($stream);
        }
    }

    private function loginAs(array $permissions): void
    {
        $user        = new AdminUser(['username' => 'importmanager']);
        $user->email = 'importmanager@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('admin');
        $user->addPermission(...$permissions);
        auth()->login($user);
    }
}
