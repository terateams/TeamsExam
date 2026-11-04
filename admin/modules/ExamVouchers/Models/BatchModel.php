<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Models;

use CodeIgniter\Model;
use OverflowException;

class BatchModel extends Model
{
    protected $table         = 'exam_voucher_batches';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['identifier', 'issuer', 'purchased_on'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public function suggestIdentifier(): string
    {
        $identifiers = $this->select('identifier')->like('identifier', 'BATCH-', 'after')->findColumn('identifier') ?? [];
        $highest     = '0';

        foreach ($identifiers as $identifier) {
            if (! preg_match('/\ABATCH-([0-9]{6}|[1-9][0-9]{6,})\z/', $identifier, $matches)) {
                continue;
            }

            $number = ltrim($matches[1], '0');
            $number = $number === '' ? '0' : $number;
            if (strlen($number) > strlen($highest) || (strlen($number) === strlen($highest) && strcmp($number, $highest) > 0)) {
                $highest = $number;
            }
        }

        $identifier = 'BATCH-' . str_pad(str_increment($highest), 6, '0', STR_PAD_LEFT);
        if (strlen($identifier) > 150) {
            throw new OverflowException('No further batch identifier can be suggested.');
        }

        return $identifier;
    }
}
