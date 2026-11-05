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
        return ['operations' => [
            [
                'controller' => 'Modules\\ExamVouchers\\Controllers\\Batches::create',
                'methods'    => ['POST'], 'action' => 'exam-voucher-batch.create',
                'object'     => ['type' => 'exam-voucher-batch'],
                'fields'     => ['identifier', 'issuer', 'purchased_on'],
            ],
            [
                'controller' => 'Modules\\ExamVouchers\\Controllers\\Batches::update',
                'methods'    => ['POST'], 'action' => 'exam-voucher-batch.update',
                'object'     => ['type' => 'exam-voucher-batch', 'route_parameter' => 0],
                'fields'     => ['identifier', 'issuer', 'purchased_on'],
            ],
            [
                'controller' => 'Modules\\ExamVouchers\\Controllers\\VoucherImports::import',
                'methods'    => ['POST'], 'action' => 'exam-voucher.import',
                'object'     => ['type' => 'exam-voucher-batch', 'route_parameter' => 0],
                'fields'     => ['file.size'],
            ],
            [
                'controller' => 'Modules\\ExamVouchers\\Controllers\\VoucherAssignments::assign',
                'methods'    => ['POST'], 'action' => 'exam-voucher.assign',
                'object'     => ['type' => 'exam-voucher', 'route_parameter' => 0],
                'fields'     => ['recipient_type', 'recipient_id'],
            ],
        ]];
    }

    public static function AdminMenu(): array
    {
        return ['items' => [[
            'permission' => 'exam-vouchers.manage',
            'label'      => 'ExamVouchers.menu',
            'icon'       => 'ti-ticket',
            'children'   => [
                [
                    'route'  => 'admin/exam-voucher-batches',
                    'label'  => 'ExamVouchers.batch',
                    'active' => '*/admin/exam-voucher-batches*',
                ],
                [
                    'route'  => 'admin/exam-vouchers',
                    'label'  => 'ExamVouchers.voucherList',
                    'active' => '*/admin/exam-vouchers*',
                ],
            ],
        ]]];
    }
}
