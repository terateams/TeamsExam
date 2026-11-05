<?php

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Geminus\Admin\Entities\AdminUser;
use Modules\ExamVouchers\Database\Migrations\AddExternalExamVoucherRecipients;
use Modules\ExamVouchers\Database\Migrations\CreateExamVouchers;
use Modules\ExamVouchers\Models\BatchModel;

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
            (new AddExternalExamVoucherRecipients())->up();
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
            "SELECT conname, confdeltype FROM pg_constraint WHERE conname IN ('exam_vouchers_recipient_fk', 'exam_vouchers_verification_note_check', 'exam_vouchers_recipient_type_check')",
        )->getResultArray();
        $byName = array_column($constraints, 'confdeltype', 'conname');
        $this->assertArrayHasKey('exam_vouchers_verification_note_check', $byName);
        $this->assertArrayHasKey('exam_vouchers_recipient_type_check', $byName);
        $this->assertSame('r', $byName['exam_vouchers_recipient_fk']);
    }

    public function testExternalRecipientMigrationAddsNullableFieldsWithExpectedLengths(): void
    {
        $columns = $this->db->query(
            'SELECT column_name, character_maximum_length, is_nullable FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ?',
            [$this->db->prefixTable('exam_vouchers')],
        )->getResultArray();
        $byColumn = array_column($columns, null, 'column_name');

        foreach (['recipient_type', 'recipient_name', 'recipient_email'] as $column) {
            $this->assertSame('YES', $byColumn[$column]['is_nullable']);
        }
        $this->assertSame(16, (int) $byColumn['recipient_type']['character_maximum_length']);
        $this->assertSame(255, (int) $byColumn['recipient_name']['character_maximum_length']);
        $this->assertSame(254, (int) $byColumn['recipient_email']['character_maximum_length']);
    }

    public function testExternalRecipientMigrationBackfillsExistingAssignments(): void
    {
        $migration = new AddExternalExamVoucherRecipients();
        $migration->down();

        $user        = new AdminUser(['username' => 'migration-student']);
        $user->email = 'migration-student@example.com';
        $user->setPassword('A-local-password-123!');
        $provider = auth()->getProvider();
        $provider->save($user);
        $recipientId = (int) $provider->getInsertID();
        $batchId     = (new BatchModel())->insert(['identifier' => 'migration-batch', 'issuer' => 'Issuer']);

        $this->db->table('exam_vouchers')->insert([
            'batch_id'   => $batchId, 'code_ciphertext' => 'encrypted', 'code_digest' => hash('sha256', 'migration-code'),
            'expires_on' => '2030-01-01', 'recipient_id' => $recipientId,
        ]);
        $internalVoucherId = (int) $this->db->insertID();
        $this->db->table('exam_vouchers')->insert([
            'batch_id'   => $batchId, 'code_ciphertext' => 'encrypted', 'code_digest' => hash('sha256', 'migration-unassigned-code'),
            'expires_on' => '2030-01-01',
        ]);
        $unassignedVoucherId = (int) $this->db->insertID();

        $migration->up();

        $internal = $this->db->table('exam_vouchers')->where('id', $internalVoucherId)->get()->getRowArray();
        $this->assertSame('internal', $internal['recipient_type']);
        $unassigned = $this->db->table('exam_vouchers')->where('id', $unassignedVoucherId)->get()->getRowArray();
        $this->assertNull($unassigned['recipient_type']);
    }

    public function testRecipientConstraintRejectsExternalWithoutName(): void
    {
        $batchId = (new BatchModel())->insert(['identifier' => 'missing-name-batch', 'issuer' => 'Issuer']);

        $this->expectException(DatabaseException::class);
        $this->db->table('exam_vouchers')->insert([
            'batch_id'       => $batchId, 'code_ciphertext' => 'encrypted', 'code_digest' => hash('sha256', 'missing-name-code'),
            'expires_on'     => '2030-01-01', 'recipient_type' => 'external',
            'recipient_name' => " \t ", 'recipient_email' => 'external@example.com',
        ]);
    }

    public function testRecipientConstraintRejectsInternalAndExternalFieldsTogether(): void
    {
        $user        = new AdminUser(['username' => 'mixed-recipient-student']);
        $user->email = 'mixed-recipient-student@example.com';
        $user->setPassword('A-local-password-123!');
        $provider = auth()->getProvider();
        $provider->save($user);
        $recipientId = (int) $provider->getInsertID();
        $batchId     = (new BatchModel())->insert(['identifier' => 'mixed-recipient-batch', 'issuer' => 'Issuer']);

        $this->expectException(DatabaseException::class);
        $this->db->table('exam_vouchers')->insert([
            'batch_id'       => $batchId, 'code_ciphertext' => 'encrypted', 'code_digest' => hash('sha256', 'mixed-recipient-code'),
            'expires_on'     => '2030-01-01', 'recipient_type' => 'internal', 'recipient_id' => $recipientId,
            'recipient_name' => 'External Student', 'recipient_email' => 'external@example.com',
        ]);
    }
}
