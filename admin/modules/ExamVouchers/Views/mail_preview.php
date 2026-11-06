<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th scope="col"><?= esc(lang('ExamVouchers.voucherNumber')) ?></th><th scope="col"><?= esc(lang('ExamVouchers.recipient')) ?></th><th scope="col"><?= esc(lang('Admin.mailStatus')) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>#<?= esc($row['id']) ?></td>
                            <td><?= esc(($row['voucher']['recipient_type'] ?? null) === 'external' ? ($row['voucher']['recipient_name'] . ' (' . $row['voucher']['recipient_email'] . ')') : ($row['voucher']['recipient_id'] ?? '-')) ?></td>
                            <td><?= esc($row['error'] !== null ? lang('ExamVouchers.mailError_' . $row['error']) : ($row['active'] ? lang('ExamVouchers.mailError_active') : ($row['sent'] ? lang('ExamVouchers.mailPreviouslySent') : '-'))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post" action="<?= route_to('admin/exam-voucher-mail/send') ?>" class="card-footer">
            <?= csrf_field() ?>
            <input type="hidden" name="selection" value="<?= esc(json_encode($ids), 'attr') ?>">
            <input type="hidden" name="sent_ids" value="<?= esc(json_encode($sentIds), 'attr') ?>">
            <?php if ($sentIds !== []): ?><label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_sent" value="1" required><span class="form-check-label"><?= esc(lang('ExamVouchers.mailConfirmSent')) ?></span></label><?php endif; ?>
            <button type="submit" class="btn btn-primary"><i class="ti ti-mail-forward me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.mailConfirmSend')) ?></button>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-vouchers') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.cancel')) ?></a>
        </form>
    </div>
<?= $this->endSection() ?>