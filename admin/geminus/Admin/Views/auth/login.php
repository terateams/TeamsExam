<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('page_title') ?><?= esc(lang('Auth.login')) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container py-4 px-3">
        <div class="row align-items-center g-4">
            <div class="col-lg">
                <div class="container-tight">
                    <div class="text-center mb-4"><span class="h2">TeamsExam</span></div>
                    <div class="card card-md">
                        <div class="card-body">
                            <h1 class="h2 text-center mb-4"><?= esc(lang('Auth.login')) ?></h1>
                            <?= $this->include('Geminus\Admin\Views\auth\notice') ?>
                            <form action="<?= url_to('login') ?>" method="post">
                                <?= csrf_field() ?>
                                <div class="mb-3">
                                    <label class="form-label required" for="login-email"><?= esc(lang('Auth.email')) ?></label>
                                    <input id="login-email" name="email" type="email" class="form-control" value="<?= esc(old('email')) ?>" inputmode="email" autocomplete="email" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required" for="login-password"><?= esc(lang('Auth.password')) ?></label>
                                    <input id="login-password" name="password" type="password" class="form-control" autocomplete="current-password" required>
                                </div>
                                <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
                                    <label class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="remember" value="1"<?= old('remember') ? ' checked' : '' ?>>
                                        <span class="form-check-label"><?= esc(lang('Auth.rememberMe')) ?></span>
                                    </label>
                                <?php endif; ?>
                                <div class="form-footer"><button type="submit" class="btn btn-primary w-100"><i class="ti ti-login me-1" aria-hidden="true"></i><?= esc(lang('Auth.login')) ?></button></div>
                            </form>
                            <?php if (service('settings')->get('MicrosoftOAuth.enabled')): ?>
                                <a href="<?= route_to('microsoft/start') ?>" class="btn btn-outline-primary w-100 mt-3"><i class="ti ti-brand-windows me-1" aria-hidden="true"></i><?= esc(lang('Admin.microsoftLogin')) ?></a>
                            <?php endif; ?>
                            <?php if (setting('Auth.allowMagicLinkLogins')): ?>
                                <p class="text-center text-secondary mt-3 mb-0"><?= esc(lang('Auth.forgotPassword')) ?> <a href="<?= url_to('magic-link') ?>"><?= esc(lang('Auth.useMagicLink')) ?></a></p>
                            <?php endif; ?>
                            <?php if (setting('Auth.allowRegistration')): ?>
                                <p class="text-center text-secondary mt-3 mb-0"><?= esc(lang('Auth.needAccount')) ?> <a href="<?= url_to('register') ?>"><?= esc(lang('Auth.register')) ?></a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg d-none d-lg-block">
                <img src="/static/images/undraw_login_weas.svg" alt="" width="400" height="397" class="img-fluid d-block mx-auto">
            </div>
        </div>
    </div>
<?= $this->endSection() ?>