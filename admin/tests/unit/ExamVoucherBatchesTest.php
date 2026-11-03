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
