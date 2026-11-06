<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourcePresenter;
use Modules\ExamVouchers\Libraries\VoucherMailTemplate;
use Psr\Log\LoggerInterface;

class VoucherMailTemplates extends ResourcePresenter
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        service('language')->setLocale($request->getLocale());
    }

    public function edit($id = null): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Modules\ExamVouchers\Views\mail_template', [
            'me'       => auth()->user(), 'page_title' => lang('ExamVouchers.mailTemplate'),
            'template' => (new VoucherMailTemplate(service('settings')))->get(),
        ]);
    }

    public function update($id = null): RedirectResponse
    {
        $validation = service('validation');
        $validation->setRules(['subject' => 'required|max_length[255]', 'body' => 'required|max_length[10000]']);
        $template = new VoucherMailTemplate(service('settings'));
        $url      = route_to('admin/exam-voucher-mail/template');
        if (! $validation->run($this->request->getPost())) {
            return redirect()->to($url)->withInput()->with('template_errors', $validation->getErrors());
        }
        $data = $validation->getValidated();
        if (! $template->valid($data['subject'], $data['body'])) {
            return redirect()->to($url)->withInput()->with('template_errors', ['body' => lang('ExamVouchers.mailTemplateInvalid')]);
        }
        $template->save($data['subject'], $data['body']);

        return redirect()->to($url)->with('alert', ['type' => 'success', 'message' => lang('ExamVouchers.mailTemplateSaved')]);
    }
}
