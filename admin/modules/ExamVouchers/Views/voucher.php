<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto ms-auto"><a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-vouchers') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.vouchers')) ?></a></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card card-lg">
        <div class="card-body">
            <dl class="mb-0">
                <div class="card-title">
                    <i class="ti ti-ticket me-2" aria-hidden="true"></i><dt class="d-inline"><?= esc(lang('ExamVouchers.voucherNumber')) ?></dt>
                    <dd class="d-inline">#<?= esc($voucher['id']) ?></dd>
                </div>
                <div>
                    <dt class="text-secondary"><?= esc(lang('ExamVouchers.exam')) ?></dt>
                    <dd class="h2 text-break mb-4"><?= esc(($voucher['exam'] ?? '') !== '' ? $voucher['exam'] : '—') ?></dd>
                </div>

                <div class="bg-body-tertiary border rounded p-3 mb-4">
                    <dt class="text-secondary"><?= esc(lang('ExamVouchers.code')) ?></dt>
                    <dd class="font-monospace fs-3 text-break mb-0"><?= esc($code) ?></dd>
                </div>
            </dl>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <dl class="mb-0">
                        <dt class="text-secondary"><?= esc(lang('ExamVouchers.expiresOn')) ?></dt>
                        <dd class="mb-0"><?= esc($voucher['expires_on']) ?></dd>
                    </dl>
                </div>
                <div class="col-12 col-md-6">
                    <dl class="mb-0">
                        <dt class="text-secondary"><?= esc(lang('ExamVouchers.region')) ?></dt>
                        <dd class="text-break mb-0"><?= esc(($voucher['region'] ?? '') !== '' ? $voucher['region'] : '—') ?></dd>
                    </dl>
                </div>
                <div class="col-12 col-md-6">
                    <dl class="mb-0">
                        <dt class="text-secondary"><?= esc(lang('ExamVouchers.issuer')) ?></dt>
                        <dd class="text-break mb-0"><?= esc($voucher['issuer']) ?></dd>
                    </dl>
                </div>
                <div class="col-12 col-md-6">
                    <dl class="mb-0">
                        <dt class="text-secondary"><?= esc(lang('ExamVouchers.identifier')) ?></dt>
                        <dd class="text-break mb-0"><?= esc($voucher['identifier']) ?></dd>
                    </dl>
                </div>
                <?php if ($voucher['recipient_type'] !== null): ?>
                    <div class="col-12 col-md-6">
                        <dl class="mb-0">
                            <dt class="text-secondary"><?= esc(lang('ExamVouchers.recipient')) ?></dt>
                            <dd class="text-break mb-0"><?= esc($voucher['recipient_type'] === 'external' ? $voucher['recipient_name'] : ($voucher['recipient_username'] ?? '#' . $voucher['recipient_id'])) ?></dd>
                            <?php if ($voucher['recipient_type'] === 'external'): ?><dd class="text-break text-secondary mb-0"><?= esc($voucher['recipient_email']) ?></dd><?php endif ?>
                        </dl>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>
