<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourcePresenter;
use Modules\ExamVouchers\Models\VoucherModel;
use Psr\Log\LoggerInterface;
use Throwable;

class VoucherVerifications extends ResourcePresenter
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        service('language')->setLocale($request->getLocale());
    }

    public function form($id): ResponseInterface|string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $voucher = (new VoucherModel())->select('id, verification_note, verification_version')->find((int) $id);
        if ($voucher === null) {
            return $this->response->setStatusCode(404);
        }

        return view('Modules\ExamVouchers\Views\verification', [
            'me' => auth()->user(), 'page_title' => lang('ExamVouchers.verification'), 'voucher' => $voucher,
        ]);
    }

    public function save($id): RedirectResponse|ResponseInterface
    {
        $model = new VoucherModel();
        if ($model->select('id')->find((int) $id) === null) {
            return $this->response->setStatusCode(404);
        }

        $input      = $this->request->getPost();
        $validation = service('validation');
        $rules      = [
            'verified' => 'required|in_list[0,1]',
            'version'  => 'required|is_natural|less_than[2147483647]',
        ];
        $validNote = true;
        if (($input['verified'] ?? null) === '1') {
            if (is_string($input['note'] ?? null)) {
                $validNote     = mb_check_encoding($input['note'], 'UTF-8') && ! preg_match('/[\x00-\x1F\x7F]/', $input['note']);
                $input['note'] = $validNote ? trim($input['note']) : '';
            }
            $rules['note'] = 'required|max_length[255]';
        }
        $validation->setRules($rules);
        $valid = $validation->run($input);
        $data  = $valid ? $validation->getValidated() : [];
        $note  = $data['note'] ?? null;
        $valid = $valid && $validNote;

        $success = false;
        if ($valid) {
            try {
                $success = $model->recordVerification((int) $id, (int) $data['version'], $note);
            } catch (Throwable) {
                log_message('error', 'Voucher verification save failed for ID {id}.', ['id' => (int) $id]);
            }
        }

        $redirect = redirect()->to(route_to('admin/exam-vouchers/verification', $id))
            ->with('alert', ['type' => $success ? 'success' : 'danger', 'message' => lang($success ? 'ExamVouchers.verificationSaved' : 'ExamVouchers.verificationFailed')]);
        if (! $valid) {
            $redirect->with('verification_error', lang('ExamVouchers.verificationInvalid'));
        }

        return $redirect;
    }
}
