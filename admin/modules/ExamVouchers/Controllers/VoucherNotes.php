<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourcePresenter;
use Config\Services;
use Modules\ExamVouchers\Models\VoucherModel;
use Psr\Log\LoggerInterface;
use Throwable;

class VoucherNotes extends ResourcePresenter
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        service('language')->setLocale($request->getLocale());
    }

    public function form($id): ResponseInterface|string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $voucher = (new VoucherModel())->select('id, note')->find((int) $id);
        if ($voucher === null) {
            return $this->response->setStatusCode(404);
        }

        return Services::renderer(null, null, false)->setData([
            'me' => auth()->user(), 'page_title' => lang('ExamVouchers.voucherNote'), 'voucher' => $voucher,
        ], 'raw')->render('Modules\ExamVouchers\Views\note', [], false);
    }

    public function save($id): RedirectResponse|ResponseInterface
    {
        $model = new VoucherModel();
        if ($model->select('id')->find((int) $id) === null) {
            return $this->response->setStatusCode(404);
        }

        $input      = $this->request->getPost();
        $note       = $input['note'] ?? null;
        $valid      = is_string($note) && mb_check_encoding($note, 'UTF-8') && ! preg_match('/[\x00-\x1F\x7F]/', $note);
        $validation = service('validation');
        $validation->setRules(['note' => 'permit_empty|max_length[255]']);
        if ($valid) {
            $input['note'] = trim($note);
            $valid         = $validation->run($input);
        }

        $success = false;
        if ($valid) {
            $data = $validation->getValidated();

            try {
                $success = $model->saveNote((int) $id, $data['note'] === '' ? null : $data['note']);
            } catch (Throwable) {
                log_message('error', 'Voucher note save failed for ID {id}.', ['id' => (int) $id]);
            }
        }

        $redirect = redirect()->to(route_to('admin/exam-vouchers/note', $id))
            ->with('alert', ['type' => $success ? 'success' : 'danger', 'message' => lang($success ? 'ExamVouchers.voucherNoteSaved' : 'ExamVouchers.voucherNoteFailed')]);
        if (! $valid) {
            $redirect->with('voucher_note_error', lang('ExamVouchers.voucherNoteInvalid'));
            if (is_string($note) && mb_check_encoding($note, 'UTF-8') && ! preg_match('/[\x00-\x1F\x7F]/', $note)) {
                $redirect->with('voucher_note_input', $note);
            }
        }

        return $redirect;
    }
}
