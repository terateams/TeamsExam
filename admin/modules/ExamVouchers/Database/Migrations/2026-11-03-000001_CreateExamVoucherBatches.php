<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExamVoucherBatches extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'identifier' => ['type' => 'VARCHAR', 'constraint' => 150],
            'issuer'     => ['type' => 'VARCHAR', 'constraint' => 150],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('identifier');
        $this->forge->createTable('exam_voucher_batches');
    }

    public function down(): void
    {
        $this->forge->dropTable('exam_voucher_batches');
    }
}
