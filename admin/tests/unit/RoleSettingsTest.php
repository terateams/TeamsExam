<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Authorization\PermissionMatcher;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Geminus\Admin\Database\Migrations\RegisterAdminFeaturePermissions;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\SuperadminGrants;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Libraries\TableLayoutAssertions;

/**
 * @internal
 */
final class RoleSettingsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testAdminFeatureMigrationPreservesExistingCatalogAndRoleGrants(): void
    {
        $originalPermissions                  = setting('AuthGroups.permissions');
        $originalMatrix                       = setting('AuthGroups.matrix');
        $permissions                          = $originalPermissions;
        $permissions['email-settings.manage'] = 'Custom email settings';
        setting('AuthGroups.permissions', $permissions);
        $existingMatrix               = $originalMatrix;
        $existingMatrix['superadmin'] = ['admin.*', 'users.create', 'email-settings.manage', 'legacy.export'];
        setting('AuthGroups.matrix', $existingMatrix);

        try {
            $migration = new RegisterAdminFeaturePermissions();
            $migration->up();
            $migration->up();
            Services::resetSingle('settings');

            $updated = setting('AuthGroups.permissions');
            $matrix  = setting('AuthGroups.matrix');
            $this->assertSame('Custom email settings', $updated['email-settings.manage']);
            $this->assertSame(['admin.*', 'users.*', 'email-settings.*', 'legacy.*', 'email-deliveries.*', 'email-templates.*', 'operation-audit.*', 'microsoft-settings.*'], $matrix['superadmin']);

            foreach (['users.view', 'email-settings.manage', 'email-deliveries.view', 'email-templates.manage', 'operation-audit.view', 'microsoft-settings.manage'] as $permission) {
                $this->assertArrayHasKey($permission, $updated);
                $this->assertTrue(PermissionMatcher::matches($permission, $matrix['superadmin']));
                $this->assertContains(explode('.', $permission, 2)[0] . '.*', $matrix['superadmin']);
                $this->assertNotContains($permission, $matrix['superadmin']);
            }

            foreach ($originalPermissions as $permission => $description) {
                if ($permission !== 'email-settings.manage') {
                    $this->assertSame($description, $updated[$permission]);
                }
            }

            foreach ($existingMatrix as $role => $grants) {
                if ($role !== 'superadmin') {
                    $this->assertSame($grants, $matrix[$role]);
                } else {
                    foreach ($grants as $grant) {
                        $this->assertContains(explode('.', $grant, 2)[0] . '.*', $matrix[$role]);
                    }
                }
            }
            $this->assertSame(count($matrix['superadmin']), count(array_unique($matrix['superadmin'])));
        } finally {
            service('settings')->setMany(['AuthGroups.permissions' => $originalPermissions, 'AuthGroups.matrix' => $originalMatrix]);
        }
    }

    public function testSuperadminDomainGrantsCoverFutureAbilitiesWithoutChangingInvalidGrants(): void
    {
        $grants = SuperadminGrants::withPermissions(['reports.view', 'reports.*', 'legacy.export', 'invalid', '*.manage', 'broken..view'], ['reports.edit', 'announcements.access']);
        $this->assertSame(['reports.*', 'legacy.*', 'invalid', '*.manage', 'broken..view', 'announcements.*'], $grants);
        $this->assertTrue(PermissionMatcher::matches('reports.publish', $grants));
        $this->assertFalse(PermissionMatcher::matches('users.create', $grants));
        $this->assertSame($grants, SuperadminGrants::withPermissions($grants, ['reports.view']));
        $this->assertSame(['announcements.*'], SuperadminGrants::withPermissions([], ['announcements.access', 'announcements.manage']));
        $invalid = ['Reports.view', "reports.view\n", 'reports.view.extra', 'reports.*.x', '1reports.view'];
        $this->assertSame($invalid, SuperadminGrants::withPermissions($invalid, []));
        $this->assertSame($invalid, SuperadminGrants::withPermissions([], $invalid));
        $this->assertFalse(PermissionMatcher::matches('reports.publish', $invalid));
    }

    public function testOnlySuperadminCanManageRolesAndPermissions(): void
    {
        $before = service('settings')->getMany(['AuthGroups.groups', 'AuthGroups.permissions', 'AuthGroups.matrix']);

        foreach ([null, 'developer'] as $group) {
            if ($group !== null) {
                $this->loginAs($group);
            }
            $this->get('/en/admin/settings/roles')->assertRedirect();

            foreach ([
                ['/en/admin/settings/roles', ['name' => 'operator', 'title' => 'Operator']],
                ['/en/admin/settings/roles/admin/permissions', ['permissions' => ['admin.settings']]],
                ['/en/admin/settings/roles/developer', ['title' => 'Changed']],
                ['/en/admin/settings/permissions', ['name' => 'reports.view', 'description' => 'View reports']],
                ['/en/admin/settings/permissions/users.create', ['description' => 'Changed']],
            ] as [$route, $data]) {
                $result = $this->post($route, [csrf_token() => csrf_hash()] + $data);
                if ($group === null) {
                    $result->assertRedirect();
                } else {
                    $result->assertRedirectTo(config('Auth')->groupDeniedRedirect());
                }
            }
            $this->assertSame($before, service('settings')->getMany(array_keys($before)));
        }
    }

    #[DataProvider('provideRoleGrantOnlyOpensItsOwnFeature')]
    public function testRoleGrantOnlyOpensItsOwnFeature(string $permission, string $allowedPath): void
    {
        $originalMatrix = setting('AuthGroups.matrix');
        $this->loginAs('superadmin');

        try {
            $this->post('/en/admin/settings/roles/admin/permissions', [csrf_token() => csrf_hash(), 'permissions' => [$permission]])->assertRedirect();
            $this->assertSame([$permission], setting('AuthGroups.matrix')['admin']);
            auth()->logout();
            $this->loginAs('admin');

            $page = $this->get($allowedPath);
            $page->assertOK();
            $body = $page->response()->getBody();
            $this->assertStringContainsString('href="' . $allowedPath . '"', $body);
            $deniedUrl = config('Auth')->permissionDeniedRedirect();

            foreach (self::provideRoleGrantOnlyOpensItsOwnFeature() as [$otherPermission, $otherPath]) {
                if ($otherPermission !== $permission) {
                    $this->assertStringNotContainsString('href="' . $otherPath . '"', $body);
                    $this->get($otherPath)->assertRedirectTo($deniedUrl);
                }
            }

            $settingKeys    = ['Email.fromEmail', 'Email.fromName', 'Email.protocol', 'MicrosoftOAuth.enabled', 'MicrosoftOAuth.tenant', 'MicrosoftOAuth.clientId'];
            $beforeSettings = service('settings')->getMany($settingKeys);
            $templates      = service('mailTemplates');
            $beforeTemplate = $templates->get('invitation', 'en');
            $beforeQueue    = db_connect()->table('queue_jobs')->countAllResults();

            foreach ([
                'email-settings.manage' => [
                    ['/en/admin/settings/email', ['fromEmail' => 'denied@example.com', 'fromName' => 'Denied', 'protocol' => 'mail']],
                    ['/en/admin/settings/email/test', ['test_email' => 'denied@example.com']],
                ],
                'email-templates.manage' => [
                    ['/en/admin/mail/templates/invitation/en', ['subject' => 'Denied', 'body' => 'Denied {link}']],
                    ['/en/admin/mail/templates/invitation/en/reset', []],
                ],
                'microsoft-settings.manage' => [
                    ['/en/admin/settings/microsoft', ['enabled' => '1', 'tenant' => 'organizations', 'clientId' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']],
                ],
            ] as $writePermission => $requests) {
                if ($writePermission !== $permission) {
                    foreach ($requests as [$writePath, $data]) {
                        $this->post($writePath, [csrf_token() => csrf_hash()] + $data)->assertRedirectTo($deniedUrl);
                    }
                }
            }
            $this->assertSame($beforeSettings, service('settings')->getMany($settingKeys));
            $this->assertSame($beforeTemplate, $templates->get('invitation', 'en'));
            $this->assertSame($beforeQueue, db_connect()->table('queue_jobs')->countAllResults());
            $this->get('/en/admin/settings/roles')->assertRedirect();
        } finally {
            setting('AuthGroups.matrix', $originalMatrix);
        }
    }

    public static function provideRoleGrantOnlyOpensItsOwnFeature(): iterable
    {
        return [
            ['users.view', '/en/admin/users'],
            ['email-settings.manage', '/en/admin/settings/email'],
            ['email-deliveries.view', '/en/admin/mail/deliveries'],
            ['queue-jobs.view', '/en/admin/queue-jobs'],
            ['email-templates.manage', '/en/admin/mail/templates'],
            ['operation-audit.view', '/en/admin/audit'],
            ['microsoft-settings.manage', '/en/admin/settings/microsoft'],
        ];
    }

    public function testLegacySettingsGrantDoesNotOpenFeaturePages(): void
    {
        $this->loginAs('developer');

        foreach (self::provideRoleGrantOnlyOpensItsOwnFeature() as [$permission, $featurePath]) {
            $this->get($featurePath)->assertRedirect();
        }
    }

    public function testMicrosoftSettingsPermissionCannotManageBindings(): void
    {
        $originalMatrix  = setting('AuthGroups.matrix');
        $matrix          = $originalMatrix;
        $matrix['admin'] = ['microsoft-settings.manage'];
        setting('AuthGroups.matrix', $matrix);

        try {
            $this->loginAs('admin');
            $links   = Services::microsoftLinks();
            $tenant  = '11111111-2222-3333-4444-555555555555';
            $linked  = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';
            $pending = 'cccccccc-dddd-eeee-ffff-000000000000';
            $this->assertTrue($links->bind(auth()->user(), $tenant, $linked));
            $this->assertTrue($links->request($tenant, $pending, null));
            $requests  = $links->pending();
            $requestId = $requests[0]['id'];
            $userId    = auth()->id();
            $page      = $this->get('/en/admin/settings/microsoft');
            $page->assertOK();
            $this->assertStringNotContainsString('/requests/' . $requestId . '/approve', $page->response()->getBody());
            $this->assertStringNotContainsString('/users/' . $userId . '/revoke', $page->response()->getBody());

            foreach (['/requests/' . $requestId . '/approve', '/requests/' . $requestId . '/reject', '/users/' . $userId . '/revoke'] as $action) {
                $this->post('/en/admin/settings/microsoft' . $action, [csrf_token() => csrf_hash(), 'user_id' => $userId])->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
                $this->assertSame($requests, $links->pending());
                $this->assertSame($userId, $links->findUser($tenant, $linked)?->id);
                $this->assertNull($links->findUser($tenant, $pending));
            }
        } finally {
            setting('AuthGroups.matrix', $originalMatrix);
        }
    }

    public function testPermissionDomainsAreLocalizedAndKeepCustomDescriptions(): void
    {
        $this->loginAs('superadmin');
        $permissions                          = setting('AuthGroups.permissions');
        $original                             = $permissions;
        $permissions['email-settings.manage'] = 'Custom scope';
        setting('AuthGroups.permissions', $permissions);

        try {
            foreach (['en' => 'Email delivery settings', 'zh-Hans' => '邮件发送设置', 'zh-Hant' => '郵件寄送設定'] as $locale => $label) {
                $page = $this->get('/' . $locale . '/admin/settings/roles?role=admin');
                $page->assertOK();
                $page->assertSee($label);
                $page->assertSee('Custom scope');
                $roleDocument = new DOMDocument();
                @$roleDocument->loadHTML($page->response()->getBody());
                $roleButtons = (new DOMXPath($roleDocument))->query('//a[contains(@class, "btn-icon") and not(ancestor::header) and not(ancestor::aside)]');
                $this->assertCount(2, $roleButtons);

                foreach ($roleButtons as $button) {
                    $this->assertSame('tooltip', $button->getAttribute('data-bs-toggle'));
                    $this->assertSame($button->getAttribute('aria-label'), $button->getAttribute('data-bs-title'));
                }
                $domainLabels = lang('Admin.permissionDomainLabels');

                foreach (array_keys($permissions) as $permission) {
                    $domain = explode('.', $permission, 2)[0];
                    $this->assertArrayHasKey($domain, $domainLabels);
                    $this->assertStringContainsString('<legend class="h4 border-bottom pb-2 mb-3">' . esc($domainLabels[$domain]) . '</legend>', $page->response()->getBody());
                }
                $this->assertStringContainsString('<span class="form-check-label text-break">email-settings.manage</span>', $page->response()->getBody());
                $this->assertStringContainsString('value="email-settings.manage"', $page->response()->getBody());
                $this->get('/' . $locale . '/admin/settings/roles?role=superadmin')->assertSee($label);
            }
            $catalog = $this->get('/zh-Hans/admin/settings/roles?view=permissions');
            $catalog->assertOK();
            $catalog->assertSee('邮件发送设置');
            TableLayoutAssertions::assertTablesInCards($catalog->response()->getBody());
            $document = new DOMDocument();
            @$document->loadHTML($catalog->response()->getBody());
            $buttons = (new DOMXPath($document))->query('//table//a[contains(@class, "btn-icon")]');
            $this->assertGreaterThan(0, $buttons->length);

            foreach ($buttons as $button) {
                $this->assertSame('', $button->getAttribute('title'));
                $this->assertNotSame('', $button->getAttribute('aria-label'));
                $this->assertSame('tooltip', $button->getAttribute('data-bs-toggle'));
                $this->assertSame($button->getAttribute('aria-label'), $button->getAttribute('data-bs-title'));
            }
        } finally {
            setting('AuthGroups.permissions', $original);
        }
    }

    public function testPermissionChangeRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);

        $this->post('/en/admin/settings/roles/admin/permissions', ['permissions' => ['admin.settings']]);
    }

    public function testPermissionDescriptionCanBeUpdatedWithoutChangingGrants(): void
    {
        $this->loginAs('superadmin');
        $editPage = $this->get('/en/admin/settings/roles?view=permissions&role=developer&permission=users.create');
        $editPage->assertOK();
        $this->assertStringContainsString('action="/en/admin/settings/permissions/users.create?role=developer"', $editPage->response()->getBody());
        $this->assertStringContainsString('value="users.create" readonly', $editPage->response()->getBody());
        $this->assertStringContainsString('value="' . esc(setting('AuthGroups.permissions')['users.create']) . '"', $editPage->response()->getBody());
        $this->assertStringContainsString('cannot be renamed', $editPage->response()->getBody());
        $invalidPage = $this->get('/en/admin/settings/roles?view=permissions&permission=unknown.permission');
        $this->assertStringContainsString('action="/en/admin/settings/permissions?role=admin"', $invalidPage->response()->getBody());

        $before           = setting('AuthGroups.matrix');
        $otherDescription = setting('AuthGroups.permissions')['admin.settings'];

        $result = $this->post('/en/admin/settings/permissions/users.create?role=developer', [csrf_token() => csrf_hash(), 'description' => 'Add users', 'name' => 'admin.settings']);
        $result->assertRedirect();
        $this->assertStringEndsWith('/en/admin/settings/roles?view=permissions&permission=users.create&role=developer', $result->response()->getHeaderLine('Location'));
        $this->assertSame('Add users', setting('AuthGroups.permissions')['users.create']);
        $this->assertSame($otherDescription, setting('AuthGroups.permissions')['admin.settings']);
        $this->assertSame($before, setting('AuthGroups.matrix'));

        $this->post('/en/admin/settings/permissions/users.create', [csrf_token() => csrf_hash(), 'description' => ''])->assertRedirect();
        $this->assertNotEmpty(session('permission_edit_errors.description'));
        $this->assertSame('Add users', setting('AuthGroups.permissions')['users.create']);
        $this->post('/en/admin/settings/permissions/missing.view', [csrf_token() => csrf_hash(), 'description' => 'No'])->assertStatus(404);
    }

    public function testPermissionEditRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);

        $this->post('/en/admin/settings/permissions/users.create', ['description' => 'Changed']);
    }

    public function testRoleMetadataCanBeUpdatedWithoutChangingGrants(): void
    {
        $this->loginAs('superadmin');
        $originalGroups = setting('AuthGroups.groups');

        try {
            $rolePage = $this->get('/en/admin/settings/roles?role=developer');
            $this->assertStringContainsString('href="/en/admin/settings/roles?view=edit-role&role=developer"', $rolePage->response()->getBody());
            $editPage = $this->get('/en/admin/settings/roles?view=edit-role&role=developer');
            $editPage->assertOK();
            $this->assertStringContainsString('action="/en/admin/settings/roles/developer"', $editPage->response()->getBody());
            $this->assertStringContainsString('value="developer" readonly', $editPage->response()->getBody());
            $this->assertStringContainsString('value="' . esc(setting('AuthGroups.groups')['developer']['title']) . '"', $editPage->response()->getBody());
            $this->assertStringContainsString('value="' . esc(setting('AuthGroups.groups')['developer']['description']) . '"', $editPage->response()->getBody());
            $this->assertStringNotContainsString('name="name"', $editPage->response()->getBody());

            $before = setting('AuthGroups.matrix');

            $saved = $this->post('/en/admin/settings/roles/developer', [csrf_token() => csrf_hash(), 'title' => 'Engineers', 'description' => 'Engineering team', 'name' => 'superadmin']);
            $saved->assertRedirect();
            $this->assertStringEndsWith('/en/admin/settings/roles?role=developer', $saved->response()->getHeaderLine('Location'));
            $this->assertSame('Engineers', setting('AuthGroups.groups')['developer']['title']);
            $this->assertSame('Engineering team', setting('AuthGroups.groups')['developer']['description']);
            $this->assertSame($before, setting('AuthGroups.matrix'));
            $this->assertArrayNotHasKey('Engineers', setting('AuthGroups.groups'));

            $this->post('/en/admin/settings/roles/developer', [csrf_token() => csrf_hash(), 'title' => ''])->assertRedirect();
            $this->assertNotEmpty(session('role_edit_errors.title'));
            $this->assertSame('Engineers', setting('AuthGroups.groups')['developer']['title']);
            $this->post('/en/admin/settings/roles/unknown', [csrf_token() => csrf_hash(), 'title' => 'No'])->assertStatus(404);
        } finally {
            setting('AuthGroups.groups', $originalGroups);
        }
    }

    public function testSuperadminDisplaysEffectivePermissionsAndGrantSources(): void
    {
        $this->loginAs('superadmin');
        $originalMatrix       = setting('AuthGroups.matrix');
        $matrix               = setting('AuthGroups.matrix');
        $matrix['superadmin'] = ['admin.*', 'users.create', 'legacy.*'];
        setting('AuthGroups.matrix', $matrix);

        try {
            $page = $this->get('/en/admin/settings/roles?role=superadmin');
            $page->assertOK();
            $body = $page->response()->getBody();
            $this->assertStringContainsString('admin.settings', $body);
            $this->assertStringContainsString('users.create', $body);
            $this->assertStringNotContainsString('users.edit', $body);
            $this->assertStringContainsString('Granted by', $body);
            $this->assertStringContainsString('3 of ' . count(setting('AuthGroups.permissions')) . ' catalog permissions granted', $body);
            $this->assertStringContainsString('legacy.*', $body);
            $this->assertStringContainsString('<details>', $body);
            $this->assertStringNotContainsString('action="/en/admin/settings/roles/superadmin/permissions"', $body);
            $this->assertSame(['admin.*', 'users.create', 'legacy.*'], setting('AuthGroups.matrix')['superadmin']);

            $this->get('/zh-Hans/admin/settings/roles?role=superadmin')->assertSee('授权来源');
            $this->get('/zh-Hant/admin/settings/roles?role=superadmin')->assertSee('授權來源');

            $matrix['superadmin'] = [];
            setting('AuthGroups.matrix', $matrix);
            $emptyPage = $this->get('/en/admin/settings/roles?role=superadmin');
            $emptyPage->assertSee('No catalog permissions are currently granted.');
            $this->assertStringNotContainsString('<details>', $emptyPage->response()->getBody());
        } finally {
            setting('AuthGroups.matrix', $originalMatrix);
        }
    }

    public function testRoleEditRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);

        $this->post('/en/admin/settings/roles/developer', ['title' => 'Changed']);
    }

    public function testCreatingPermissionsNormalizesOnlySuperadminGrants(): void
    {
        $this->loginAs('superadmin');
        $originalPermissions  = setting('AuthGroups.permissions');
        $originalMatrix       = setting('AuthGroups.matrix');
        $matrix               = $originalMatrix;
        $matrix['superadmin'] = ['legacy.export'];
        $matrix['developer']  = ['users.edit'];
        setting('AuthGroups.matrix', $matrix);

        try {
            foreach (['reports.view', 'reports.edit'] as $permission) {
                $this->post('/en/admin/settings/permissions', [csrf_token() => csrf_hash(), 'name' => $permission, 'description' => 'Report capability'])->assertRedirectTo('/en/admin/settings/roles?view=permissions');
                $matrix['superadmin'] = ['legacy.*', 'reports.*'];
                $this->assertSame($matrix, setting('AuthGroups.matrix'));
                $this->assertSame('Report capability', setting('AuthGroups.permissions')[$permission]);
            }
        } finally {
            service('settings')->setMany(['AuthGroups.permissions' => $originalPermissions, 'AuthGroups.matrix' => $originalMatrix]);
        }
    }

    public function testNewRoleAndPermissionCanBeAssigned(): void
    {
        $this->loginAs('superadmin');
        $page = $this->get('/en/admin/settings/roles');
        $page->assertOK();
        $page->assertSee('Roles and permissions');
        $this->assertStringContainsString('name="' . csrf_token() . '"', $page->response()->getBody());
        $this->assertStringContainsString('action="/en/admin/settings/roles/admin/permissions"', $page->response()->getBody());
        $selectedPage = $this->get('/en/admin/settings/roles?role=developer');
        $this->assertStringContainsString('action="/en/admin/settings/roles/developer/permissions"', $selectedPage->response()->getBody());
        $this->assertStringNotContainsString('action="/en/admin/settings/roles/admin/permissions"', $selectedPage->response()->getBody());
        $newRolePage = $this->get('/en/admin/settings/roles?view=new-role');
        $this->assertStringContainsString('action="/en/admin/settings/roles"', $newRolePage->response()->getBody());
        $this->assertStringNotContainsString('action="/en/admin/settings/roles/admin/permissions"', $newRolePage->response()->getBody());
        $catalogPage = $this->get('/en/admin/settings/roles?view=permissions');
        $catalogPage->assertSee('Permission catalog');
        $catalogPage->assertSee('Use domain.ability (e.g. reports.view).');
        $this->assertStringContainsString('aria-describedby="permission-name-hint"', $catalogPage->response()->getBody());
        $this->assertStringContainsString('action="/en/admin/settings/permissions?role=admin"', $catalogPage->response()->getBody());
        $this->assertStringNotContainsString('action="/en/admin/settings/roles/admin/permissions"', $catalogPage->response()->getBody());
        $this->get('/zh-Hans/admin/settings/roles?view=permissions')->assertSee('格式为 domain.ability');
        $this->get('/zh-Hant/admin/settings/roles?view=permissions')->assertSee('格式為 domain.ability');
        $developerCatalog = $this->get('/en/admin/settings/roles?view=permissions&role=developer');
        $this->assertStringContainsString('href="/en/admin/settings/roles?role=developer"', $developerCatalog->response()->getBody());

        $createdRole = $this->post('/en/admin/settings/roles', [csrf_token() => csrf_hash(), 'name' => 'operator', 'title' => 'Operator', 'description' => 'Operations']);
        $createdRole->assertRedirect();
        $this->assertStringEndsWith('/en/admin/settings/roles?role=operator', $createdRole->response()->getHeaderLine('Location'));
        $createdPermission = $this->post('/en/admin/settings/permissions', [csrf_token() => csrf_hash(), 'name' => 'reports.view', 'description' => 'View reports']);
        $createdPermission->assertRedirect();
        $this->assertStringEndsWith('/en/admin/settings/roles?view=permissions', $createdPermission->response()->getHeaderLine('Location'));
        $contextRedirect = $this->post('/en/admin/settings/permissions?role=developer', [csrf_token() => csrf_hash(), 'name' => 'reports.edit', 'description' => 'Edit reports']);
        $this->assertStringEndsWith('/en/admin/settings/roles?view=permissions&role=developer', $contextRedirect->response()->getHeaderLine('Location'));
        $this->assertSame('Operator', setting('AuthGroups.groups')['operator']['title']);
        $this->assertSame('View reports', setting('AuthGroups.permissions')['reports.view']);
        $this->assertContains('reports.*', setting('AuthGroups.matrix')['superadmin']);
        $this->assertNotContains('reports.view', setting('AuthGroups.matrix')['superadmin']);
        $this->assertNotContains('reports.edit', setting('AuthGroups.matrix')['superadmin']);
        $this->assertCount(1, array_keys(setting('AuthGroups.matrix')['superadmin'], 'reports.*', true));
        $this->assertTrue(auth()->user()->can('reports.view'));

        $saved = $this->post('/en/admin/settings/roles/operator/permissions', [csrf_token() => csrf_hash(), 'permissions' => ['reports.view']]);
        $saved->assertRedirect();
        $this->assertStringEndsWith('/en/admin/settings/roles?role=operator', $saved->response()->getHeaderLine('Location'));
        $this->assertSame(['reports.view'], setting('AuthGroups.matrix')['operator']);

        $user        = new AdminUser(['username' => 'operatoruser']);
        $user->email = 'operator@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('operator');
        $this->assertTrue($user->can('reports.view'));
        $this->assertFalse($user->can('admin.settings'));
        $this->post('/en/admin/settings/roles/operator/permissions', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame([], setting('AuthGroups.matrix')['operator']);
        $this->assertFalse($user->can('reports.view'));
        $this->get('/zh-Hans/admin/settings/roles')->assertSee('角色与权限');
        $this->get('/zh-Hant/admin/settings/roles')->assertSee('角色與權限');
    }

    public function testInvalidChangesNeverModifySettingsOrSuperadmin(): void
    {
        $this->loginAs('superadmin');
        $this->post('/en/admin/settings/roles', [csrf_token() => csrf_hash(), 'name' => 'superadmin', 'title' => 'Other'])->assertRedirect();
        $this->assertNotEmpty(session('role_errors.name'));
        $this->post('/en/admin/settings/permissions', [csrf_token() => csrf_hash(), 'name' => 'admin.*', 'description' => 'All'])->assertRedirect();
        $this->assertNotEmpty(session('permission_errors.name'));

        $before = setting('AuthGroups.matrix');
        $this->post('/en/admin/settings/roles/admin/permissions', [csrf_token() => csrf_hash(), 'permissions' => ['admin.settings', 'unknown.permission']])->assertRedirect();
        $this->assertSame($before, setting('AuthGroups.matrix'));
        $this->post('/en/admin/settings/roles/admin/permissions', [csrf_token() => csrf_hash(), 'permissions' => [['admin.settings']]])->assertRedirect();
        $this->assertSame($before, setting('AuthGroups.matrix'));
        $this->post('/en/admin/settings/roles/superadmin/permissions', [csrf_token() => csrf_hash(), 'permissions' => []])->assertStatus(404);
        $this->assertSame($before, setting('AuthGroups.matrix'));
    }

    public function testUnchangedWildcardGrantsArePreserved(): void
    {
        $this->loginAs('superadmin');
        $matrix                = setting('AuthGroups.matrix');
        $matrix['developer'][] = 'admin.*';
        setting('AuthGroups.matrix', $matrix);

        $permissions = array_keys(setting('AuthGroups.permissions'));
        $selected    = array_values(array_filter($permissions, static fn (string $permission): bool => str_starts_with($permission, 'admin.') || in_array($permission, $matrix['developer'], true)));
        $page        = $this->get('/en/admin/settings/roles?role=developer');
        $this->assertStringContainsString('name="grants[]" value="admin.&#x2A;" checked', $page->response()->getBody());
        $this->post('/en/admin/settings/roles/developer/permissions', [csrf_token() => csrf_hash(), 'permissions' => $selected, 'grants' => ['admin.*']])->assertRedirect();
        $this->assertContains('admin.*', setting('AuthGroups.matrix')['developer']);

        $selected = array_values(array_diff($selected, ['admin.settings']));
        $this->post('/en/admin/settings/roles/developer/permissions', [csrf_token() => csrf_hash(), 'permissions' => $selected, 'grants' => ['admin.*']])->assertRedirect();
        $this->assertSame(lang('Admin.wildcardPermissionConflict'), session('alert')['message']);
        $this->assertContains('admin.*', setting('AuthGroups.matrix')['developer']);

        $this->post('/en/admin/settings/roles/developer/permissions', [csrf_token() => csrf_hash(), 'permissions' => $selected])->assertRedirect();
        $this->assertNotContains('admin.*', setting('AuthGroups.matrix')['developer']);

        $matrix                = setting('AuthGroups.matrix');
        $matrix['developer'][] = 'internal.export';
        setting('AuthGroups.matrix', $matrix);
        $this->post('/en/admin/settings/roles/developer/permissions', [csrf_token() => csrf_hash(), 'permissions' => $selected, 'grants' => ['forged.access']])->assertRedirect();
        $this->assertContains('internal.export', setting('AuthGroups.matrix')['developer']);
        $this->post('/en/admin/settings/roles/developer/permissions', [csrf_token() => csrf_hash(), 'permissions' => $selected])->assertRedirect();
        $this->assertNotContains('internal.export', setting('AuthGroups.matrix')['developer']);
    }

    #[DataProvider('provideInvalidCatalogIdentifiersPreserveSettings')]
    public function testInvalidCatalogIdentifiersPreserveSettings(string $resource, string $name): void
    {
        $this->loginAs('superadmin');
        $groups      = setting('AuthGroups.groups');
        $permissions = setting('AuthGroups.permissions');
        $matrix      = setting('AuthGroups.matrix');
        $payload     = $resource === 'roles' ? ['title' => 'Invalid role'] : ['description' => 'Invalid permission'];
        $this->post('/en/admin/settings/' . $resource, [csrf_token() => csrf_hash(), 'name' => $name] + $payload)->assertRedirect();
        $this->assertNotEmpty(session($resource === 'roles' ? 'role_errors.name' : 'permission_errors.name'));
        $this->assertSame($groups, setting('AuthGroups.groups'));
        $this->assertSame($permissions, setting('AuthGroups.permissions'));
        $this->assertSame($matrix, setting('AuthGroups.matrix'));
    }

    public static function provideInvalidCatalogIdentifiersPreserveSettings(): iterable
    {
        yield 'uppercase role' => ['roles', 'Operator'];

        yield 'underscore role' => ['roles', 'invalid_role'];

        yield 'duplicate role' => ['roles', 'user'];

        yield 'single segment permission' => ['permissions', 'reports'];

        yield 'three segment permission' => ['permissions', 'reports.items.view'];

        yield 'wildcard permission' => ['permissions', 'reports.*'];

        yield 'duplicate permission' => ['permissions', 'users.create'];
    }

    public function testDelegatedManagerCannotAssignMorePowerfulRole(): void
    {
        $this->loginAs('superadmin');
        $superadminId      = auth()->id();
        $groups            = setting('AuthGroups.groups');
        $groups['manager'] = ['title' => 'Manager', 'description' => ''];
        setting('AuthGroups.groups', $groups);
        $matrix            = setting('AuthGroups.matrix');
        $matrix['manager'] = ['admin.access', 'users.edit', 'users.manage-admins'];
        setting('AuthGroups.matrix', $matrix);

        $target        = new AdminUser(['username' => 'manageduser']);
        $target->email = 'managed@example.com';
        $target->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($target);
        $target = $users->findById($users->getInsertID());
        $target->addGroup('user');
        auth()->logout();
        $this->loginAs('manager');

        $page = $this->get('/en/admin/users/' . $target->id . '/edit');
        $page->assertOK();
        $this->assertStringNotContainsString('value="developer"', $page->response()->getBody());
        $this->post('/en/admin/users/' . $target->id . '/edit', [
            csrf_token() => csrf_hash(),
            'username'   => 'manageduser',
            'email'      => 'managed@example.com',
            'role'       => 'developer',
            'status'     => 'enabled',
        ])->assertRedirect();
        $this->assertNotEmpty(session('user_errors.role'));
        $this->assertSame(['user'], $users->findById($target->id)->getGroups());

        foreach (['admin', 'developer'] as $role) {
            $protected        = new AdminUser(['username' => 'protected' . $role]);
            $protected->email = 'protected' . $role . '@example.com';
            $protected->setPassword('A-local-password-123!');
            $users->save($protected);
            $protected = $users->findById($users->getInsertID());
            $protected->addGroup($role);
            $this->get('/en/admin/users/' . $protected->id . '/edit')->assertStatus(404);
            $this->post('/en/admin/users/' . $protected->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'takenover', 'email' => 'takenover@example.com', 'role' => 'user', 'status' => 'enabled'])->assertStatus(404);
            $this->post('/en/admin/users/' . $protected->id . '/invite', [csrf_token() => csrf_hash()])->assertStatus(404);
            $this->assertSame('protected' . $role . '@example.com', $users->findById($protected->id)->email);
            $this->assertSame([$role], $users->findById($protected->id)->getGroups());
        }

        $this->get('/en/admin/users/' . $superadminId . '/edit')->assertStatus(404);
        $this->post('/en/admin/users/' . $superadminId . '/edit', [csrf_token() => csrf_hash(), 'username' => 'takenover', 'email' => 'takenover@example.com', 'role' => 'user', 'status' => 'enabled'])->assertStatus(404);
        $this->post('/en/admin/users/' . $superadminId . '/invite', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->assertSame(['superadmin'], $users->findById($superadminId)->getGroups());

        $matrix              = setting('AuthGroups.matrix');
        $matrix['developer'] = ['internal.export'];
        setting('AuthGroups.matrix', $matrix);
        $this->assertArrayNotHasKey('internal.export', setting('AuthGroups.permissions'));
        $page = $this->get('/en/admin/users/' . $target->id . '/edit');
        $this->assertStringNotContainsString('value="developer"', $page->response()->getBody());
    }

    private function loginAs(string $group): void
    {
        $user        = new AdminUser(['username' => 'rolesettings' . $group]);
        $user->email = 'rolesettings' . $group . '@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($group);
        auth()->login($user);
    }
}
