<?php

$routes->group('{locale}/admin', ['namespace' => 'Modules\ExamVouchers\Controllers', 'filter' => 'permission:exam-vouchers.manage'], static function ($routes) {
    $routes->presenter('exam-voucher-batches', ['controller' => 'Batches', 'only' => ['index'], 'placeholder' => '(:num)', 'as' => 'admin/exam-voucher-batches']);

    foreach (['new', 'create', 'show', 'edit', 'update', 'delete'] as $action) {
        $routes->presenter('exam-voucher-batches', ['controller' => 'Batches', 'only' => [$action], 'placeholder' => '(:num)', 'as' => 'admin/exam-voucher-batches/' . $action]);
    }
    $routes->get('exam-voucher-batches/(:num)/import', 'VoucherImports::form/$1', ['as' => 'admin/exam-vouchers/import']);
    $routes->post('exam-voucher-batches/(:num)/import', 'VoucherImports::import/$1', ['as' => 'admin/exam-vouchers/import/store']);
    $routes->get('exam-vouchers', 'Vouchers::index', ['as' => 'admin/exam-vouchers']);
    $routes->get('exam-vouchers/(:num)', 'Vouchers::show/$1', ['as' => 'admin/exam-vouchers/show']);
    $routes->get('exam-vouchers/(:num)/assign', 'VoucherAssignments::form/$1', ['as' => 'admin/exam-vouchers/assign']);
    $routes->post('exam-vouchers/(:num)/assign', 'VoucherAssignments::assign/$1', ['as' => 'admin/exam-vouchers/assign/store']);
    $routes->get('exam-vouchers/(:num)/verification', 'VoucherVerifications::form/$1', ['as' => 'admin/exam-vouchers/verification']);
    $routes->post('exam-vouchers/(:num)/verification', 'VoucherVerifications::save/$1', ['as' => 'admin/exam-vouchers/verification/store']);
    $routes->get('exam-vouchers/(:num)/note', 'VoucherNotes::form/$1', ['as' => 'admin/exam-vouchers/note']);
    $routes->post('exam-vouchers/(:num)/note', 'VoucherNotes::save/$1', ['as' => 'admin/exam-vouchers/note/store']);
});
