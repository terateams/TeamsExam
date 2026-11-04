<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourcePresenter;
use Geminus\Admin\Libraries\DataManagement\ListQuery;
use Modules\ExamVouchers\Models\BatchModel;
use Psr\Log\LoggerInterface;
use Throwable;

class Batches extends ResourcePresenter
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        service('language')->setLocale($request->getLocale());
    }

    public function index(): string
    {
        $query = new ListQuery($this->request->getGet(), ['id' => 'id', 'identifier' => 'identifier', 'issuer' => 'issuer', 'purchased_on' => 'purchased_on'], 'id');
        $model = $query->apply(new BatchModel(), ['identifier', 'issuer']);

        return view('Modules\ExamVouchers\Views\index', [
            'me'      => auth()->user(), 'page_title' => lang('ExamVouchers.title'), 'query' => $query,
            'batches' => $model->paginate($query->perPage), 'pager' => $model->pager,
        ]);
    }

    public function new(): string
    {
        return $this->form([], route_to('admin/exam-voucher-batches/create'));
    }

    public function create(): RedirectResponse
    {
        return $this->save();
    }

    public function show($id = null): ResponseInterface|string
    {
        $batch = (new BatchModel())->find((int) $id);
        if ($batch === null) {
            return $this->response->setStatusCode(404);
        }

        return view('Modules\ExamVouchers\Views\show', [
            'me' => auth()->user(), 'page_title' => lang('ExamVouchers.batch'), 'batch' => $batch,
        ]);
    }

    public function edit($id = null): ResponseInterface|string
    {
        $batch = (new BatchModel())->find((int) $id);
        if ($batch === null) {
            return $this->response->setStatusCode(404);
        }

        return $this->form($batch, route_to('admin/exam-voucher-batches/update', $id));
    }

    public function update($id = null): RedirectResponse|ResponseInterface
    {
        return $this->save((int) $id);
    }

    public function delete($id = null): RedirectResponse|ResponseInterface
    {
        $model = new BatchModel();
        if ($model->find((int) $id) === null) {
            return $this->response->setStatusCode(404);
        }

        try {
            $deleted = $model->delete((int) $id);
        } catch (Throwable $exception) {
            log_message('error', 'Voucher batch deletion failed: {type}', ['type' => $exception::class]);
            $deleted = false;
        }
        if (! $deleted) {
            if (db_connect()->table('exam_vouchers')->where('batch_id', (int) $id)->countAllResults() > 0) {
                return redirect()->to(route_to('admin/exam-voucher-batches/show', $id))->with('alert', ['type' => 'danger', 'message' => lang('ExamVouchers.batchNotEmpty')]);
            }

            return redirect()->to(route_to('admin/exam-voucher-batches/show', $id))->with('alert', ['type' => 'danger', 'message' => lang('ExamVouchers.deleteFailed')]);
        }

        return redirect()->to(route_to('admin/exam-voucher-batches'))->with('alert', ['type' => 'success', 'message' => lang('ExamVouchers.deleted')]);
    }

    private function form(array $batch, string $action): string
    {
        return view('Modules\ExamVouchers\Views\form', [
            'me'    => auth()->user(), 'page_title' => lang($batch === [] ? 'ExamVouchers.create' : 'ExamVouchers.edit'),
            'batch' => $batch, 'formAction' => $action,
        ]);
    }

    private function save(?int $id = null): RedirectResponse|ResponseInterface
    {
        $model = new BatchModel();
        if ($id !== null && $model->find($id) === null) {
            return $this->response->setStatusCode(404);
        }
        $input = $this->request->getPost();

        foreach (['identifier', 'issuer', 'purchased_on'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (($input['purchased_on'] ?? null) === '') {
            $input['purchased_on'] = null;
        }
        $validation = service('validation');
        $validation->setRules([
            'identifier'   => ['label' => 'ExamVouchers.identifier', 'rules' => 'required|string|max_length[150]|regex_match[/\A[^\x00]*\z/u]|is_unique[exam_voucher_batches.identifier,id,' . ($id ?? 0) . ']'],
            'issuer'       => ['label' => 'ExamVouchers.issuer', 'rules' => 'required|string|max_length[150]|regex_match[/\A[^\x00]*\z/u]'],
            'purchased_on' => ['label' => 'ExamVouchers.purchasedOn', 'rules' => 'permit_empty|string|regex_match[/\A(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}\z/]|valid_date[Y-m-d]'],
        ]);
        $back = $id === null ? route_to('admin/exam-voucher-batches/new') : route_to('admin/exam-voucher-batches/edit', $id);
        if (! $validation->run($input)) {
            return redirect()->to($back)->withInput()->with('errors', $validation->getErrors());
        }
        $saved = $id === null ? $model->insert($validation->getValidated()) : $model->update($id, $validation->getValidated());
        if ($saved === false) {
            return redirect()->to($back)->withInput()->with('alert', ['type' => 'danger', 'message' => lang('ExamVouchers.saveFailed')]);
        }

        return redirect()->to(route_to('admin/exam-voucher-batches/show', $id ?? $saved))->with('alert', ['type' => 'success', 'message' => lang('ExamVouchers.saved')]);
    }
}
