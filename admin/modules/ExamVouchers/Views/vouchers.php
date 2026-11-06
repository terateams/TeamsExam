<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto ms-auto">
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-voucher-mail/template') ?>"><i class="ti ti-template me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.mailTemplate')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-voucher-mail/deliveries') ?>"><i class="ti ti-mail me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.mailDeliveries')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-voucher-batches') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.title')) ?></a>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h3><?= esc(lang('ExamVouchers.inventory')) ?></h3>
    <p class="text-secondary"><?= esc(lang('ExamVouchers.inventoryHint')) ?></p>
    <div class="row row-deck row-cards mb-3">
        <?php foreach (['unassigned' => 'unassigned', 'assigned' => 'assignedCount', 'verified' => 'verified'] as $key => $label): ?>
            <div class="col-sm-6 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader"><?= esc(lang('ExamVouchers.' . $label)) ?></div>
                        <div class="h1 mb-0" id="inventory-<?= esc($key, 'attr') ?>"><?= esc($inventory[$key]) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
    <form id="voucher-mail-form" method="post" action="<?= route_to('admin/exam-voucher-mail/preview') ?>" class="mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="selection" id="voucher-mail-selection" value="">
        <button type="submit" class="btn btn-primary"><i class="ti ti-mail-forward me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.mailSendSelected')) ?></button>
        <button type="button" class="btn btn-outline-secondary" id="voucher-mail-clear"><i class="ti ti-x me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.mailClearSelection')) ?></button>
        <span class="text-secondary ms-2" id="voucher-mail-count" aria-live="polite"></span>
    </form>
    <div class="card">
        <div class="card-body">
            <?= view_cell('Geminus\Admin\Cells\FilterBarCell', [
                'action'      => route_to('admin/exam-vouchers'), 'clearUrl' => route_to('admin/exam-vouchers'),
                'submitLabel' => lang('Admin.userFilter'), 'clearLabel' => lang('Admin.userClear'),
                'hidden'      => $batch === '' ? [] : ['batch' => $batch],
                'fields'      => [['id' => 'voucher-search', 'name' => 'q', 'label' => lang('ExamVouchers.voucherSearch'), 'value' => $query->search, 'maxlength' => 100, 'class' => 'col-12 col-md-4']],
            ]) ?>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr>
                    <th scope="col"><?= esc(lang('ExamVouchers.voucherNumber')) ?></th>
                    <th scope="col"><?= esc(lang('ExamVouchers.identifier')) ?></th>
                    <th scope="col"><?= esc(lang('ExamVouchers.issuer')) ?></th>
                    <th scope="col"><?= esc(lang('ExamVouchers.exam')) ?></th>
                    <th scope="col"><?= esc(lang('ExamVouchers.region')) ?></th>
                    <th scope="col"><?= esc(lang('ExamVouchers.expiresOn')) ?></th>
                    <th scope="col"><?= esc(lang('ExamVouchers.recipient')) ?></th>
                    <th scope="col"><?= esc(lang('ExamVouchers.verification')) ?></th>
                    <th scope="col" class="w-1"><?= esc(lang('Admin.userActions')) ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($vouchers as $voucher): ?>
                        <tr>
                            <td><?php if ($voucher['recipient_type'] !== null): ?><input class="form-check-input voucher-mail-check me-2" type="checkbox" name="ids[]" value="<?= esc($voucher['id'], 'attr') ?>" form="voucher-mail-form" aria-label="<?= esc(lang('ExamVouchers.mailSelect') . ' #' . $voucher['id'], 'attr') ?>"><?php endif ?>#<?= esc($voucher['id']) ?></td>
                            <td class="text-break"><?= esc($voucher['identifier']) ?></td>
                            <td class="text-break"><?= esc($voucher['issuer']) ?></td>
                            <td class="text-break"><?= esc($voucher['exam'] ?? '') ?></td>
                            <td class="text-break"><?= esc($voucher['region'] ?? '') ?></td>
                            <td><?= esc($voucher['expires_on']) ?></td>
                            <td>
                                <?php if ($voucher['recipient_type'] === null): ?>
                                    <span class="badge bg-yellow-lt"><?= esc(lang('ExamVouchers.unassigned')) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-green-lt"><?= esc(lang('ExamVouchers.assignedCount')) ?></span>
                                    <div class="text-break mt-1"><?= esc($voucher['recipient_type'] === 'external' ? $voucher['recipient_name'] : ($voucher['recipient_username'] ?? '#' . $voucher['recipient_id'])) ?></div>
                                    <?php if ($voucher['recipient_type'] === 'external'): ?><div class="text-break text-secondary"><?= esc($voucher['recipient_email']) ?></div><?php endif ?>
                                <?php endif ?>
                            </td>
                            <td><?= esc(lang((int) $voucher['usage_verified'] === 0 ? 'ExamVouchers.notVerified' : 'ExamVouchers.verified')) ?></td>
                            <td><div class="btn-list"><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= route_to('admin/exam-vouchers/show', $voucher['id']) ?>" aria-label="<?= esc(lang('ExamVouchers.voucherDetail'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-title="<?= esc(lang('ExamVouchers.voucherDetail'), 'attr') ?>"><i class="ti ti-eye" aria-hidden="true"></i></a>
                                <?php if ($voucher['recipient_type'] === null): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= route_to('admin/exam-vouchers/assign', $voucher['id']) ?>" aria-label="<?= esc(lang('ExamVouchers.assign'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-title="<?= esc(lang('ExamVouchers.assign'), 'attr') ?>"><i class="ti ti-user-plus" aria-hidden="true"></i></a><?php endif ?>
                                <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= route_to('admin/exam-vouchers/verification', $voucher['id']) ?>" aria-label="<?= esc(lang('ExamVouchers.verification'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-title="<?= esc(lang('ExamVouchers.verification'), 'attr') ?>"><i class="ti ti-checklist" aria-hidden="true"></i></a>
                                <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= route_to('admin/exam-vouchers/note', $voucher['id']) ?>" aria-label="<?= esc(lang('ExamVouchers.voucherNote'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-title="<?= esc(lang('ExamVouchers.voucherNote'), 'attr') ?>"><i class="ti ti-notes" aria-hidden="true"></i></a>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($vouchers === []): ?>
                        <tr><td colspan="9"><?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang($query->search === '' && $batch === '' ? 'ExamVouchers.vouchersEmpty' : 'ExamVouchers.vouchersNoResults'), 'filtered' => $query->search !== '' || $batch !== '', 'clearUrl' => route_to('admin/exam-vouchers'), 'clearLabel' => lang('Admin.userClear')]) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager->only(['q', 'batch'])->links(), 'total' => $pager->getTotal(), 'currentPage' => $pager->getCurrentPage(), 'perPage' => $pager->getPerPage(), 'totalLabel' => lang('ExamVouchers.vouchers')]) ?>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <script>
        (() => {
            const key = 'voucher-mail-selection';
            const checks = document.querySelectorAll('.voucher-mail-check');
            const field = document.getElementById('voucher-mail-selection');
            const count = document.getElementById('voucher-mail-count');
            let selected = new Set(JSON.parse(sessionStorage.getItem(key) || '[]'));
            const refresh = () => {
                checks.forEach(check => { check.checked = selected.has(check.value); });
                field.value = JSON.stringify([...selected]);
                count.textContent = String(selected.size);
                sessionStorage.setItem(key, field.value);
            };
            checks.forEach(check => check.addEventListener('change', () => {
                if (check.checked) selected.add(check.value); else selected.delete(check.value);
                refresh();
            }));
            document.getElementById('voucher-mail-clear').addEventListener('click', () => { selected = new Set(); refresh(); });
            refresh();
        })();
    </script>
<?= $this->endSection() ?>
