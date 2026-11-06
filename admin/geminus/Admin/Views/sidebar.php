<?php

use Geminus\Admin\Config\AdminMenu;

?>
<ul class="navbar-nav pt-lg-3">
    <li class="nav-item<?= url_is('*/admin/dashboard') ? ' active' : '' ?>">
        <a class="nav-link<?= url_is('*/admin/dashboard') ? ' active' : '' ?>" href="<?= route_to('admin/dashboard') ?>">
            <i class="ti ti-dashboard nav-link-icon icon"></i>
            <span class="nav-link-title"> <?= lang('Admin.dashboard') ?> </span>
        </a>
    </li>
    <?php foreach (config(AdminMenu::class)->items as $item): ?>
        <?php if (array_any((array) $item['permission'], static fn (string $permission): bool => auth()->user()?->can($permission) ?? false)): ?>
            <?php if (isset($item['children'])): ?>
                <?php $isActive = array_any($item['children'], static fn (array $child): bool => url_is($child['active'])); ?>
                <li class="nav-item dropdown<?= $isActive ? ' active' : '' ?>">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="<?= $isActive ? 'true' : 'false' ?>">
                        <i class="ti <?= esc($item['icon'], 'attr') ?> nav-link-icon icon" aria-hidden="true"></i>
                        <span class="nav-link-title"><?= esc(lang($item['label'])) ?></span>
                    </a>
                    <div class="dropdown-menu<?= $isActive ? ' show' : '' ?>">
                        <?php foreach ($item['children'] as $child): ?>
                            <a class="dropdown-item<?= url_is($child['active']) ? ' active' : '' ?>" href="<?= route_to($child['route']) ?>"<?= url_is($child['active']) ? ' aria-current="page"' : '' ?>><?= esc(lang($child['label'])) ?></a>
                        <?php endforeach; ?>
                    </div>
                </li>
            <?php else: ?>
                <li class="nav-item<?= url_is($item['active']) ? ' active' : '' ?>">
                    <a class="nav-link<?= url_is($item['active']) ? ' active' : '' ?>" href="<?= route_to($item['route']) ?>"<?= url_is($item['active']) ? ' aria-current="page"' : '' ?>>
                        <i class="ti <?= esc($item['icon'], 'attr') ?> nav-link-icon icon" aria-hidden="true"></i>
                        <span class="nav-link-title"><?= esc(lang($item['label'])) ?></span>
                    </a>
                </li>
            <?php endif; ?>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if (auth()->user()?->can('users.view') || auth()->user()?->can('users.create') || auth()->user()?->can('operation-audit.view') || auth()->user()?->can('queue-jobs.view') || auth()->user()?->can('email-deliveries.view') || auth()->user()?->can('email-templates.manage') || auth()->user()?->can('email-settings.manage') || auth()->user()?->can('microsoft-settings.manage') || auth()->user()?->inGroup('superadmin')): ?>
        <li class="nav-item">
            <hr class="my-2 mx-3">
        </li>
    <?php endif; ?>
    <?php if (auth()->user()?->can('users.view') || auth()->user()?->can('users.create')): ?>
        <li class="nav-item<?= url_is('*/admin/users*') ? ' active' : '' ?>">
            <a class="nav-link<?= url_is('*/admin/users*') ? ' active' : '' ?>" href="<?= route_to(auth()->user()->can('users.view') ? 'admin/users' : 'admin/users/create') ?>"<?= url_is('*/admin/users*') ? ' aria-current="page"' : '' ?>>
                <i class="ti ti-users nav-link-icon icon" aria-hidden="true"></i>
                <span class="nav-link-title"><?= esc(lang('Admin.users')) ?></span>
            </a>
        </li>
    <?php endif; ?>
    <?php if (auth()->user()?->can('operation-audit.view')): ?>
        <li class="nav-item<?= url_is('*/admin/audit') ? ' active' : '' ?>">
            <a class="nav-link<?= url_is('*/admin/audit') ? ' active' : '' ?>" href="<?= route_to('admin/audit') ?>"<?= url_is('*/admin/audit') ? ' aria-current="page"' : '' ?>>
                <i class="ti ti-history nav-link-icon icon" aria-hidden="true"></i>
                <span class="nav-link-title"><?= esc(lang('Admin.operationAudit')) ?></span>
            </a>
        </li>
    <?php endif; ?>
    <?php if (auth()->user()?->can('queue-jobs.view')): ?>
        <li class="nav-item<?= url_is('*/admin/queue-jobs') ? ' active' : '' ?>">
            <a class="nav-link<?= url_is('*/admin/queue-jobs') ? ' active' : '' ?>" href="<?= route_to('admin/queue-jobs') ?>"<?= url_is('*/admin/queue-jobs') ? ' aria-current="page"' : '' ?>>
                <i class="ti ti-list-check nav-link-icon icon" aria-hidden="true"></i>
                <span class="nav-link-title"><?= esc(lang('Admin.queueJobs')) ?></span>
            </a>
        </li>
    <?php endif; ?>
    <?php if (auth()->user()?->can('email-deliveries.view') || auth()->user()?->can('email-templates.manage')): ?>
        <li class="nav-item dropdown<?= url_is('*/admin/mail/*') ? ' active' : '' ?>">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="<?= url_is('*/admin/mail/*') ? 'true' : 'false' ?>">
                <i class="ti ti-mail nav-link-icon icon" aria-hidden="true"></i>
                <span class="nav-link-title"> <?= esc(lang('Admin.mail')) ?> </span>
            </a>
            <div class="dropdown-menu<?= url_is('*/admin/mail/*') ? ' show' : '' ?>">
                <?php if (auth()->user()?->can('email-deliveries.view')): ?>
                <a class="dropdown-item<?= url_is('*/admin/mail/deliveries') ? ' active' : '' ?>" href="<?= route_to('admin/mail/deliveries') ?>"<?= url_is('*/admin/mail/deliveries') ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.mailDeliveries')) ?></a>
                <?php endif; ?>
                <?php if (auth()->user()?->can('email-templates.manage')): ?>
                <a class="dropdown-item<?= url_is('*/admin/mail/templates') ? ' active' : '' ?>" href="<?= route_to('admin/mail/templates') ?>"<?= url_is('*/admin/mail/templates') ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.mailTemplates')) ?></a>
                <?php endif; ?>
            </div>
        </li>
    <?php endif; ?>
    <?php if (auth()->user()?->can('email-settings.manage') || auth()->user()?->can('microsoft-settings.manage') || auth()->user()?->inGroup('superadmin')): ?>
        <li class="nav-item dropdown<?= url_is('*/admin/settings/*') ? ' active' : '' ?>">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="<?= url_is('*/admin/settings/*') ? 'true' : 'false' ?>">
                <i class="ti ti-settings nav-link-icon icon" aria-hidden="true"></i>
                <span class="nav-link-title"> <?= esc(lang('Admin.systemSettings')) ?> </span>
            </a>
            <div class="dropdown-menu<?= url_is('*/admin/settings/*') ? ' show' : '' ?>">
                <?php if (auth()->user()?->can('email-settings.manage')): ?>
                <a class="dropdown-item<?= url_is('*/admin/settings/email') ? ' active' : '' ?>" href="<?= route_to('admin/settings/email') ?>"<?= url_is('*/admin/settings/email') ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.emailDelivery')) ?></a>
                <?php endif; ?>
                <?php if (auth()->user()?->can('microsoft-settings.manage')): ?>
                <a class="dropdown-item<?= url_is('*/admin/settings/microsoft') ? ' active' : '' ?>" href="<?= route_to('admin/settings/microsoft') ?>"<?= url_is('*/admin/settings/microsoft') ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.microsoftLogin')) ?></a>
                <?php endif; ?>
                <?php if (auth()->user()?->inGroup('superadmin')): ?>
                    <a class="dropdown-item<?= url_is('*/admin/settings/roles') ? ' active' : '' ?>" href="<?= route_to('admin/settings/roles') ?>"<?= url_is('*/admin/settings/roles') ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.roleSettings')) ?></a>
                <?php endif; ?>
            </div>
        </li>
    <?php endif; ?>
</ul>
