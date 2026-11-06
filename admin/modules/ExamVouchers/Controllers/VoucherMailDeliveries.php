<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourcePresenter;
use Config\Services;
use Modules\ExamVouchers\Libraries\VoucherEmailQueue;
use Modules\ExamVouchers\Libraries\VoucherMailTemplate;
use Modules\ExamVouchers\Models\VoucherEmailDeliveryModel;
use Modules\ExamVouchers\Models\VoucherModel;
use Psr\Log\LoggerInterface;
use Throwable;

class VoucherMailDeliveries extends ResourcePresenter
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        service('language')->setLocale($request->getLocale());
        $response->setHeader('Cache-Control', 'private, no-store');
    }

    public function index(): string
    {
        return $this->deliveries(false);
    }

    public function operations(): string
    {
        return $this->deliveries(true);
    }

    private function deliveries(bool $operations): string
    {
        $model = new VoucherEmailDeliveryModel();
        $page  = max(1, (int) $this->request->getGet('page'));
        $batch = $this->request->getGet('batch');
        $batch = is_string($batch) && preg_match('/^[a-f0-9]{32}$/', $batch) ? $batch : '';
        $total = $model->metadata($batch);
        $page  = min($page, max(1, (int) ceil($total / 20)));

        return view('Modules\ExamVouchers\Views\mail_deliveries', [
            'me'    => auth()->user(), 'page_title' => lang('ExamVouchers.mailDeliveries'),
            'rows'  => $model->metadata($batch, 20, ($page - 1) * 20),
            'pager' => Services::pager(null, null, false)->makeLinks($page, 20, $total),
            'total' => $total, 'currentPage' => $page, 'perPage' => 20, 'operations' => $operations, 'batch' => $batch,
        ]);
    }

    public function preview(): ResponseInterface|string
    {
        $ids = $this->selection();
        if ($ids === null) {
            return redirect()->to(route_to('admin/exam-vouchers'))->with('alert', ['type' => 'danger', 'message' => lang('ExamVouchers.mailSelectInvalid')]);
        }

        return $this->renderPreview($ids, []);
    }

    public function resend($id): ResponseInterface|string
    {
        $delivery = (new VoucherEmailDeliveryModel())->resendable((int) $id);
        if ($delivery === null) {
            return $this->response->setStatusCode(404);
        }

        return $this->renderPreview([(int) $delivery['voucher_id']], []);
    }

    public function send(): ResponseInterface|string
    {
        $ids = $this->selection();
        if ($ids === null) {
            return redirect()->to(route_to('admin/exam-vouchers'))->with('alert', ['type' => 'danger', 'message' => lang('ExamVouchers.mailSelectInvalid')]);
        }
        $confirmed = $this->request->getPost('confirm_sent') === '1' ? $this->sentIdsFromPost() : [];
        $templates = new VoucherMailTemplate(service('settings'));
        $template  = $templates->get();
        $config    = config('Queue');
        $db        = db_connect();
        $batch     = bin2hex(random_bytes(16));

        try {
            $queue   = service('queue');
            $queueDb = $config->defaultHandler === 'database' && $config->database['getShared'] ? db_connect($config->database['dbGroup']) : null;
            $errors  = (new VoucherEmailQueue($db, service('encrypter'), $queue, $config, $queueDb, $templates, new VoucherEmailDeliveryModel($db), new VoucherModel($db)))->enqueue($ids, $confirmed, $template, $batch);
        } catch (Throwable $exception) {
            log_message('error', 'Voucher email submission failed: {type}', ['type' => $exception::class]);

            return redirect()->to(route_to('admin/exam-vouchers'))->with('alert', ['type' => 'danger', 'message' => lang('ExamVouchers.mailQueueFailed')]);
        }
        if ($errors !== []) {
            return $this->renderPreview($ids, $errors);
        }

        return redirect()->to(route_to('admin/exam-voucher-mail/deliveries') . '?batch=' . $batch)->with('alert', ['type' => 'success', 'message' => lang('ExamVouchers.mailQueued')]);
    }

    private function selection(): ?array
    {
        $json = $this->request->getPost('selection');
        $ids  = is_string($json) && $json !== '' ? json_decode($json, true) : $this->request->getPost('ids');
        if (! is_array($ids) || ! array_is_list($ids) || $ids === []) {
            return null;
        }
        $result = [];

        foreach ($ids as $id) {
            if ((! is_string($id) && ! is_int($id)) || ! ctype_digit((string) $id) || (int) $id < 1 || (int) $id > 2147483647 || strlen((string) $id) > 10) {
                return null;
            }
            $result[] = (int) $id;
        }

        return count($result) === count(array_unique($result)) ? $result : null;
    }

    private function sentIdsFromPost(): array
    {
        $value = $this->request->getPost('sent_ids');
        $ids   = is_string($value) ? json_decode($value, true) : null;

        return is_array($ids) ? array_values(array_filter($ids, 'is_int')) : [];
    }

    private function renderPreview(array $ids, array $errors): string
    {
        $vouchers        = (new VoucherModel())->previewRecipients($ids);
        $statesByVoucher = (new VoucherEmailDeliveryModel())->statesFor($ids);
        $rows            = [];
        $sentIds         = [];

        foreach ($ids as $id) {
            $voucher = $vouchers[$id] ?? null;
            $states  = $statesByVoucher[$id] ?? ['active' => false, 'sent' => false];
            $active  = $states['active'];
            $sent    = $states['sent'];
            if ($sent) {
                $sentIds[] = $id;
            }
            $rows[] = ['id' => $id, 'voucher' => $voucher, 'active' => $active, 'sent' => $sent, 'error' => $errors[$id] ?? null];
        }

        return view('Modules\ExamVouchers\Views\mail_preview', [
            'me'   => auth()->user(), 'page_title' => lang('ExamVouchers.mailPreview'),
            'rows' => $rows, 'ids' => $ids, 'sentIds' => $sentIds, 'errors' => $errors,
        ]);
    }
}
