<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('head') ?>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/libs/vanilla-calendar-pro/index.js"></script>
<?= $this->endSection() ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10 col-xxl-8">
            <form class="card" method="post" action="<?= esc($formAction, 'attr') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach (['identifier', 'issuer'] as $field): ?>
                            <div class="col-12 col-md-6">
                                <label class="form-label required" for="batch-<?= esc($field, 'attr') ?>"><?= esc(lang('ExamVouchers.' . $field)) ?></label>
                                <input id="batch-<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>" class="form-control<?= session('errors.' . $field) ? ' is-invalid' : '' ?>" value="<?= esc(old($field, $batch[$field] ?? ($field === 'identifier' ? $suggestedIdentifier : '') ?? '', false), 'attr') ?>" maxlength="150" required>
                                <?php if (session('errors.' . $field)): ?><div class="invalid-feedback"><?= esc(session('errors.' . $field)) ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-12 col-md-6">
                            <?= view_cell('Geminus\Admin\Cells\DateFieldCell', ['inputId' => 'batch-purchased-on', 'name' => 'purchased_on', 'label' => lang('ExamVouchers.purchasedOn'), 'value' => (string) old('purchased_on', $batch['purchased_on'] ?? '', false), 'error' => (string) session('errors.purchased_on')]) ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer btn-list">
                    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.save')) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-voucher-batches') ?>"><i class="ti ti-x me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.cancel')) ?></a>
                </div>
            </form>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <script>
        new tabler.Datepicker(document.getElementById('batch-purchased-on'), {
            locale: document.documentElement.lang,
            dateFormat: (date) => [
                date.getFullYear(),
                String(date.getMonth() + 1).padStart(2, '0'),
                String(date.getDate()).padStart(2, '0'),
            ].join('-'),
        });
    </script>
<?= $this->endSection() ?>
