<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto ms-auto"><a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-vouchers') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.vouchers')) ?></a></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card"><div class="card-body">
        <p><?= esc(lang('ExamVouchers.voucherNumber')) ?>: #<?= esc($voucher['id']) ?></p>
        <?php if ($voucher['recipient_id'] !== null): ?>
            <p><?= esc(lang('ExamVouchers.alreadyAssigned')) ?></p>
        <?php else: ?>
            <form method="get" action="<?= route_to('admin/exam-vouchers/assign', $voucher['id']) ?>">
                <div class="mb-3">
                    <label class="form-label" for="username"><?= esc(lang('ExamVouchers.username')) ?></label>
                    <input class="form-control" id="username" name="username" value="<?= esc($search, 'attr') ?>" maxlength="30" required aria-describedby="username-hint">
                    <div class="form-hint" id="username-hint"><?= esc(lang('ExamVouchers.recipientHint')) ?></div>
                </div>
                <button class="btn btn-outline-primary" type="submit"><i class="ti ti-search me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.search')) ?></button>
            </form>
            <?php if ($user !== null): ?>
                <form method="post" action="<?= route_to('admin/exam-vouchers/assign/store', $voucher['id']) ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="recipient_id" value="<?= esc($user['id'], 'attr') ?>">
                    <p><?= esc(lang('ExamVouchers.confirmRecipient', [$user['username']])) ?></p>
                    <button class="btn btn-primary" type="submit"><i class="ti ti-user-check me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.confirmAssign')) ?></button>
                </form>
            <?php elseif ($search !== ''): ?>
                <p class="mt-3"><?= esc(lang('ExamVouchers.userNotFound')) ?></p>
            <?php endif ?>
        <?php endif ?>
    </div></div>
<?= $this->endSection() ?>
