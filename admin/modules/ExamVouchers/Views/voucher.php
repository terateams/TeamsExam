<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto ms-auto"><a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-vouchers') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.vouchers')) ?></a></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card"><div class="card-body">
        <dl>
            <dt><?= esc(lang('ExamVouchers.identifier')) ?></dt><dd class="text-break"><?= esc($voucher['identifier']) ?></dd>
            <dt><?= esc(lang('ExamVouchers.issuer')) ?></dt><dd class="text-break"><?= esc($voucher['issuer']) ?></dd>
            <dt><?= esc(lang('ExamVouchers.exam')) ?></dt><dd class="text-break"><?= esc($voucher['exam'] ?? '') ?></dd>
            <dt><?= esc(lang('ExamVouchers.region')) ?></dt><dd class="text-break"><?= esc($voucher['region'] ?? '') ?></dd>
            <dt><?= esc(lang('ExamVouchers.expiresOn')) ?></dt><dd><?= esc($voucher['expires_on']) ?></dd>
            <dt><?= esc(lang('ExamVouchers.code')) ?></dt><dd class="text-break"><?= esc($code) ?></dd>
        </dl>
    </div></div>
<?= $this->endSection() ?>
