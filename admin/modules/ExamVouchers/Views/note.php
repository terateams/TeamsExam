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
                <form method="post" action="<?= route_to('admin/exam-vouchers/note/store', $voucher['id']) ?>">
                    <?= csrf_field() ?>
                    <div class="card-body">
                        <label class="form-label" for="note"><?= esc(lang('ExamVouchers.voucherNote')) ?></label>
                        <input class="form-control<?= session('voucher_note_error') ? ' is-invalid' : '' ?>" id="note" name="note" maxlength="255" value="<?= esc(session('voucher_note_input') ?? $voucher['note'] ?? '', 'attr') ?>" aria-describedby="note-hint<?= session('voucher_note_error') ? ' note-error' : '' ?>"<?= session('voucher_note_error') ? ' aria-invalid="true"' : '' ?>>
                        <div class="form-hint" id="note-hint"><?= esc(lang('ExamVouchers.voucherNoteHint')) ?></div>
                        <?php if (session('voucher_note_error')): ?><div class="invalid-feedback d-block" id="note-error"><?= esc(session('voucher_note_error')) ?></div><?php endif ?>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.save')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>
