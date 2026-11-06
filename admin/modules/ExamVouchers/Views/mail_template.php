<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card">
        <form id="voucher-mail-template-form" method="post" action="<?= route_to('admin/exam-voucher-mail/template/update') ?>">
            <?= csrf_field() ?>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required" for="template-subject"><?= esc(lang('Admin.mailSubject')) ?></label>
                    <input class="form-control<?= session('template_errors.subject') ? ' is-invalid' : '' ?>" id="template-subject" name="subject" maxlength="255" required value="<?= esc(old('subject', $template['subject']), 'attr') ?>">
                    <?php if (session('template_errors.subject')): ?><div class="invalid-feedback"><?= esc(session('template_errors.subject')) ?></div><?php endif; ?>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-lg-6">
                        <div class="card h-100">
                            <div class="card-header"><label class="form-label required card-title mb-0" for="template-body"><?= esc(lang('Admin.mailHtmlBody')) ?></label></div>
                            <div class="card-body">
                                <textarea class="form-control<?= session('template_errors.body') ? ' is-invalid' : '' ?>" id="template-body" name="body" rows="15" maxlength="10000" required aria-describedby="template-variables"><?= esc(old('body', $template['body'])) ?></textarea>
                                <?php if (session('template_errors.body')): ?><div class="invalid-feedback"><?= esc(session('template_errors.body')) ?></div><?php endif; ?>
                                <div class="form-hint" id="template-variables">{name}, {code}, {exam}, {region}, {expiresOn}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card h-100">
                            <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.mailPreview')) ?></h3></div>
                            <div class="card-body">
                                <iframe id="template-preview" title="<?= esc(lang('Admin.mailPreview'), 'attr') ?>" class="w-100 border rounded" style="height: 360px" sandbox="" referrerpolicy="no-referrer"></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="card-footer d-flex align-items-center gap-2">
            <button form="voucher-mail-template-form" type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('Admin.mailTemplateSave')) ?></button>
            <form method="post" action="<?= route_to('admin/exam-voucher-mail/template/reset') ?>" class="ms-auto">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-restore me-1" aria-hidden="true"></i><?= esc(lang('Admin.mailTemplateRestore')) ?></button>
            </form>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <script>
        const bodyInput = document.getElementById('template-body');
        const preview = document.getElementById('template-preview');
        const samples = { name: 'Example User', code: 'EXAMPLE-CODE', exam: 'Example Exam', region: 'Example Region', expiresOn: '2030-01-01' };
        const updatePreview = () => {
            const markup = bodyInput.value.replace(/\{([a-zA-Z][a-zA-Z0-9]*)\}/g, (token, name) => Object.hasOwn(samples, name) ? samples[name] : token);
            preview.srcdoc = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:">' + markup;
        };
        bodyInput.addEventListener('input', updatePreview);
        updatePreview();
    </script>
<?= $this->endSection() ?>