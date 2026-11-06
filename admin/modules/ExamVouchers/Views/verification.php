<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto ms-auto btn-list"><a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-vouchers') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.vouchers')) ?></a></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xxl-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= esc(lang('ExamVouchers.voucherNumber')) ?>: #<?= esc($voucher['id']) ?></h3>
                </div>
                <form method="post" action="<?= route_to('admin/exam-vouchers/verification/store', $voucher['id']) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="version" value="<?= esc($voucher['verification_version'], 'attr') ?>">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="verified"><?= esc(lang('ExamVouchers.verification')) ?></label>
                            <select class="form-select" id="verified" name="verified" aria-describedby="verification-hint">
                                <option value="0"<?= $voucher['verification_note'] === null ? ' selected' : '' ?>><?= esc(lang('ExamVouchers.notVerified')) ?></option>
                                <option value="1"<?= $voucher['verification_note'] !== null ? ' selected' : '' ?>><?= esc(lang('ExamVouchers.verified')) ?></option>
                            </select>
                            <div class="form-hint" id="verification-hint"><?= esc(lang('ExamVouchers.verificationHint')) ?></div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="note"><?= esc(lang('ExamVouchers.verificationNote')) ?></label>
                            <input class="form-control<?= session('verification_error') ? ' is-invalid' : '' ?>" id="note" name="note" maxlength="255" value="<?= esc($voucher['verification_note'] ?? '', 'attr') ?>" aria-describedby="note-hint<?= session('verification_error') ? ' note-error' : '' ?>"<?= session('verification_error') ? ' aria-invalid="true"' : '' ?>>
                            <div class="form-hint" id="note-hint"><?= esc(lang('ExamVouchers.noteHint')) ?></div>
                            <?php if (session('verification_error')): ?><div class="invalid-feedback d-block" id="note-error"><?= esc(session('verification_error')) ?></div><?php endif ?>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.save')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>
