<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto btn-list">
            <a class="btn btn-primary" href="<?= route_to('admin/exam-vouchers/import', $batch['id']) ?>"><i class="ti ti-upload me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.import')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-vouchers') ?>?batch=<?= (int) $batch['id'] ?>"><i class="ti ti-ticket me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.vouchers')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-voucher-batches/edit', $batch['id']) ?>"><i class="ti ti-edit me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.edit')) ?></a>
            <span class="d-inline-flex" data-bs-toggle="tooltip" data-bs-title="<?= esc(lang('ExamVouchers.delete'), 'attr') ?>"><button class="btn btn-outline-danger btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#batch-delete" aria-label="<?= esc(lang('ExamVouchers.delete'), 'attr') ?>"><i class="ti ti-trash" aria-hidden="true"></i></button></span>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-voucher-batches') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.title')) ?></a>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="modal modal-blur fade" id="batch-delete" tabindex="-1" aria-labelledby="batch-delete-title" aria-describedby="batch-delete-description" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <form class="modal-content" method="post" action="<?= route_to('admin/exam-voucher-batches/delete', $batch['id']) ?>">
                <?= csrf_field() ?>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('ExamVouchers.cancel'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-title="<?= esc(lang('ExamVouchers.cancel'), 'attr') ?>"></button>
                <div class="modal-status bg-danger"></div>
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-triangle text-danger fs-1 mb-2 d-block" aria-hidden="true"></i>
                    <h3 id="batch-delete-title"><?= esc(lang('ExamVouchers.deleteConfirm')) ?></h3>
                    <p class="text-break fw-semibold"><?= esc($batch['identifier']) ?></p>
                    <div class="text-secondary" id="batch-delete-description"><?= esc(lang('ExamVouchers.deleteWarning')) ?></div>
                </div>
                <div class="modal-footer"><div class="w-100"><div class="row g-2">
                    <div class="col"><button type="button" class="btn w-100" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.cancel')) ?></button></div>
                    <div class="col"><button type="submit" class="btn btn-danger w-100"><i class="ti ti-trash me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.delete')) ?></button></div>
                </div></div></div>
            </form>
        </div>
    </div>
    <div class="card"><div class="card-body">
        <dl><dt><?= esc(lang('ExamVouchers.identifier')) ?></dt><dd class="text-break"><?= esc($batch['identifier']) ?></dd>
        <dt><?= esc(lang('ExamVouchers.issuer')) ?></dt><dd class="text-break"><?= esc($batch['issuer']) ?></dd>
        <dt><?= esc(lang('ExamVouchers.purchasedOn')) ?></dt><dd><?= esc($batch['purchased_on'] ?? '-') ?></dd></dl>
    </div></div>
<?= $this->endSection() ?>
