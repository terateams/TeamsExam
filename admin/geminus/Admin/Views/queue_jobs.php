<?php

use CodeIgniter\Queue\Enums\Status;

?><?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div>
        <h2 class="page-title"><?= esc($page_title) ?></h2>
        <div class="text-secondary mt-1"><?= esc(lang('Admin.queueJobsDescription')) ?></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <?php if (! $supported): ?>
        <div class="alert alert-warning" role="alert"><?= esc(lang('Admin.queueJobsUnsupported')) ?></div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <?php
                $queueOptions = ['' => lang('Admin.queueAll')];

        foreach ($queues as $name) {
            $queueOptions[$name] = $name;
        }
        $statusOptions = ['' => lang('Admin.mailAllStatuses')];

        foreach ($statuses as $item) {
            $statusOptions[(string) $item->value] = lang('Admin.queueStatus_' . strtolower($item->name));
        }
        ?>
                <?= view_cell('Geminus\Admin\Cells\FilterBarCell', [
            'action'      => route_to('admin/queue-jobs'), 'clearUrl' => route_to('admin/queue-jobs'),
            'submitLabel' => lang('Admin.userFilter'), 'clearLabel' => lang('Admin.userClear'),
            'fields'      => [
                ['type' => 'select', 'id' => 'queue-name', 'name' => 'queue', 'label' => lang('Admin.queueName'), 'value' => $queue, 'class' => 'col-12 col-sm-4 col-lg-3', 'options' => $queueOptions],
                ['type' => 'select', 'id' => 'queue-status', 'name' => 'status', 'label' => lang('Admin.mailStatus'), 'value' => $status, 'class' => 'col-12 col-sm-4 col-lg-3', 'options' => $statusOptions],
            ],
        ]) ?>
            </div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead><tr><th scope="col">#</th><th scope="col"><?= esc(lang('Admin.queueName')) ?></th><th scope="col"><?= esc(lang('Admin.mailStatus')) ?></th><th scope="col"><?= esc(lang('Admin.mailAttempts')) ?></th><th scope="col"><?= esc(lang('Admin.mailCreated')) ?></th><th scope="col"><?= esc(lang('Admin.mailAvailable')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $jobStatus = Status::tryFrom((int) $row['status']); ?>
                            <tr>
                                <td><?= esc($row['id']) ?></td>
                                <td><?= esc($row['queue']) ?></td>
                                <td><?= esc($jobStatus === null ? lang('Admin.mailJob_unknown') : lang('Admin.queueStatus_' . strtolower($jobStatus->name))) ?></td>
                                <td><?= esc($row['attempts']) ?></td>
                                <td class="text-nowrap"><?= esc($me->formatDateTime($row['created_at'])) ?></td>
                                <td class="text-nowrap"><?= esc($me->formatDateTime($row['available_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?><tr><td colspan="6" class="text-center py-4">
                            <?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang('Admin.mailNoRecords'), 'filtered' => $queue !== '' || $status !== '', 'clearUrl' => route_to('admin/queue-jobs'), 'clearLabel' => lang('Admin.userClear')]) ?>
                        </td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager, 'total' => $total, 'currentPage' => $currentPage, 'perPage' => $perPage, 'totalLabel' => lang('Admin.userTotal')]) ?>
            </div>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>