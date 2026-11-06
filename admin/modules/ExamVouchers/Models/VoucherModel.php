<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Models;

use CodeIgniter\Model;

class VoucherModel extends Model
{
    protected $table      = 'exam_vouchers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    public function lockForEmail(int $voucherId): ?array
    {
        $table = $this->db->protectIdentifiers($this->db->prefixTable($this->table));

        return $this->db->query("SELECT * FROM {$table} WHERE id = ? FOR UPDATE", [$voucherId])->getRowArray();
    }

    public function internalEmailRecipient(int $userId): array
    {
        $user     = $this->db->table('users')->select('username')->where('id', $userId)->get()->getRowArray();
        $identity = $this->db->table('auth_identities')->select('secret')->where('user_id', $userId)->where('type', 'email_password')->get()->getRowArray();

        return ['name' => $user['username'] ?? '', 'email' => $identity['secret'] ?? null];
    }

    public function previewRecipients(array $voucherIds): array
    {
        $vouchers = [];

        foreach (array_chunk($voucherIds, 1000) as $chunk) {
            $rows = $this->db->table($this->table)->select('id, recipient_type, recipient_name, recipient_email, recipient_id')->whereIn('id', $chunk)->get()->getResultArray();

            foreach ($rows as $row) {
                $vouchers[(int) $row['id']] = $row;
            }
        }

        return $vouchers;
    }

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
            ->select('COUNT(*) FILTER (WHERE recipient_type IS NULL) AS unassigned', false)
            ->select('COUNT(*) FILTER (WHERE recipient_type IS NOT NULL) AS assigned', false)
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
            "UPDATE {$vouchers} SET recipient_id = ?, recipient_type = 'internal' WHERE id = ? AND recipient_type IS NULL AND EXISTS (SELECT 1 FROM {$users} WHERE id = ? AND deleted_at IS NULL)",
            [$recipientId, $voucherId, $recipientId],
        );

        return $this->db->affectedRows() === 1;
    }

    public function assignExternalRecipient(int $voucherId, string $name, string $email): bool
    {
        $vouchers = $this->db->protectIdentifiers($this->db->prefixTable('exam_vouchers'));
        $this->db->query(
            "UPDATE {$vouchers} SET recipient_type = 'external', recipient_name = ?, recipient_email = ? WHERE id = ? AND recipient_type IS NULL",
            [trim($name), trim($email), $voucherId],
        );

        return $this->db->affectedRows() === 1;
    }
}
