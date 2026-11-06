<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Models;

use CodeIgniter\Model;
use RuntimeException;

class VoucherEmailDeliveryModel extends Model
{
    public const QUEUED = 'queued';
    public const SENT   = 'sent';
    public const FAILED = 'failed';

    protected $table      = 'exam_voucher_email_deliveries';
    protected $returnType = 'array';

    public function states(int $voucherId): array
    {
        return $this->statesFor([$voucherId])[$voucherId] ?? ['active' => false, 'sent' => false];
    }

    public function statesFor(array $voucherIds): array
    {
        $states = [];

        foreach (array_chunk($voucherIds, 1000) as $chunk) {
            $rows = $this->db->table($this->table)->select('voucher_id, status')->whereIn('voucher_id', $chunk)->whereIn('status', [self::QUEUED, self::SENT])->distinct()->get()->getResultArray();

            foreach ($rows as $row) {
                $id = (int) $row['voucher_id'];
                $states[$id] ??= ['active' => false, 'sent' => false];
                $states[$id][$row['status'] === self::QUEUED ? 'active' : 'sent'] = true;
            }
        }

        return $states;
    }

    public function metadata(string $batch, ?int $limit = null, int $offset = 0): array|int
    {
        $query = $this->db->table($this->table)->select('id, voucher_id, recipient, subject, status, attempts, created_at, processed_at, failure_reason');
        if ($batch !== '') {
            $query->where('batch_key', $batch);
        }

        return $limit === null ? $query->countAllResults() : $query->orderBy('id', 'DESC')->limit($limit, $offset)->get()->getResultArray();
    }

    public function resendable(int $id): ?array
    {
        return $this->db->table($this->table)->select('voucher_id')->where('id', $id)->whereIn('status', [self::SENT, self::FAILED])->get()->getRowArray();
    }

    public function queued(int $id): ?array
    {
        return $this->db->table($this->table)->where('id', $id)->where('status', self::QUEUED)->get()->getRowArray();
    }

    public function createDelivery(array $data): int
    {
        if (! $this->db->table($this->table)->insert($data)) {
            throw new RuntimeException('Could not create email delivery.');
        }

        return (int) $this->db->insertID();
    }

    public function assignJob(int $id, int $jobId): bool
    {
        return $this->db->table($this->table)->where('id', $id)->update(['job_id' => $jobId]);
    }

    public function recordAttempt(int $id, int $attempts, bool $sent): bool
    {
        $data = ['attempts' => $attempts, 'processed_at' => gmdate('Y-m-d H:i:s'), 'failure_reason' => $sent ? null : 'Email delivery failed.'];
        if ($sent) {
            $data += ['status' => self::SENT, 'snapshot_ciphertext' => ''];
        }

        return $this->db->table($this->table)->where('id', $id)->where('status', self::QUEUED)->update($data);
    }

    public function markFailed(int $jobId): bool
    {
        return $this->db->table($this->table)->where('job_id', $jobId)->where('status', self::QUEUED)->update([
            'status'       => self::FAILED, 'failure_reason' => 'Email delivery failed.',
            'processed_at' => gmdate('Y-m-d H:i:s'), 'snapshot_ciphertext' => '',
        ]);
    }
}
