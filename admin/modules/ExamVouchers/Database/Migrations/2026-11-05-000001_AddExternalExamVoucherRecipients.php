<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExternalExamVoucherRecipients extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('exam_vouchers', [
            'recipient_type'  => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
            'recipient_name'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'recipient_email' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
        ]);

        $vouchers = $this->db->protectIdentifiers($this->db->prefixTable('exam_vouchers'));
        $this->db->query("UPDATE {$vouchers} SET recipient_type = 'internal' WHERE recipient_id IS NOT NULL");
        $this->db->query(
            "ALTER TABLE {$vouchers} ADD CONSTRAINT exam_vouchers_recipient_type_check CHECK (
                (recipient_type IS NULL AND recipient_id IS NULL AND recipient_name IS NULL AND recipient_email IS NULL)
                OR (recipient_type = 'internal' AND recipient_id IS NOT NULL AND recipient_name IS NULL AND recipient_email IS NULL)
                OR (recipient_type = 'external' AND recipient_id IS NULL AND recipient_name IS NOT NULL AND recipient_name ~ '[^[:space:]]' AND recipient_email IS NOT NULL AND recipient_email ~ '[^[:space:]]')
            )",
        );
    }

    public function down(): void
    {
        $vouchers = $this->db->protectIdentifiers($this->db->prefixTable('exam_vouchers'));
        $this->db->query("ALTER TABLE {$vouchers} DROP CONSTRAINT IF EXISTS exam_vouchers_recipient_type_check");

        foreach (['recipient_type', 'recipient_name', 'recipient_email'] as $column) {
            if ($this->db->fieldExists($column, 'exam_vouchers')) {
                $this->forge->dropColumn('exam_vouchers', $column);
            }
        }
    }
}
