<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card">
            <div class="card-body">
                <?= view_cell('Geminus\Admin\Cells\FilterBarCell', [
                    'action'      => route_to('admin/mail/deliveries'), 'clearUrl' => route_to('admin/mail/deliveries'),
                    'submitLabel' => lang('Admin.userFilter'), 'clearLabel' => lang('Admin.userClear'),
                    'fields'      => [
                        ['type' => 'select', 'id' => 'mail-status', 'name' => 'status', 'label' => lang('Admin.mailStatus'), 'value' => $status, 'class' => 'col-12 col-sm-4 col-lg-3', 'options' => ['' => lang('Admin.mailAllStatuses')] + array_combine($statuses, array_map(static fn (string $option): string => lang('Admin.mailStatus_' . $option), $statuses))],
                        ['id' => 'mail-recipient', 'name' => 'recipient', 'label' => lang('Admin.mailRecipient'), 'value' => $recipient, 'maxlength' => 254, 'class' => 'col-12 col-sm-5 col-lg-4'],
                    ],
                ]) ?>
            </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                    <thead><tr><th scope="col">#</th><th scope="col"><?= esc(lang('Admin.mailRecipient')) ?></th><th scope="col"><?= esc(lang('Admin.mailSubject')) ?></th><th scope="col"><?= esc(lang('Admin.mailStatus')) ?></th><th scope="col"><?= esc(lang('Admin.mailAttempts')) ?></th><th scope="col"><?= esc(lang('Admin.mailCreated')) ?></th><th scope="col"><?= esc(lang('Admin.mailProcessed')) ?></th><th scope="col"><?= esc(lang('Admin.mailFailureReason')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= esc($row['id']) ?></td>
                                <td class="text-wrap"><?= esc($row['recipient']) ?></td>
                                <td class="text-wrap"><?= esc($row['subject']) ?></td>
                                <td><span class="badge <?= match ($row['status']) {
                                    'sent' => 'bg-success-lt', 'failed' => 'bg-danger-lt', default => 'bg-secondary-lt',
                                } ?>"><?= esc(lang('Admin.mailStatus_' . $row['status'])) ?></span></td>
                                <td><?= esc($row['attempts']) ?></td>
                                <td class="text-nowrap"><?= esc($me->formatDateTime($row['created_at'])) ?></td>
                                <td class="text-nowrap"><?= esc($me->formatDateTime($row['processed_at']) ?? '-') ?></td>
                                <td class="text-wrap"><?= esc($row['failure_reason'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?><tr><td colspan="8" class="text-center py-4">
                            <?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang('Admin.mailNoRecords'), 'filtered' => $status !== '' || $recipient !== '', 'clearUrl' => route_to('admin/mail/deliveries'), 'clearLabel' => lang('Admin.userClear')]) ?>
                        </td></tr><?php endif; ?>
                    </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager, 'total' => $total, 'currentPage' => $currentPage, 'perPage' => $perPage, 'totalLabel' => lang('Admin.userTotal')]) ?>
        </div>
    </div>
<?= $this->endSection() ?>