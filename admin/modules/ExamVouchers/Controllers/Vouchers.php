<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourcePresenter;
use Config\Services;
use Geminus\Admin\Libraries\DataManagement\ListQuery;
use Modules\ExamVouchers\Models\VoucherModel;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class Vouchers extends ResourcePresenter
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        service('language')->setLocale($request->getLocale());
    }

    public function index(): string
    {
        $query = new ListQuery($this->request->getGet(), ['id' => 'exam_vouchers.id'], 'id');
        $model = new VoucherModel();
        $model->select('exam_vouchers.id, exam_vouchers.batch_id, exam_vouchers.exam, exam_vouchers.region, exam_vouchers.expires_on, exam_vouchers.recipient_type, exam_vouchers.recipient_id, exam_vouchers.recipient_name, exam_vouchers.recipient_email, users.username AS recipient_username, exam_voucher_batches.identifier, exam_voucher_batches.issuer')
            ->select('CASE WHEN exam_vouchers.verification_note IS NULL THEN 0 ELSE 1 END AS usage_verified', false)
            ->join('exam_voucher_batches', 'exam_voucher_batches.id = exam_vouchers.batch_id')
            ->join('users', 'users.id = exam_vouchers.recipient_id', 'left');
        $batch = $this->request->getGet('batch');
        if (is_string($batch) && ctype_digit($batch) && strlen($batch) <= 10 && (int) $batch > 0) {
            $model->where('exam_vouchers.batch_id', (int) $batch);
        } else {
            $batch = '';
        }
        $query->apply($model, ['exam_voucher_batches.identifier', 'exam_voucher_batches.issuer', 'exam_vouchers.exam', 'exam_vouchers.region'], stableField: 'exam_vouchers.id');

        return view('Modules\ExamVouchers\Views\vouchers', [
            'me'        => auth()->user(), 'page_title' => lang('ExamVouchers.vouchers'), 'query' => $query,
            'vouchers'  => $model->paginate($query->perPage), 'pager' => $model->pager, 'batch' => $batch,
            'inventory' => $model->inventoryCounts(),
        ]);
    }

    public function show($id = null): ResponseInterface|string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0');
        $this->response->setHeader('Pragma', 'no-cache');
        $voucher = (new VoucherModel())
            ->select('exam_vouchers.id, exam_vouchers.code_ciphertext, exam_vouchers.exam, exam_vouchers.region, exam_vouchers.expires_on, exam_vouchers.recipient_type, exam_vouchers.recipient_id, exam_vouchers.recipient_name, exam_vouchers.recipient_email, users.username AS recipient_username, exam_voucher_batches.identifier, exam_voucher_batches.issuer')
            ->join('exam_voucher_batches', 'exam_voucher_batches.id = exam_vouchers.batch_id')
            ->join('users', 'users.id = exam_vouchers.recipient_id', 'left')
            ->where('exam_vouchers.id', (int) $id)->first();
        if ($voucher === null) {
            return $this->response->setStatusCode(404);
        }

        try {
            $ciphertext = base64_decode($voucher['code_ciphertext'], true);
            if ($ciphertext === false || $ciphertext === '') {
                throw new RuntimeException('Invalid ciphertext.');
            }
            $code = service('encrypter')->decrypt($ciphertext);
            if (! is_string($code) || $code === '') {
                throw new RuntimeException('Decryption failed.');
            }
        } catch (Throwable $exception) {
            log_message('error', 'Voucher decryption failed for ID {id}: {type}', ['id' => (int) $id, 'type' => $exception::class]);

            return $this->response->setStatusCode(500)->setBody(lang('ExamVouchers.decryptFailed'));
        }
        unset($voucher['code_ciphertext']);

        $renderer = Services::renderer(null, null, false);

        return $renderer->setData([
            'me' => auth()->user(), 'page_title' => lang('ExamVouchers.voucherDetail'), 'voucher' => $voucher, 'code' => $code,
        ], 'raw')->render('Modules\ExamVouchers\Views\voucher', [], false);
    }
}
