<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-auto ms-auto btn-list">
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-voucher-batches/show', $batch['id']) ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.batch')) ?></a>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <form action="<?= route_to('admin/exam-vouchers/import/store', $batch['id']) ?>" method="post" enctype="multipart/form-data" class="card mb-4">
        <?= csrf_field() ?>
        <div class="card-body">
            <p><?= esc(lang('ExamVouchers.identifier')) ?>: <?= esc($batch['identifier']) ?></p>
            <label class="form-label required" for="voucher-csv"><?= esc(lang('Admin.attachmentFile')) ?></label>
            <input id="voucher-csv" name="file" type="file" accept=".csv" required class="form-control<?= session('errors.file') ? ' is-invalid' : '' ?>" aria-describedby="voucher-csv-hint<?= session('errors.file') ? ' voucher-csv-error' : '' ?>"<?= session('errors.file') ? ' aria-invalid="true"' : '' ?>>
            <?php if (session('errors.file')): ?><div id="voucher-csv-error" class="invalid-feedback"><?= esc(session('errors.file')) ?></div><?php endif; ?>
            <div id="voucher-csv-hint" class="form-hint"><?= esc(lang('ExamVouchers.csvHint')) ?></div>
        </div>
        <div class="card-footer">
            <button class="btn btn-primary" type="submit"><i class="ti ti-upload me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.import')) ?></button>
        </div>
    </form>
    <?php if ($report !== []): ?>
        <?= view_cell('Geminus\Admin\Cells\ImportReportCell', [
            'title'        => lang('ExamVouchers.report'), 'rowLabel' => lang('Admin.userRow'),
            'resultLabel'  => lang('Admin.userResult'), 'reasonLabel' => lang('Admin.userReason'),
            'columns'      => [], 'rows' => $report,
            'resultLabels' => ['created' => lang('ExamVouchers.created'), 'error' => lang('Admin.userResult_error')],
            'reasonLabels' => [
                ''               => '', 'invalid' => lang('ExamVouchers.invalidRow'), 'processing' => lang('ExamVouchers.processing'),
                'invalid_code'   => lang('ExamVouchers.invalidCode'), 'invalid_exam' => lang('ExamVouchers.invalidExam'),
                'invalid_region' => lang('ExamVouchers.invalidRegion'), 'invalid_date' => lang('ExamVouchers.invalidDate'),
                'duplicate'      => lang('ExamVouchers.duplicateCode'), 'not_imported' => lang('ExamVouchers.notImported'),
            ],
        ]) ?>
    <?php endif; ?>
<?= $this->endSection() ?>
