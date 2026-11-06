<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVoucherEmailDeliveries extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'auto_increment' => true],
            'voucher_id'          => ['type' => 'INT'],
            'batch_key'           => ['type' => 'CHAR', 'constraint' => 32],
            'recipient'           => ['type' => 'VARCHAR', 'constraint' => 254],
            'subject'             => ['type' => 'TEXT'],
            'snapshot_ciphertext' => ['type' => 'TEXT'],
            'status'              => ['type' => 'VARCHAR', 'constraint' => 16],
            'attempts'            => ['type' => 'INT', 'default' => 0],
            'job_id'              => ['type' => 'INT', 'null' => true],
            'created_at'          => ['type' => 'DATETIME'],
            'processed_at'        => ['type' => 'DATETIME', 'null' => true],
            'failure_reason'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('voucher_id', 'exam_vouchers', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('exam_voucher_email_deliveries');

        $table = $this->db->protectIdentifiers($this->db->prefixTable('exam_voucher_email_deliveries'));
        $this->db->query("CREATE UNIQUE INDEX exam_voucher_email_active_unique ON {$table} (voucher_id) WHERE status = 'queued'");
        $this->db->query("ALTER TABLE {$table} ADD CONSTRAINT exam_voucher_email_status_check CHECK (status IN ('queued', 'sent', 'failed'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('exam_voucher_email_deliveries', true);
    }
}
