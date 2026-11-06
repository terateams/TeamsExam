<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto ms-auto">
            <a class="btn btn-outline-secondary" href="<?= $operations ? route_to('admin/mail/deliveries') : route_to('admin/exam-vouchers') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.cancel')) ?></a>
            <?php if ($batch !== ''): ?><a class="btn btn-outline-secondary" href="<?= $operations ? route_to('admin/mail/voucher-deliveries') : route_to('admin/exam-voucher-mail/deliveries') ?>"><i class="ti ti-list me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.mailAllDeliveries')) ?></a><?php endif; ?>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th scope="col">#</th><th scope="col"><?= esc(lang('ExamVouchers.voucherNumber')) ?></th><th scope="col"><?= esc(lang('Admin.mailRecipient')) ?></th><th scope="col"><?= esc(lang('Admin.mailSubject')) ?></th><th scope="col"><?= esc(lang('Admin.mailStatus')) ?></th><th scope="col"><?= esc(lang('Admin.mailAttempts')) ?></th><th scope="col"><?= esc(lang('Admin.mailCreated')) ?></th><th scope="col"><?= esc(lang('Admin.mailProcessed')) ?></th><th scope="col"><?= esc(lang('Admin.mailFailureReason')) ?></th><?php if (! $operations): ?><th scope="col"><?= esc(lang('Admin.userActions')) ?></th><?php endif; ?></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= esc($row['id']) ?></td><td>#<?= esc($row['voucher_id']) ?></td>
                            <td><?= esc($row['recipient']) ?></td><td><?= esc($row['subject']) ?></td>
                            <td><span class="badge <?= match ($row['status']) {
                                'sent' => 'bg-success-lt', 'failed' => 'bg-danger-lt', default => 'bg-secondary-lt'
                            } ?>"><?= esc(lang('Admin.mailStatus_' . $row['status'])) ?></span></td>
                            <td><?= esc($row['attempts']) ?></td><td><?= esc($row['created_at']) ?></td><td><?= esc($row['processed_at'] ?? '-') ?></td><td><?= esc($row['failure_reason'] ?? '-') ?></td>
                            <?php if (! $operations): ?><td><?php if (in_array($row['status'], ['sent', 'failed'], true)): ?><form method="post" action="<?= route_to('admin/exam-voucher-mail/resend', $row['id']) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?= esc(lang('ExamVouchers.mailResend'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-title="<?= esc(lang('ExamVouchers.mailResend'), 'attr') ?>"><i class="ti ti-mail-forward" aria-hidden="true"></i></button></form><?php endif; ?></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?><tr><td colspan="<?= $operations ? 9 : 10 ?>"><?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang('Admin.mailNoRecords')]) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager, 'total' => $total, 'currentPage' => $currentPage, 'perPage' => $perPage, 'totalLabel' => lang('Admin.userTotal')]) ?>
        </div>
    </div>
<?= $this->endSection() ?>

<?php if (! $operations && $batch !== ''): ?>
<?= $this->section('javascript') ?><script>sessionStorage.removeItem('voucher-mail-selection');</script><?= $this->endSection() ?>
<?php endif; ?>