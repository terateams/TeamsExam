<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Queue\Enums\Status;
use CodeIgniter\Queue\Handlers\DatabaseHandler;
use Config\Services;
use DateTimeImmutable;

class QueueJobs extends BaseController
{
    public function index(): ResponseInterface|string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $config = config('Queue');
        if (($config->handlers[$config->defaultHandler] ?? null) !== DatabaseHandler::class) {
            return $this->response->setStatusCode(501)->setBody(view('Geminus\Admin\Views\queue_jobs', [
                'page_title' => lang('Admin.queueJobs'),
                'supported'  => false,
            ]));
        }

        $db            = db_connect($config->database['dbGroup']);
        $queues        = array_column($db->table('queue_jobs')->select('queue')->distinct()->orderBy('queue')->get()->getResultArray(), 'queue');
        $queue         = (string) $this->request->getGet('queue');
        $status        = (string) $this->request->getGet('status');
        $statuses      = Status::cases();
        $validStatuses = array_map(static fn (Status $item): string => (string) $item->value, $statuses);
        if (! in_array($status, $validStatuses, true)) {
            $status = '';
        }

        $query = $db->table('queue_jobs')->select('id, queue, status, attempts, created_at, available_at');
        if ($queue !== '') {
            $query->where('queue', $queue);
        }
        if ($status !== '') {
            $query->where('status', (int) $status);
        }

        $total = (clone $query)->countAllResults();
        $page  = min(max(1, (int) $this->request->getGet('page')), max(1, (int) ceil($total / 20)));
        $rows  = $query->orderBy('id', 'DESC')->limit(20, ($page - 1) * 20)->get()->getResultArray();

        foreach ($rows as $index => $row) {
            foreach (['created_at', 'available_at'] as $field) {
                $rows[$index][$field] = new DateTimeImmutable('@' . $row[$field]);
            }
        }

        return view('Geminus\Admin\Views\queue_jobs', [
            'page_title'  => lang('Admin.queueJobs'),
            'supported'   => true,
            'me'          => auth()->user(),
            'queues'      => $queues,
            'queue'       => $queue,
            'statuses'    => $statuses,
            'status'      => $status,
            'rows'        => $rows,
            'total'       => $total,
            'currentPage' => $page,
            'perPage'     => 20,
            'pager'       => Services::pager(null, null, false)->makeLinks($page, 20, $total),
        ]);
    }
}
