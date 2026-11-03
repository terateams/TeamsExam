<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Encryption\EncrypterInterface;
use DateTimeImmutable;
use Geminus\Admin\Libraries\DataManagement\CsvImport;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class VoucherCsvImport
{
    public const COLUMNS = ['code', 'exam', 'region', 'expires_on'];

    private string $digestKey;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly EncrypterInterface $encrypter,
        string $key,
    ) {
        if (strlen($key) < 32) {
            throw new RuntimeException('Encryption key is not configured.');
        }
        $this->digestKey = hash_hkdf('sha256', $key, 32, 'exam-voucher-deduplication-v1');
    }

    public function import($stream, int $batchId): array
    {
        $pending = [];
        $seen    = [];
        $report  = (new CsvImport())->import($stream, self::COLUMNS, function (array $data) use (&$pending, &$seen): array {
            $data   = array_map('trim', $data);
            $reason = $this->validate($data);
            if ($reason !== '') {
                return ['result' => 'error', 'reason' => $reason];
            }
            $digest = hash_hmac('sha256', $data['code'], $this->digestKey);
            if (isset($seen[$digest]) || $this->db->table('exam_vouchers')->where('code_digest', $digest)->countAllResults() > 0) {
                return ['result' => 'error', 'reason' => 'duplicate'];
            }
            $seen[$digest] = true;
            $pending[]     = $data + ['digest' => $digest];

            return ['result' => 'created', 'reason' => ''];
        }, rules: [
            'code'       => 'required|string',
            'exam'       => 'permit_empty|string',
            'region'     => 'permit_empty|string',
            'expires_on' => 'required|string',
        ]);
        if ($report === []) {
            throw new InvalidArgumentException('CSV contains no data rows.');
        }
        $failed = array_filter($report, static fn (array $row): bool => $row['result'] !== 'created');
        if ($failed !== []) {
            return array_map(static fn (array $row): array => [
                'row'    => $row['row'], 'result' => 'error',
                'reason' => $row['result'] === 'created' ? 'not_imported' : $row['reason'],
            ], $report);
        }

        if (! $this->db->transBegin()) {
            throw new RuntimeException('Could not start import transaction.');
        }

        try {
            $fingerprint = hash_hmac('sha256', 'exam-voucher-key-guard-v1', $this->digestKey);
            $this->db->query('INSERT INTO exam_voucher_key_guard (id, key_fingerprint) VALUES (1, ?) ON CONFLICT (id) DO NOTHING', [$fingerprint]);
            $guard = $this->db->table('exam_voucher_key_guard')->where('id', 1)->get()->getRowArray();
            if ($guard === null || ! hash_equals($guard['key_fingerprint'], $fingerprint)) {
                throw new RuntimeException('Voucher encryption key changed.');
            }

            foreach ($pending as $data) {
                $saved = $this->db->table('exam_vouchers')->insert([
                    'batch_id'        => $batchId,
                    'code_ciphertext' => base64_encode($this->encrypter->encrypt($data['code'])),
                    'code_digest'     => $data['digest'],
                    'exam'            => $data['exam'] === '' ? null : $data['exam'],
                    'region'          => $data['region'] === '' ? null : $data['region'],
                    'expires_on'      => $data['expires_on'],
                ]);
                if (! $saved) {
                    throw new RuntimeException('Voucher insert failed.');
                }
            }
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Voucher import commit failed.');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();

            throw new RuntimeException('Voucher import failed.', 0, $exception);
        }

        return array_map(static fn (array $row): array => [
            'row' => $row['row'], 'result' => 'created', 'reason' => '',
        ], $report);
    }

    private function validate(array $data): string
    {
        foreach (['code', 'exam', 'region'] as $field) {
            if (! mb_check_encoding($data[$field], 'UTF-8') || mb_strlen($data[$field]) > 255 || preg_match('/[\x00-\x1f\x7f]/', $data[$field])) {
                return 'invalid_' . $field;
            }
        }
        if ($data['code'] === '') {
            return 'invalid_code';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['expires_on']);
        if ($date === false || $date->format('Y-m-d') !== $data['expires_on'] || $data['expires_on'] < '0001-01-01') {
            return 'invalid_date';
        }

        return '';
    }
}
