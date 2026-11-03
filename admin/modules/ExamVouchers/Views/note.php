<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto ms-auto"><a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-vouchers') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.vouchers')) ?></a></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card"><div class="card-body">
        <form method="post" action="<?= route_to('admin/exam-vouchers/note/store', $voucher['id']) ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="note"><?= esc(lang('ExamVouchers.voucherNote')) ?></label>
                <input class="form-control<?= session('voucher_note_error') ? ' is-invalid' : '' ?>" id="note" name="note" maxlength="255" value="<?= esc(session('voucher_note_input') ?? $voucher['note'] ?? '', 'attr') ?>" aria-describedby="note-hint">
                <div class="form-hint" id="note-hint"><?= esc(lang('ExamVouchers.voucherNoteHint')) ?></div>
                <?php if (session('voucher_note_error')): ?><div class="invalid-feedback d-block"><?= esc(session('voucher_note_error')) ?></div><?php endif ?>
            </div>
            <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.save')) ?></button>
        </form>
    </div></div>
<?= $this->endSection() ?>
