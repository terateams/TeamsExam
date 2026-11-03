<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExamVouchers extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT'],
            'key_fingerprint' => ['type' => 'CHAR', 'constraint' => 64],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('exam_voucher_key_guard');
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'auto_increment' => true],
            'batch_id'             => ['type' => 'INT'],
            'code_ciphertext'      => ['type' => 'TEXT'],
            'code_digest'          => ['type' => 'CHAR', 'constraint' => 64],
            'exam'                 => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'region'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'expires_on'           => ['type' => 'DATE'],
            'recipient_id'         => ['type' => 'INT', 'null' => true],
            'verification_note'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'verification_version' => ['type' => 'INT', 'default' => 0],
            'note'                 => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code_digest');
        $this->forge->addForeignKey('batch_id', 'exam_voucher_batches', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_vouchers');
        $vouchers = $this->db->protectIdentifiers($this->db->prefixTable('exam_vouchers'));
        $users    = $this->db->protectIdentifiers($this->db->prefixTable('users'));
        $this->db->query("ALTER TABLE {$vouchers} ADD CONSTRAINT exam_vouchers_recipient_fk FOREIGN KEY (recipient_id) REFERENCES {$users} (id) ON DELETE RESTRICT");
        $this->db->query("ALTER TABLE {$vouchers} ADD CONSTRAINT exam_vouchers_verification_note_check CHECK (verification_note IS NULL OR length(btrim(verification_note)) > 0)");
    }

    public function down(): void
    {
        $this->forge->dropTable('exam_vouchers', true);
        $this->forge->dropTable('exam_voucher_key_guard', true);
    }
}
