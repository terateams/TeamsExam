<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto ms-auto">
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-vouchers') ?>"><i class="ti ti-ticket me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.vouchers')) ?></a>
            <a class="btn btn-primary" href="<?= route_to('admin/exam-voucher-batches/new') ?>"><i class="ti ti-plus me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.create')) ?></a>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card">
        <div class="card-body">
            <?= view_cell('Geminus\Admin\Cells\FilterBarCell', [
                'action'      => route_to('admin/exam-voucher-batches'), 'clearUrl' => route_to('admin/exam-voucher-batches'),
                'submitLabel' => lang('Admin.userFilter'), 'clearLabel' => lang('Admin.userClear'),
                'hidden'      => ['sort' => $query->sort, 'direction' => $query->direction],
                'fields'      => [['id' => 'batch-search', 'name' => 'q', 'label' => lang('ExamVouchers.search'), 'value' => $query->search, 'maxlength' => 100, 'class' => 'col-12 col-md-4']],
            ]) ?>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr>
                    <?php foreach (['identifier' => 'identifier', 'issuer' => 'issuer', 'purchased_on' => 'purchasedOn'] as $field => $label): ?>
                        <?= view_cell('Geminus\Admin\Cells\SortHeaderCell', ['action' => route_to('admin/exam-voucher-batches'), 'field' => $field, 'label' => lang('ExamVouchers.' . $label), 'sort' => $query->sort, 'direction' => $query->direction, 'filters' => ['q' => $query->search], 'defaultDirection' => $field === 'purchased_on' ? 'DESC' : 'ASC']) ?>
                    <?php endforeach; ?>
                    <th scope="col" class="w-1"><?= esc(lang('Admin.userActions')) ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($batches as $batch): ?>
                        <tr>
                            <td class="text-break"><a href="<?= route_to('admin/exam-voucher-batches/show', $batch['id']) ?>"><?= esc($batch['identifier']) ?></a></td>
                            <td class="text-break"><?= esc($batch['issuer']) ?></td>
                            <td class="text-nowrap"><?= esc($batch['purchased_on'] ?? '-') ?></td>
                            <td><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= route_to('admin/exam-voucher-batches/edit', $batch['id']) ?>" aria-label="<?= esc(lang('ExamVouchers.edit'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-title="<?= esc(lang('ExamVouchers.edit'), 'attr') ?>"><i class="ti ti-edit" aria-hidden="true"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($batches === []): ?>
                        <tr><td colspan="4"><?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang($query->search === '' ? 'ExamVouchers.empty' : 'ExamVouchers.noResults'), 'filtered' => $query->search !== '', 'clearUrl' => route_to('admin/exam-voucher-batches'), 'clearLabel' => lang('Admin.userClear'), 'createUrl' => route_to('admin/exam-voucher-batches/new'), 'createLabel' => lang('ExamVouchers.create')]) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager->only(['q', 'sort', 'direction'])->links(), 'total' => $pager->getTotal(), 'currentPage' => $pager->getCurrentPage(), 'perPage' => $pager->getPerPage(), 'totalLabel' => lang('ExamVouchers.total')]) ?>
        </div>
    </div>
<?= $this->endSection() ?>
