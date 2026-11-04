<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Models;

use CodeIgniter\Model;

class BatchModel extends Model
{
    protected $table         = 'exam_voucher_batches';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['identifier', 'issuer', 'purchased_on'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';
}
