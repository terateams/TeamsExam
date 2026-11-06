<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto btn-list">
            <a class="btn btn-primary" href="<?= route_to('admin/exam-vouchers/import', $batch['id']) ?>"><i class="ti ti-upload me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.import')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-vouchers') ?>?batch=<?= (int) $batch['id'] ?>"><i class="ti ti-ticket me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.vouchers')) ?></a>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= esc(lang('Admin.userActions')) ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?= route_to('admin/exam-voucher-batches/edit', $batch['id']) ?>"><i class="ti ti-edit me-2" aria-hidden="true"></i><?= esc(lang('ExamVouchers.edit')) ?></a>
                    <button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#batch-delete"><i class="ti ti-trash me-2" aria-hidden="true"></i><?= esc(lang('ExamVouchers.delete')) ?></button>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="<?= route_to('admin/exam-voucher-batches') ?>"><i class="ti ti-arrow-left me-2" aria-hidden="true"></i><?= esc(lang('ExamVouchers.title')) ?></a>
                </div>
            </div>
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
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10 col-xxl-8">
            <div class="card card-lg">
                <div class="card-header">
                    <h3 class="card-title text-break"><?= esc($batch['identifier']) ?></h3>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-12 col-md-6">
                            <dl class="mb-0">
                                <dt class="text-secondary"><?= esc(lang('ExamVouchers.issuer')) ?></dt>
                                <dd class="mb-0 text-break"><?= esc($batch['issuer']) ?></dd>
                            </dl>
                        </div>
                        <div class="col-12 col-md-6">
                            <dl class="mb-0">
                                <dt class="text-secondary"><?= esc(lang('ExamVouchers.purchasedOn')) ?></dt>
                                <dd class="mb-0"><?= esc($batch['purchased_on'] ?? '-') ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>
