<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use DateTimeImmutable;
use DateTimeZone;

class EmailQueue extends BaseController
{
    public function index(): ResponseInterface|string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');

        if ($this->request->getGet('view') === 'queue') {
            return redirect()->to(site_url($this->request->getLocale() . '/admin/queue-jobs'));
        }

        $page     = max(1, (int) $this->request->getGet('page'));
        $db       = db_connect();
        $statuses = ['queued', 'sent', 'failed'];
        $status   = in_array($this->request->getGet('status'), $statuses, true) ? $this->request->getGet('status') : '';

        $query = $db->table('email_delivery_logs')
            ->select('id, job_id, recipient, subject, status, attempts, created_at, processed_at, failure_reason');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $recipient = trim((string) $this->request->getGet('recipient'));
        if ($recipient !== '') {
            $query->like('recipient', mb_substr($recipient, 0, 254), 'both', null, true);
        }

        $total = (clone $query)->countAllResults();
        $page  = min($page, max(1, (int) ceil($total / 20)));
        $rows  = $query->orderBy('id', 'DESC')->limit(20, ($page - 1) * 20)->get()->getResultArray();
        $utc   = new DateTimeZone('UTC');

        foreach ($rows as $index => $row) {
            foreach (['created_at', 'processed_at'] as $field) {
                $rows[$index][$field] = $row[$field] === null ? null : new DateTimeImmutable($row[$field], $utc);
            }
        }

        return view('Geminus\Admin\Views\email_queue', [
            'me'          => auth()->user(),
            'page_title'  => lang('Admin.mailDeliveries'),
            'rows'        => $rows,
            'total'       => $total,
            'currentPage' => $page,
            'perPage'     => 20,
            'pager'       => Services::pager(null, null, false)->makeLinks($page, 20, $total),
            'statuses'    => $statuses,
            'status'      => $status,
            'recipient'   => $recipient,
        ]);
    }
}
