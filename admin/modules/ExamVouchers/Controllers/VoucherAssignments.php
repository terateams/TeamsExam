<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourcePresenter;
use Modules\ExamVouchers\Models\VoucherModel;
use Psr\Log\LoggerInterface;

class VoucherAssignments extends ResourcePresenter
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        service('language')->setLocale($request->getLocale());
    }

    public function form($id): ResponseInterface|string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $voucher = (new VoucherModel())->select('id, recipient_type')->find((int) $id);
        if ($voucher === null) {
            return $this->response->setStatusCode(404);
        }

        $search    = $this->request->getGet('username');
        $search    = is_string($search) ? trim($search) : '';
        $users     = [];
        $moreUsers = false;
        if ($voucher['recipient_type'] === null && $search !== '' && mb_strlen($search) <= 30) {
            $escape = db_connect()->likeEscapeChar;
            $match  = strtr($search, [$escape => $escape . $escape, '%' => $escape . '%', '_' => $escape . '_']);
            $users  = auth()->getProvider()->select('id, username, avatar')
                ->like('username', $match, 'both', null, true)->orderBy('username')->findAll(21);
            $moreUsers = count($users) > 20;
            $users     = array_slice($users, 0, 20);
        }

        return view('Modules\ExamVouchers\Views\assign', [
            'me'     => auth()->user(), 'page_title' => lang('ExamVouchers.assign'), 'voucher' => $voucher,
            'search' => $search, 'users' => $users, 'moreUsers' => $moreUsers,
        ]);
    }

    public function assign($id): RedirectResponse|ResponseInterface
    {
        $model = new VoucherModel();
        if ($model->select('id')->find((int) $id) === null) {
            return $this->response->setStatusCode(404);
        }
        $validation = service('validation');
        $post       = $this->request->getPost() ?? [];

        foreach (['recipient_name', 'recipient_email'] as $field) {
            if (isset($post[$field]) && is_string($post[$field])) {
                $post[$field] = trim($post[$field]);
            }
        }

        $rules = ['recipient_type' => 'required|in_list[internal,external]'];
        if (($post['recipient_type'] ?? null) === 'internal') {
            $rules['recipient_id'] = 'required|is_natural_no_zero|max_length[10]|less_than_equal_to[2147483647]';
        } elseif (($post['recipient_type'] ?? null) === 'external') {
            $rules['recipient_name']  = 'required|max_length[255]';
            $rules['recipient_email'] = 'required|valid_email|max_length[254]';
        }
        $validation->setRules($rules);
        $valid = $validation->run($post);
        if ($valid) {
            $recipient = $validation->getValidated();
            $success   = $recipient['recipient_type'] === 'internal'
                ? $model->assignRecipient((int) $id, (int) $recipient['recipient_id'])
                : $model->assignExternalRecipient((int) $id, $recipient['recipient_name'], $recipient['recipient_email']);
        } else {
            $success = false;
        }

        return redirect()->to(route_to('admin/exam-vouchers/assign', $id))
            ->with('alert', ['type' => $success ? 'success' : 'danger', 'message' => lang($success ? 'ExamVouchers.assigned' : 'ExamVouchers.assignFailed')]);
    }
}
