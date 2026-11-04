<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\ExamVouchers\Database\Migrations\CreateExamVouchers;

/**
 * @internal
 */
final class ExamVoucherMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace;

    public function testBatchMigrationCreatesNullablePurchaseDate(): void
    {
        $this->db->table('exam_voucher_batches')->insert(['identifier' => 'B-NULL', 'issuer' => 'Supplier']);
        $batch = $this->db->table('exam_voucher_batches')->where('identifier', 'B-NULL')->get()->getRowArray();
        $this->assertNull($batch['purchased_on']);
        $column = $this->db->query(
            'SELECT data_type, is_nullable FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
            [$this->db->prefixTable('exam_voucher_batches'), 'purchased_on'],
        )->getRowArray();
        $this->assertSame('date', $column['data_type']);
        $this->assertSame('YES', $column['is_nullable']);
    }

    public function testMigrationRecreatesColumnsAndConstraints(): void
    {
        $migration = new CreateExamVouchers();
        $migration->down();

        try {
            $this->assertFalse($this->db->tableExists('exam_vouchers'));
            $this->assertFalse($this->db->tableExists('exam_voucher_key_guard'));
        } finally {
            $migration->up();
        }
        $this->assertTrue($this->db->tableExists('exam_vouchers'));
        $this->assertTrue($this->db->tableExists('exam_voucher_key_guard'));

        $columns = $this->db->query(
            'SELECT column_name, is_nullable, column_default FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ?',
            [$this->db->prefixTable('exam_vouchers')],
        )->getResultArray();
        $byColumn = array_column($columns, null, 'column_name');

        foreach (['recipient_id', 'verification_note', 'note'] as $column) {
            $this->assertSame('YES', $byColumn[$column]['is_nullable']);
        }
        $this->assertSame('NO', $byColumn['verification_version']['is_nullable']);
        $this->assertSame('0', $byColumn['verification_version']['column_default']);

        $constraints = $this->db->query(
            "SELECT conname, confdeltype FROM pg_constraint WHERE conname IN ('exam_vouchers_recipient_fk', 'exam_vouchers_verification_note_check')",
        )->getResultArray();
        $byName = array_column($constraints, 'confdeltype', 'conname');
        $this->assertArrayHasKey('exam_vouchers_verification_note_check', $byName);
        $this->assertSame('r', $byName['exam_vouchers_recipient_fk']);
    }
}
