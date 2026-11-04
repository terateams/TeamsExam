<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Models;

use CodeIgniter\Model;

class VoucherModel extends Model
{
    protected $table      = 'exam_vouchers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    public function expiringBetween(string $start, string $end): array
    {
        return $this->select('id, expires_on')
            ->where('expires_on >=', $start)
            ->where('expires_on <=', $end)
            ->findAll();
    }

    public function countsByBatch(array $batchIds): array
    {
        if ($batchIds === []) {
            return [];
        }

        $rows = $this->db->table($this->table)
            ->select('batch_id, COUNT(*) AS voucher_count')
            ->whereIn('batch_id', $batchIds)
            ->groupBy('batch_id')
            ->get()->getResultArray();
        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['batch_id']] = (int) $row['voucher_count'];
        }

        return $counts;
    }

    public function saveNote(int $voucherId, ?string $note): bool
    {
        $this->db->table($this->table)->where('id', $voucherId)->update(['note' => $note]);

        return $this->db->affectedRows() === 1;
    }

    public function inventoryCounts(): array
    {
        $row = $this->db->table($this->table)
            ->select('COUNT(*) FILTER (WHERE recipient_id IS NULL) AS unassigned', false)
            ->select('COUNT(*) FILTER (WHERE recipient_id IS NOT NULL) AS assigned', false)
            ->select('COUNT(*) FILTER (WHERE verification_note IS NOT NULL) AS verified', false)
            ->get()->getRowArray();

        return [
            'unassigned' => (int) $row['unassigned'],
            'assigned'   => (int) $row['assigned'],
            'verified'   => (int) $row['verified'],
        ];
    }

    public function recordVerification(int $voucherId, int $version, ?string $note): bool
    {
        $table = $this->db->protectIdentifiers($this->db->prefixTable('exam_vouchers'));
        $this->db->query(
            "UPDATE {$table} SET verification_note = ?, verification_version = verification_version + 1 WHERE id = ? AND verification_version = ?",
            [$note, $voucherId, $version],
        );

        return $this->db->affectedRows() === 1;
    }

    public function assignRecipient(int $voucherId, int $recipientId): bool
    {
        $vouchers = $this->db->protectIdentifiers($this->db->prefixTable('exam_vouchers'));
        $users    = $this->db->protectIdentifiers($this->db->prefixTable('users'));
        $this->db->query(
            "UPDATE {$vouchers} SET recipient_id = ? WHERE id = ? AND recipient_id IS NULL AND EXISTS (SELECT 1 FROM {$users} WHERE id = ? AND deleted_at IS NULL)",
            [$recipientId, $voucherId, $recipientId],
        );

        return $this->db->affectedRows() === 1;
    }
}
