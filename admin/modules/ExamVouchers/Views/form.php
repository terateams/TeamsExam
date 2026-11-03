<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <form class="card" method="post" action="<?= esc($formAction, 'attr') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <?php foreach (['identifier', 'issuer'] as $field): ?>
                <div class="mb-3">
                    <label class="form-label required" for="batch-<?= esc($field, 'attr') ?>"><?= esc(lang('ExamVouchers.' . $field)) ?></label>
                    <input id="batch-<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>" class="form-control<?= session('errors.' . $field) ? ' is-invalid' : '' ?>" value="<?= esc(old($field, $batch[$field] ?? '', false), 'attr') ?>" maxlength="150" required>
                    <?php if (session('errors.' . $field)): ?><div class="invalid-feedback"><?= esc(session('errors.' . $field)) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.save')) ?></button>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-voucher-batches') ?>"><i class="ti ti-x me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.cancel')) ?></a>
        </div>
    </form>
<?= $this->endSection() ?>
