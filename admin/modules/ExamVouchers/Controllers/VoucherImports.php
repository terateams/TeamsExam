<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourcePresenter;
use InvalidArgumentException;
use Modules\ExamVouchers\Libraries\VoucherCsvImport;
use Modules\ExamVouchers\Models\BatchModel;
use Psr\Log\LoggerInterface;
use Throwable;

class VoucherImports extends ResourcePresenter
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        service('language')->setLocale($request->getLocale());
    }

    public function form($id): ResponseInterface|string
    {
        $batch = (new BatchModel())->find((int) $id);
        if ($batch === null) {
            return $this->response->setStatusCode(404);
        }
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Modules\ExamVouchers\Views\import', [
            'me'     => auth()->user(), 'page_title' => lang('ExamVouchers.import'), 'batch' => $batch,
            'report' => session('voucher_import_report') ?? [],
        ]);
    }

    public function import($id): RedirectResponse|ResponseInterface
    {
        if ((new BatchModel())->find((int) $id) === null) {
            return $this->response->setStatusCode(404);
        }
        $back = route_to('admin/exam-vouchers/import', $id);
        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid() || $file->getSize() > 1024 * 1024
                           || strtolower($file->getClientExtension()) !== 'csv') {
            return redirect()->to($back)->with('errors', ['file' => lang('ExamVouchers.invalidCsv')]);
        }
        if (! in_array($file->getMimeType(), ['text/plain', 'text/csv', 'application/vnd.ms-excel'], true)) {
            return redirect()->to($back)->with('errors', ['file' => lang('ExamVouchers.invalidCsv')]);
        }
        $stream = fopen($file->getTempName(), 'rb');
        if ($stream === false) {
            return redirect()->to($back)->with('errors', ['file' => lang('ExamVouchers.invalidCsv')]);
        }

        try {
            $config = config('Encryption');
            $report = (new VoucherCsvImport(db_connect(), service('encrypter'), $config->key))->import($stream, (int) $id);
        } catch (InvalidArgumentException) {
            return redirect()->to($back)->with('errors', ['file' => lang('ExamVouchers.invalidCsv')]);
        } catch (Throwable $exception) {
            log_message('error', 'Voucher import failed: {type}; cause: {cause}', [
                'type' => $exception::class, 'cause' => $exception->getPrevious() === null ? 'none' : $exception->getPrevious()::class,
            ]);

            return redirect()->to($back)->with('alert', ['type' => 'danger', 'message' => lang('ExamVouchers.importFailed')]);
        } finally {
            fclose($stream);
        }
        $failed = array_filter($report, static fn (array $row): bool => $row['result'] === 'error') !== [];

        return redirect()->to($back)->with('voucher_import_report', $report)
            ->with('alert', ['type' => $failed ? 'danger' : 'success', 'message' => lang($failed ? 'ExamVouchers.importRejected' : 'ExamVouchers.imported')]);
    }
}
