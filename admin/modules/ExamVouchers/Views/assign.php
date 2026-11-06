<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('head') ?>
    <?php if ($voucher['recipient_type'] === null): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/libs/tom-select/dist/css/tom-select.bootstrap5.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/css/tabler-vendors.min.css">
        <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/libs/tom-select/dist/js/tom-select.base.min.js"></script>
    <?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto ms-auto btn-list"><a class="btn btn-outline-secondary" href="<?= route_to('admin/exam-vouchers') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.vouchers')) ?></a></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <?php if ($voucher['recipient_type'] !== null): ?>
        <div class="card"><div class="card-body">
            <p><?= esc(lang('ExamVouchers.voucherNumber')) ?>: #<?= esc($voucher['id']) ?></p>
            <p><?= esc(lang('ExamVouchers.alreadyAssigned')) ?></p>
        </div></div>
    <?php else: ?>
        <p class="text-secondary mb-3"><?= esc(lang('ExamVouchers.voucherNumber')) ?>: #<?= esc($voucher['id']) ?></p>
        <div class="row row-cards">
            <div class="col-12 col-xl-6">
                <section class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-user me-2 text-primary" aria-hidden="true"></i><?= esc(lang('ExamVouchers.internalRecipient')) ?></h3>
                    </div>
                    <div class="card-body">
                        <form id="recipient-search" method="get" action="<?= route_to('admin/exam-vouchers/assign', $voucher['id']) ?>">
                            <div class="mb-3">
                                <label class="form-label" for="username"><?= esc(lang('ExamVouchers.username')) ?></label>
                                <input class="form-control" id="username" name="username" value="<?= esc($search, 'attr') ?>" maxlength="30" required aria-describedby="username-hint">
                                <div class="form-hint" id="username-hint"><?= esc(lang('ExamVouchers.recipientHint')) ?></div>
                            </div>
                            <button class="btn btn-outline-primary" type="submit"><i class="ti ti-search me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.search')) ?></button>
                        </form>
                        <form method="post" action="<?= route_to('admin/exam-vouchers/assign/store', $voucher['id']) ?>" class="mt-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="recipient_type" value="internal">
                            <div class="mb-3">
                                <label class="form-label" for="recipient"><?= esc(lang('ExamVouchers.recipient')) ?></label>
                                <select class="form-select" id="recipient" name="recipient_id" required aria-describedby="recipient-hint recipient-status" data-placeholder="<?= esc(lang('ExamVouchers.selectRecipient'), 'attr') ?>">
                                    <option value=""><?= esc(lang('ExamVouchers.selectRecipient')) ?></option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= esc($user->id, 'attr') ?>" data-custom-properties="<?= esc(view_cell('Geminus\Admin\Cells\AvatarCell', ['user' => $user]), 'attr') ?>"><?= esc($user->username) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <div class="form-hint" id="recipient-hint"><?= esc(lang('ExamVouchers.confirmSelected')) ?></div>
                                <div class="form-hint" id="recipient-status" aria-live="polite" data-error="<?= esc(lang('ExamVouchers.searchFailed'), 'attr') ?>" data-empty="<?= esc(lang('ExamVouchers.userNotFound'), 'attr') ?>">
                                    <?php if ($moreUsers): ?><?= esc(lang('ExamVouchers.refineSearch')) ?>
                                    <?php elseif ($search !== '' && $users === []): ?><?= esc(lang('ExamVouchers.userNotFound')) ?><?php endif ?>
                                </div>
                            </div>
                            <button class="btn btn-primary" type="submit"><i class="ti ti-user-check me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.confirmAssign')) ?></button>
                        </form>
                    </div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-user-plus me-2 text-primary" aria-hidden="true"></i><?= esc(lang('ExamVouchers.externalRecipient')) ?></h3>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary"><?= esc(lang('ExamVouchers.externalRecipientHint')) ?></p>
                        <form method="post" action="<?= route_to('admin/exam-vouchers/assign/store', $voucher['id']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="recipient_type" value="external">
                            <div class="mb-3">
                                <label class="form-label" for="recipient-name"><?= esc(lang('ExamVouchers.recipientName')) ?></label>
                                <input class="form-control" id="recipient-name" name="recipient_name" type="text" maxlength="255" autocomplete="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="recipient-email"><?= esc(lang('ExamVouchers.recipientEmail')) ?></label>
                                <input class="form-control" id="recipient-email" name="recipient_email" type="email" maxlength="254" autocomplete="email" required>
                            </div>
                            <button class="btn btn-primary" type="submit"><i class="ti ti-user-check me-1" aria-hidden="true"></i><?= esc(lang('ExamVouchers.confirmAssign')) ?></button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    <?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <?php if ($voucher['recipient_type'] === null): ?>
        <script>
            (() => {
                const form = document.getElementById('recipient-search');
                const status = document.getElementById('recipient-status');
                if (typeof TomSelect === 'undefined') return;

                const renderOption = (data, escape) => data.customProperties
                    ? `<div class="dropdown-item"><span class="dropdown-item-indicator">${data.customProperties}</span>${escape(data.text)}</div>`
                    : `<div>${escape(data.text)}</div>`;
                const picker = new TomSelect('#recipient', {
                    copyClassesToDropdown: false,
                    loadThrottle: 300,
                    shouldLoad: query => query.trim().length > 0 && query.trim().length <= 30,
                    render: {
                        item: renderOption,
                        option: renderOption,
                        no_results: () => `<div class="no-results">${escapeHtml(status.textContent === status.dataset.error ? '' : status.dataset.empty)}</div>`,
                    },
                    async load(query, callback) {
                        const url = new URL(form.action, window.location.href);
                        url.searchParams.set('username', query.trim());
                        try {
                            const response = await fetch(url, { credentials: 'same-origin' });
                            if (!response.ok || response.redirected) throw new Error('Search request failed');
                            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                            const select = page.getElementById('recipient');
                            const updated = page.getElementById('recipient-status');
                            if (!select || !updated) throw new Error('Search results unavailable');
                            if (this.control_input.value.trim() !== query.trim()) {
                                delete this.loadedSearches[query];
                                callback();
                                return;
                            }
                            status.textContent = updated.textContent.trim();
                            delete this.loadedSearches[query];
                            callback(Array.from(select.options).filter(option => option.value).map(option => ({
                                value: option.value, text: option.textContent, customProperties: option.dataset.customProperties,
                            })));
                        } catch (error) {
                            delete this.loadedSearches[query];
                            if (this.control_input.value.trim() === query.trim()) status.textContent = status.dataset.error;
                            console.error(error);
                            callback();
                        }
                    },
                });
                picker.control_input.setAttribute('aria-describedby', 'recipient-hint recipient-status');
                picker.control_input.maxLength = 30;
                picker.control_input.addEventListener('input', () => {
                    status.textContent = '';
                    if (picker.getValue()) picker.clear(true);
                    picker.clearOptions();
                });
                form.hidden = true;

                function escapeHtml(text) {
                    const element = document.createElement('span');
                    element.textContent = text;
                    return element.innerHTML;
                }
            })();
        </script>
    <?php endif ?>
<?= $this->endSection() ?>
