<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Config;

class Registrar
{
    public static function Tasks(): array
    {
        return ['commands' => [[
            'name'     => 'exam-voucher-expiry',
            'command'  => 'exam-vouchers:remind-expiry',
            'schedule' => '0 0 * * *',
        ]]];
    }

    public static function OperationAudit(): array
    {
        return ['operations' => [[
            'controller' => 'Modules\\ExamVouchers\\Controllers\\VoucherImports::import',
            'methods'    => ['POST'], 'action' => 'exam-voucher.import',
            'object'     => ['type' => 'exam-voucher-batch', 'route_parameter' => 0],
            'fields'     => ['file.size'],
        ]]];
    }

    public static function AdminMenu(): array
    {
        return ['items' => [[
            'permission' => 'exam-vouchers.manage',
            'route'      => 'admin/exam-voucher-batches',
            'label'      => 'ExamVouchers.title',
            'icon'       => 'ti-ticket',
            'active'     => '*/admin/exam-voucher*',
        ]]];
    }
}
