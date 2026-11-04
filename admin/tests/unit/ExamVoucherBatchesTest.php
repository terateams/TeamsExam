<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Geminus\Admin\Entities\AdminUser;
use Modules\ExamVouchers\Models\BatchModel;

/**
 * @internal
 */
final class ExamVoucherBatchesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testVoucherMenuProvidesLocalizedLinksAndHighlightsCurrentSection(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $batch = (new BatchModel())->insert(['identifier' => 'B-MENU', 'issuer' => 'Supplier']);

        foreach ([
            'en'      => ['Exam voucher management', 'Purchase batch', 'Exam voucher list'],
            'zh-Hans' => ['考试券管理', '采购批次', '考试券列表'],
            'zh-Hant' => ['考試券管理', '採購批次', '考試券列表'],
        ] as $locale => [$menuLabel, $batchLabel, $voucherLabel]) {
            $batchUrl   = "/{$locale}/admin/exam-voucher-batches";
            $voucherUrl = "/{$locale}/admin/exam-vouchers";

            foreach ([
                "/{$locale}/admin/dashboard"  => null,
                $batchUrl                     => $batchUrl,
                "{$batchUrl}/show/{$batch}"   => $batchUrl,
                "{$batchUrl}/{$batch}/import" => $batchUrl,
                $voucherUrl                   => $voucherUrl,
            ] as $path => $activeUrl) {
                $response = $this->get($path);
                $response->assertOK();
                $document = new DOMDocument();
                @$document->loadHTML('<?xml encoding="UTF-8">' . $response->response()->getBody());
                $xpath = new DOMXPath($document);
                $group = $xpath->query("//div[@id='sidebar-menu']//li[div/a[@href='{$batchUrl}']]");
                $this->assertSame(1, $group->length, $path);
                $this->assertStringContainsString($menuLabel, $group->item(0)->textContent);
                $this->assertSame($batchLabel, trim($xpath->query("./div/a[@href='{$batchUrl}']", $group->item(0))->item(0)->textContent));
                $this->assertSame($voucherLabel, trim($xpath->query("./div/a[@href='{$voucherUrl}']", $group->item(0))->item(0)->textContent));
                $this->assertSame($activeUrl === null ? 0 : 1, $xpath->query("self::li[contains(concat(' ', normalize-space(@class), ' '), ' active ')]", $group->item(0))->length);
                $this->assertSame($activeUrl === null ? 'false' : 'true', $xpath->query('./a', $group->item(0))->item(0)->getAttribute('aria-expanded'));
                $this->assertSame($activeUrl === null ? 0 : 1, $xpath->query("./div[contains(concat(' ', normalize-space(@class), ' '), ' show ')]", $group->item(0))->length);
                $current = $xpath->query("./div/a[@aria-current='page']", $group->item(0));
                $this->assertSame($activeUrl === null ? 0 : 1, $current->length, $path);
                if ($activeUrl !== null) {
                    $this->assertSame($activeUrl, $current->item(0)->getAttribute('href'));
                    $this->assertStringContainsString(' active ', ' ' . $current->item(0)->getAttribute('class') . ' ');
                }
            }
        }
    }

    public function testVoucherMenuIsHiddenWithoutPermission(): void
    {
        $this->loginAs(['announcements.manage']);
        $response = $this->get('/en/admin/dashboard');
        $response->assertOK();
        $this->assertStringNotContainsString('Exam voucher management', $response->response()->getBody());
        $this->assertStringNotContainsString('href="/en/admin/exam-voucher-batches"', $response->response()->getBody());
        $this->assertStringNotContainsString('href="/en/admin/exam-vouchers"', $response->response()->getBody());
    }

    public function testManagerCreatesViewsUpdatesAndDeletesBatch(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $this->post('/en/admin/exam-voucher-batches/create', [
            csrf_token() => csrf_hash(), 'identifier' => ' B-001 ', 'issuer' => ' Supplier A ',
        ])->assertRedirect();
        $model = new BatchModel();
        $batch = $model->where('identifier', 'B-001')->first();
        $this->assertNotNull($batch);
        $this->assertSame('Supplier A', $batch['issuer']);
        $this->get('/en/admin/exam-voucher-batches')->assertSee('B-001');
        $this->get('/en/admin/exam-voucher-batches/show/' . $batch['id'])->assertSee('Supplier A');
        $this->get('/en/admin/exam-voucher-batches/edit/' . $batch['id'])->assertOK();
        $this->post('/en/admin/exam-voucher-batches/update/' . $batch['id'], [
            csrf_token() => csrf_hash(), 'identifier' => 'B-002', 'issuer' => 'Supplier B',
        ])->assertRedirect();
        $this->assertSame('Supplier B', $model->find($batch['id'])['issuer']);
        $this->assertSame('B-002', $model->find($batch['id'])['identifier']);
        $this->post('/en/admin/exam-voucher-batches/update/' . $batch['id'], [
            csrf_token() => csrf_hash(), 'identifier' => 'B-002', 'issuer' => 'Supplier C',
        ])->assertRedirect();
        $this->assertSame('Supplier C', $model->find($batch['id'])['issuer']);
        $this->assertSame('B-002', $model->find($batch['id'])['identifier']);
        $this->post('/en/admin/exam-voucher-batches/delete/' . $batch['id'], [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertNull($model->find($batch['id']));
    }

    public function testInvalidAndDuplicateIdentifiersDoNotChangeData(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $model = new BatchModel();
        $id    = $model->insert(['identifier' => 'B-001', 'issuer' => 'Supplier A']);
        $this->post('/en/admin/exam-voucher-batches/create', [csrf_token() => csrf_hash(), 'identifier' => 'B-001', 'issuer' => 'Supplier B'])->assertRedirect();
        $this->post('/en/admin/exam-voucher-batches/create', [csrf_token() => csrf_hash(), 'identifier' => ' ', 'issuer' => 'Supplier B'])->assertRedirect();
        $other = $model->insert(['identifier' => 'B-002', 'issuer' => 'Supplier B']);
        $this->post('/en/admin/exam-voucher-batches/update/' . $other, [csrf_token() => csrf_hash(), 'identifier' => 'B-001', 'issuer' => 'Changed'])->assertRedirect();
        $this->assertSame(2, $model->countAllResults());
        $this->assertSame('Supplier B', $model->find($other)['issuer']);
        $this->assertSame('Supplier A', $model->find($id)['issuer']);
    }

    public function testUnauthorizedRequestsAndGetWritesCannotChangeData(): void
    {
        $model = new BatchModel();
        $id    = $model->insert(['identifier' => 'B-001', 'issuer' => 'Supplier A']);
        $this->get('/en/admin/exam-voucher-batches')->assertRedirect();
        $this->loginAs(['announcements.manage']);
        $this->get('/en/admin/exam-voucher-batches')->assertRedirect();
        $this->post('/en/admin/exam-voucher-batches/create', [csrf_token() => csrf_hash(), 'identifier' => 'B-002', 'issuer' => 'Supplier B'])->assertRedirect();
        $this->post('/en/admin/exam-voucher-batches/update/' . $id, [csrf_token() => csrf_hash(), 'identifier' => 'Changed', 'issuer' => 'Changed'])->assertRedirect();
        $this->post('/en/admin/exam-voucher-batches/delete/' . $id, [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(1, $model->countAllResults());
        $this->assertSame('B-001', $model->find($id)['identifier']);
        $this->assertSame('Supplier A', $model->find($id)['issuer']);
        auth()->user()->addPermission('exam-vouchers.manage');
        $this->get('/en/admin/exam-voucher-batches/delete/' . $id)->assertStatus(404);
        $this->assertNotNull($model->find($id));
    }

    public function testCreateRequiresCsrf(): void
    {
        $this->loginAs(['exam-vouchers.manage']);

        try {
            $this->post('/en/admin/exam-voucher-batches/create', ['identifier' => 'B-001', 'issuer' => 'Supplier']);
            $this->fail('Missing CSRF token must be rejected.');
        } catch (SecurityException $exception) {
            $this->assertSame(0, (new BatchModel())->countAllResults());
        }
    }

    private function loginAs(array $permissions): void
    {
        $user        = new AdminUser(['username' => 'batchmanager']);
        $user->email = 'batchmanager@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('admin');
        $user->addPermission(...$permissions);
        auth()->login($user);
    }
}
