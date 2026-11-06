<?php

use CodeIgniter\Config\Services;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Files\File;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\QueuePushResult;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Geminus\Admin\Controllers\Users;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\Dashboard\AuditDashboardProvider;
use Geminus\Admin\Libraries\Dashboard\EmailDashboardProvider;
use Geminus\Admin\Libraries\Dashboard\UserDashboardProvider;
use Geminus\Admin\Libraries\MailTemplates;
use Geminus\Admin\Libraries\MicrosoftLinks;
use Geminus\Admin\Libraries\QueuedEmail;
use Geminus\Admin\Libraries\UserProvisioning;
use Geminus\Admin\Models\AttachmentModel;
use Geminus\Admin\Models\EmailDeliveryLogModel;
use Geminus\Admin\Models\OperationAuditModel;
use Geminus\Admin\Models\UserModel;
use Modules\Announcements\Models\AnnouncementModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Libraries\TableLayoutAssertions;

/**
 * @internal
 */
final class UsersTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    public function testAdminDashboardProvidersRespectIndependentPermissions(): void
    {
        $this->assertSame('created', service('userProvisioning')->create('dashboardviewer', 'dashboardviewer@example.com'));
        $viewer = auth()->getProvider()->findByCredentials(['email' => 'dashboardviewer@example.com']);
        $this->assertSame([], service('dashboard')->sections($viewer, 'en'));
        $viewer->addPermission('users.create');
        $sections = service('dashboard')->sections($viewer, 'en');
        $this->assertSame(['users'], array_column($sections, 'id'));
        $this->assertSame(['create'], array_column($sections[0]['items'], 'id'));
        $viewer->addPermission('users.view', 'email-deliveries.view', 'operation-audit.view');
        $sections = service('dashboard')->sections($viewer, 'en');
        $this->assertSame(['users', 'email', 'audit'], array_column($sections, 'id'));
        $this->assertFalse($sections[0]['unavailable']);
        $this->assertSame(1, $sections[0]['items'][0]['value']);
        $this->assertSame(0, $sections[1]['items'][0]['value']);
        $this->assertSame('progress', $sections[1]['items'][2]['type']);
        $this->assertSame(0, $sections[1]['items'][2]['value']);
        $this->assertSame(0, $sections[1]['items'][2]['max']);
        $this->assertSame([], $sections[2]['items'][0]['rows']);
        $html     = view_cell('Geminus\Admin\Cells\DashboardCell', ['sections' => $sections, 'locale' => 'en', 'timezone' => 'UTC']);
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($document);
        $this->assertCount(1, $xpath->query('//*[@data-dashboard-item="email:success-rate"]'));
        $this->assertCount(0, $xpath->query('//*[@data-dashboard-item="email:success-rate"]//*[@role="progressbar"]'));
        $this->assertStringContainsString('No items to track.', $html);
        $viewer->removePermission('users.view', 'users.create', 'email-deliveries.view', 'operation-audit.view');
        $this->assertSame([], service('dashboard')->sections($viewer, 'en'));
    }

    public function testAdminDashboardCountsBoundedListsAndLinksMatchTheirPages(): void
    {
        $users = new UserModel();
        $ids   = [];

        for ($index = 0; $index < 7; $index++) {
            $user = new AdminUser(['username' => 'dashboard' . $index, 'status' => $index === 6 ? 'banned' : null]);
            $users->save($user);
            $ids[] = $users->getInsertID();
            $users->db->table('users')->where('id', $ids[$index])->update(['created_at' => $index === 0 ? '2026-10-11 02:00:00' : '2026-10-10 01:02:03']);
        }
        $deleted = new AdminUser(['username' => 'deleted-dashboard', 'status' => 'banned']);
        $users->save($deleted);
        $users->db->table('users')->where('id', $users->getInsertID())->update(['created_at' => '2026-10-10 01:02:03']);
        $users->delete($users->getInsertID());
        $viewer = $users->findById($ids[1]);
        $viewer->addPermission('users.view', 'email-deliveries.view', 'operation-audit.view');
        $deliveries = new EmailDeliveryLogModel();
        $deliveries->createQueued('queued-one@example.com', 'Queued one', null);
        $deliveries->createQueued('queued-two@example.com', 'Queued two', null);
        $failed = $deliveries->createQueued('failed@example.com', 'Failed mail', null);
        $deliveries->markQueueFailed($failed);
        $sent = $deliveries->createQueued('sent@example.com', 'Sent mail', null);
        $deliveries->recordAttempt($sent, 1, null);
        $this->assertSame(['queued' => 2, 'failed' => 1, 'sent' => 1, 'total' => 4], $deliveries->dashboardCounts());

        for ($index = 0; $index < 7; $index++) {
            db_connect()->table('operation_audit_logs')->insert(['actor_id' => null, 'action' => 'POST', 'target_type' => 'users', 'target_id' => (string) $index,
                'path'                                                      => 'private-path-' . $index, 'result' => 'success', 'ip_address' => '192.0.2.123', 'user_agent' => 'PrivateDashboardAgent',
                'created_at'                                                => $index === 0 ? '2026-10-11 02:00:00' : '2026-10-10 01:02:03']);
        }
        $userItems = service('userdashboardprovider')->items($viewer);
        $this->assertSame(7, $userItems[0]['value']);
        $this->assertSame(1, $userItems[1]['value']);
        $rows = $userItems[2]['rows'];
        $this->assertSame('status-list', $userItems[2]['type']);
        $this->assertSame(['Dashboard.userNormal', 'Dashboard.userBanned', 'Dashboard.userNormal', 'Dashboard.userNormal', 'Dashboard.userNormal'], array_column($rows, 'status'));
        $this->assertSame('danger', $rows[1]['tone']);
        $this->assertSame('ban', $rows[1]['icon']);
        $this->assertSame(['dashboard0', 'dashboard6', 'dashboard5', 'dashboard4', 'dashboard3'], array_column($rows, 'title'));
        $this->assertSame('2026-10-11T02:00:00Z', $rows[0]['time']);
        $this->assertStringContainsString('LIMIT 5', (string) $users->db->getLastQuery());
        $auditItems = service('auditdashboardprovider')->items($viewer);
        $this->assertSame(['POST users #0', 'POST users #6', 'POST users #5', 'POST users #4', 'POST users #3'], array_column($auditItems[0]['rows'], 'title'));
        $this->assertStringContainsString('LIMIT 5', (string) db_connect()->getLastQuery());
        $sections = array_column(service('dashboard')->sections($viewer, 'zh-Hans'), null, 'id');
        $this->assertSame(2, $sections['email']['items'][0]['value']);
        $this->assertSame(1, $sections['email']['items'][1]['value']);
        $this->assertSame('/zh-Hans/admin/mail/deliveries?view=logs&status=queued', $sections['email']['items'][0]['link']['url']);
        $this->assertSame('/zh-Hans/admin/mail/deliveries?view=logs&status=failed', $sections['email']['items'][1]['link']['url']);
        $this->assertSame(['queued', 'failed', 'success-rate'], array_column($sections['email']['items'], 'id'));
        $this->assertSame(1, $sections['email']['items'][2]['value']);
        $this->assertSame(4, $sections['email']['items'][2]['max']);
        $this->assertSame('/zh-Hans/admin/mail/deliveries?view=logs&status=sent', $sections['email']['items'][2]['link']['url']);
        $this->assertSame('/zh-Hans/admin/users?q=dashboard0', $sections['users']['items'][2]['rows'][0]['link']['url']);
        $this->assertSame('/zh-Hans/admin/audit?type=users&target=0', $sections['audit']['items'][0]['rows'][0]['link']['url']);
        auth()->login($viewer);
        $dashboard = $this->get('/zh-Hans/admin/dashboard');
        $dashboard->assertOK();
        $dashboard->assertSee('POST users #0');

        foreach (['dashboard-users', 'dashboard-audit'] as $region) {
            $this->assertStringContainsString($region, $dashboard->response()->getBody());
        }

        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $dashboard->response()->getBody());
        $xpath = new DOMXPath($document);
        $this->assertCount(1, $xpath->query('//*[@data-dashboard-item="email:queued"]'));
        $this->assertCount(1, $xpath->query('//*[@data-dashboard-item="email:failed"]'));
        $this->assertCount(0, $xpath->query('//*[@data-dashboard-item="email:queue"]'));
        $this->assertCount(1, $xpath->query('//*[@id="dashboard-email"]'));
        $progress = $xpath->query('//*[@data-dashboard-item="email:success-rate"]//*[@role="progressbar"]')->item(0);
        $this->assertSame('1', $progress->getAttribute('aria-valuenow'));
        $this->assertSame('4', $progress->getAttribute('aria-valuemax'));
        $this->assertSame('width: 25.0%', $progress->getAttribute('style'));
        $this->assertSame('已完成 1 / 4', $progress->getAttribute('aria-valuetext'));
        $this->assertCount(1, $xpath->query('//*[@data-dashboard-item="users:recent"]//span[contains(@class,"bg-red-lt")]/span[text()="已禁用"]'));
        $this->assertCount(4, $xpath->query('//*[@data-dashboard-item="users:recent"]//span[contains(@class,"bg-green-lt")]/span[text()="正常"]'));

        foreach (['private-path-', '192.0.2.123', 'PrivateDashboardAgent', 'queued-one@example.com', 'failed@example.com'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $dashboard->response()->getBody());
        }
        $filtered = $this->get('/zh-Hans/admin/mail/deliveries?view=logs&status=failed');
        $filtered->assertSee('Failed mail');
        $filtered->assertDontSee('Queued one');
        $filteredUsers = $this->get('/zh-Hans/admin/users?q=dashboard0');
        $filteredUsers->assertSee('dashboard0');
        $filteredUsers->assertDontSee('dashboard3');
        $audit = $this->get('/zh-Hans/admin/audit?type=users&target=0');
        $audit->assertSee('private-path-0');
        $audit->assertDontSee('private-path-3');
        $deliveries->recordAttempt($failed, 2, null);
        $updated = service('emaildashboardprovider')->items($viewer);
        $this->assertSame(0, $updated[1]['value']);
        $this->assertSame(2, $updated[2]['value']);
        $this->assertSame(4, $updated[2]['max']);
        $viewer->removePermission('users.view', 'email-deliveries.view', 'operation-audit.view');
        $this->assertSame([], service('dashboard')->sections($viewer, 'zh-Hans'));
        $revoked = $this->get('/zh-Hans/admin/dashboard');
        $revoked->assertOK();
        $revoked->assertSee('暂无可显示的仪表盘内容。');

        foreach (['dashboard-users', 'dashboard-email', 'dashboard-audit'] as $region) {
            $this->assertStringNotContainsString($region, $revoked->response()->getBody());
        }
        $this->get('/zh-Hans/admin/users')->assertRedirect();
        $this->get('/zh-Hans/admin/mail/deliveries')->assertRedirect();
        $this->get('/zh-Hans/admin/audit')->assertRedirect();
    }

    public function testAdminDashboardProvidersDoNotQueryWithoutBusinessPermission(): void
    {
        $viewer = $this->createStub(User::class);
        $viewer->method('can')->willReturn(false);
        $users = $this->createMock(UserModel::class);
        $users->expects($this->never())->method('dashboardCount');
        $users->expects($this->never())->method('dashboardRecent');
        $email = $this->createMock(EmailDeliveryLogModel::class);
        $email->expects($this->never())->method('dashboardCounts');
        $audit = $this->createMock(OperationAuditModel::class);
        $audit->expects($this->never())->method('dashboardRecent');
        $this->assertSame([], (new UserDashboardProvider($users))->items($viewer));
        $this->assertSame([], (new EmailDashboardProvider($email))->items($viewer));
        $this->assertSame([], (new AuditDashboardProvider($audit))->items($viewer));

        foreach (['userdashboardprovider', 'emaildashboardprovider', 'auditdashboardprovider'] as $name) {
            $this->assertNotSame(service($name), service($name));
        }

        foreach (['en', 'zh-Hans', 'zh-Hant'] as $locale) {
            foreach (['usersTotal', 'usersScope', 'usersBanned', 'usersBannedScope', 'usersRecent', 'usersEmpty', 'userNormal', 'userBanned', 'emailSuccessRate', 'emailSuccessScope', 'emailQueued', 'emailQueuedScope', 'emailFailed', 'emailFailedScope', 'auditRecent', 'auditEmpty'] as $key) {
                $this->assertNotSame('Dashboard.' . $key, lang('Dashboard.' . $key, [], $locale));
            }
        }
    }

    protected function tearDown(): void
    {
        service('settings')->forget(MailTemplates::settingKey('invitation', 'en', 'subject'));
        service('settings')->forget(MailTemplates::settingKey('invitation', 'en', 'body'));
        Services::resetSingle('mailtemplates');
        Services::resetSingle('email');
        Services::resetSingle('queue');
        auth()->logout();
        parent::tearDown();
    }

    public function testProvisioningUsesIndependentModelsAndResetsValidationBetweenCalls(): void
    {
        $provisioning = service('userProvisioning');
        $this->assertNotSame($provisioning, service('userProvisioning'));
        $this->assertSame('invalid', $provisioning->create('x', 'invalid'));
        $this->assertSame('created', $provisioning->create(' first ', ' FIRST@example.com '));
        $first = auth()->getProvider()->findByCredentials(['email' => 'first@example.com']);
        $this->assertFalse($provisioning->usernameTaken('FIRST', $first->id));
        $this->assertTrue($provisioning->usernameTaken('FIRST'));
        $this->assertSame('username', $provisioning->create('FIRST', 'other@example.com'));
        $this->assertSame('duplicate', $provisioning->create('other', 'FIRST@example.com'));
        $this->assertSame('created', $provisioning->create('second', 'second@example.com'));
        $this->assertSame(2, auth()->getProvider()->countAllResults());
        $this->assertSame(['user'], $first->getGroups());
    }

    #[DataProvider('provideProvisioningRejectsInvalidAccountUpdates')]
    public function testProvisioningRejectsInvalidAccountUpdates(string $username, string $email, string $role, string $status): void
    {
        $provisioning = service('userProvisioning');
        $this->assertSame('created', $provisioning->create('target', 'target@example.com'));
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);
        $this->assertSame('invalid', $provisioning->updateAccount($user, $username, $email, $role, $status));
        $stored = auth()->getProvider()->findById($user->id);
        $this->assertSame('target', $stored->username);
        $this->assertSame('target@example.com', $stored->email);
        $this->assertSame(['user'], $stored->getGroups());
        $this->assertFalse($stored->isBanned());
        $this->assertSame('updated', $provisioning->updateAccount($stored, ' target ', ' TARGET@example.com ', 'user', 'enabled'));
    }

    public static function provideProvisioningRejectsInvalidAccountUpdates(): iterable
    {
        yield 'username' => ['x', 'changed@example.com', 'admin', 'banned'];

        yield 'email' => ['changed', 'invalid', 'admin', 'banned'];

        yield 'unknown role' => ['changed', 'changed@example.com', 'missing', 'banned'];

        yield 'superadmin' => ['changed', 'changed@example.com', 'superadmin', 'banned'];

        yield 'status' => ['changed', 'changed@example.com', 'admin', 'unknown'];

        yield 'encoding' => ["bad\xFF", 'changed@example.com', 'admin', 'banned'];
    }

    public function testProvisioningRequiresPersistedTargetAndPreservesUnchangedLegacyUsername(): void
    {
        $provisioning = service('userProvisioning');
        $this->assertSame('invalid', $provisioning->updateAccount(new AdminUser(), 'target', 'target@example.com', 'user', 'enabled'));
        $this->assertSame('created', $provisioning->create('legacy', 'legacy@example.com'));
        $user = auth()->getProvider()->findByCredentials(['email' => 'legacy@example.com']);
        db_connect()->table(config('Auth')->tables['users'])->where('id', $user->id)->update(['username' => 'legacy_name']);
        $user = auth()->getProvider()->findById($user->id);
        $hash = $user->getEmailIdentity()->secret2;
        $this->assertSame('updated', $provisioning->updateAccount($user, 'legacy_name', ' NEW@example.com ', 'developer', 'banned'));
        $stored = auth()->getProvider()->findById($user->id);
        $this->assertSame('new@example.com', $stored->email);
        $this->assertSame($hash, $stored->getEmailIdentity()->secret2);
        $this->assertSame(['developer'], $stored->getGroups());
        $this->assertTrue($stored->isBanned());
        $this->assertSame('updated', $provisioning->updateAccount($stored, 'legacy_name', 'new@example.com', 'user', 'enabled'));
        $this->assertFalse(auth()->getProvider()->findById($user->id)->isBanned());
    }

    public function testProvisioningRollsBackFailedIdentityAndCanRetryOnSameConnection(): void
    {
        $db         = db_connect();
        $identities = new UserIdentityModel($db);
        $failing    = $this->getMockBuilder(UserIdentityModel::class)->setConstructorArgs([$db])->onlyMethods(['create'])->getMock();
        $failing->expects($this->atLeastOnce())->method('create')->willReturnCallback(static function ($data) use ($identities): void {
            if ($data['secret'] === 'failed@example.com') {
                $data['secret2'] = str_repeat('x', 300);
            }
            $identities->create($data);
        });
        $provider     = auth()->getProvider()::class;
        $provisioning = $this->provisioningWith(static fn () => new $provider($db), $failing);

        $this->assertSame('save', $provisioning->create('failed', 'failed@example.com'));

        foreach (['users', 'identities', 'groups_users'] as $table) {
            $this->assertSame(0, $db->table(config('Auth')->tables[$table])->countAllResults());
        }
        $this->assertSame(0, $db->transDepth);
        $this->assertTrue($db->transStatus());
        $this->assertSame('created', $provisioning->create('retry', 'retry@example.com'));
        $stored = auth()->getProvider()->findByCredentials(['email' => 'retry@example.com']);
        $this->assertNotNull($stored);
        $this->assertSame(['user'], $stored->getGroups());
    }

    public function testProvisioningRollsBackAccountAndGroupsWhenStatusSaveReturnsFalse(): void
    {
        $this->assertSame('created', service('userProvisioning')->create('target', 'target@example.com'));
        $db             = db_connect();
        $provider       = auth()->getProvider()::class;
        $users          = new $provider($db);
        $failing        = $this->getMockBuilder($provider)->setConstructorArgs([$db])->onlyMethods(['save'])->getMock();
        $failStatusSave = true;
        $failing->expects($this->atLeastOnce())->method('save')->willReturnCallback(static function ($user) use ($users, &$failStatusSave): bool {
            return ! ($failStatusSave && $user->isBanned()) && $users->save($user);
        });
        $provisioning = $this->provisioningWith(static fn () => $failing);
        $user         = $users->findByCredentials(['email' => 'target@example.com']);
        $hash         = $user->getEmailIdentity()->secret2;
        $this->assertSame('save', $provisioning->updateAccount($user, 'changed', 'changed@example.com', 'admin', 'banned'));
        $stored = $users->findById($user->id);
        $this->assertSame('target', $stored->username);
        $this->assertSame('target@example.com', $stored->email);
        $this->assertSame($hash, $stored->getEmailIdentity()->secret2);
        $this->assertSame(['user'], $stored->getGroups());
        $this->assertFalse($stored->isBanned());
        $this->assertSame('target', $user->username);
        $failStatusSave = false;
        $this->assertSame('updated', $provisioning->updateAccount($stored, 'changed', 'changed@example.com', 'admin', 'banned'));
        $stored = $users->findById($user->id);
        $this->assertSame('changed@example.com', $stored->email);
        $this->assertSame(['admin'], $stored->getGroups());
        $this->assertTrue($stored->isBanned());
    }

    private function provisioningWith(Closure $users, ?UserIdentityModel $identities = null): UserProvisioning
    {
        return new UserProvisioning(
            $users,
            $identities ?? new UserIdentityModel(db_connect()),
            Services::validation(null, false),
            service('passwords'),
            config('Auth')->usernameValidationRules,
            config('Auth')->emailValidationRules,
            array_keys(service('settings')->get('AuthGroups.groups')),
        );
    }

    public function testProvisioningRecoversAfterDatabaseUpdateConflict(): void
    {
        $provisioning = service('userProvisioning');
        $this->assertSame('created', $provisioning->create('owner', 'owner@example.com'));
        $this->assertSame('created', $provisioning->create('target', 'target@example.com'));
        $db       = db_connect();
        $provider = auth()->getProvider()::class;
        $failing  = $this->getMockBuilder($provider)->setConstructorArgs([$db])->onlyMethods(['findByCredentials'])->getMock();
        $failing->expects($this->atLeastOnce())->method('findByCredentials')->willReturnCallback(static fn (array $credentials) => ($credentials['email'] ?? null) === 'owner@example.com' ? null : (new $provider($db))->findByCredentials($credentials));
        $provisioning = $this->provisioningWith(static fn () => $failing);
        $user         = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);

        $this->assertSame('save', $provisioning->updateAccount($user, 'changed', 'owner@example.com', 'admin', 'banned'));
        $stored = auth()->getProvider()->findById($user->id);
        $this->assertSame('target', $stored->username);
        $this->assertSame('target@example.com', $stored->email);
        $this->assertSame(['user'], $stored->getGroups());
        $this->assertFalse($stored->isBanned());
        $this->assertSame(0, $db->transDepth);
        $this->assertTrue($db->transStatus());
        $this->assertSame('updated', $provisioning->updateAccount($stored, 'changed', 'changed@example.com', 'admin', 'banned'));
        $this->assertSame('changed@example.com', auth()->getProvider()->findById($user->id)->email);
    }

    public function testProvisioningPreservesFailureOfCallerOwnedTransaction(): void
    {
        $db         = db_connect();
        $identities = new UserIdentityModel($db);
        $failing    = $this->getMockBuilder(UserIdentityModel::class)->setConstructorArgs([$db])->onlyMethods(['create'])->getMock();
        $failing->expects($this->atLeastOnce())->method('create')->willReturnCallback(static function ($data) use ($identities): void {
            $data['secret2'] = str_repeat('x', 300);
            $identities->create($data);
        });
        $provider     = auth()->getProvider()::class;
        $provisioning = $this->provisioningWith(static fn () => new $provider($db), $failing);
        $db->transBegin();

        try {
            $this->assertSame('save', $provisioning->create('failed', 'failed@example.com'));
            $this->assertSame(1, $db->transDepth);
            $this->assertFalse($db->transStatus());
        } finally {
            $db->transRollback();
            $db->resetTransStatus();
        }
        $this->assertSame(0, auth()->getProvider()->countAllResults());
    }

    public function testUserListAndExportRequireViewPermission(): void
    {
        $this->get('/en/admin/users')->assertRedirect();
        $this->get('/en/admin/users/export')->assertRedirect();
        $this->loginAs('admin');
        $this->get('/en/admin/users')->assertRedirect();
        $this->get('/en/admin/users/export')->assertRedirect();
    }

    public function testEditingOrdinaryUsersDoesNotRequireManagingAdmins(): void
    {
        $this->loginAs('admin');
        auth()->user()->addPermission('users.edit');
        $this->createUser('ordinarytarget', 'ordinarytarget@example.com');
        $ordinary = auth()->getProvider()->findByCredentials(['email' => 'ordinarytarget@example.com']);
        $this->createUser('admintarget', 'admintarget@example.com');
        $administrator = auth()->getProvider()->findByCredentials(['email' => 'admintarget@example.com']);
        $administrator->addGroup('admin');
        $this->get('/en/admin/users/' . $ordinary->id . '/edit')->assertOK();
        $this->get('/en/admin/users/' . $administrator->id . '/edit')->assertStatus(404);
        $before = db_connect()->table(config('Auth')->tables['users'])->where('id', $administrator->id)->get()->getRowArray();
        $this->post('/en/admin/users/' . $administrator->id . '/edit', [
            csrf_token() => csrf_hash(), 'username' => 'takeover', 'email' => 'takeover@example.com', 'role' => 'user', 'status' => 'banned',
        ])->assertStatus(404);
        $this->assertSame($before, db_connect()->table(config('Auth')->tables['users'])->where('id', $administrator->id)->get()->getRowArray());
        $this->assertSame('admintarget@example.com', auth()->getProvider()->findById($administrator->id)->email);
        $this->post('/en/admin/users/' . $ordinary->id . '/edit', [
            csrf_token() => csrf_hash(), 'username' => 'ordinarytarget', 'email' => 'ordinarytarget@example.com', 'role' => 'admin', 'status' => 'enabled',
        ])->assertRedirect();
        $this->assertArrayHasKey('role', session('user_errors'));
        $this->assertFalse(auth()->getProvider()->findById($ordinary->id)->inGroup('admin'));
    }

    #[DataProvider('provideUserOperationPermissionsAreIndependent')]
    public function testUserOperationPermissionsAreIndependent(string $permission): void
    {
        $this->loginAs('user');
        auth()->user()->addPermission($permission);
        $this->createUser('permissiontarget', 'permissiontarget@example.com');
        $target = auth()->getProvider()->findByCredentials(['email' => 'permissiontarget@example.com']);
        $target->addGroup('user');
        $denied = config('Auth')->permissionDeniedRedirect();

        foreach (['users.view' => '/en/admin/users', 'users.create' => '/en/admin/users/create', 'users.edit' => '/en/admin/users/' . $target->id . '/edit'] as $required => $route) {
            $result = $this->get($route);
            if ($permission === $required) {
                $result->assertOK();
            } else {
                $result->assertRedirectTo($denied);
            }
        }

        foreach (['users.view' => '/en/admin/users/export', 'users.create' => '/en/admin/users/template'] as $required => $route) {
            $result = $this->get($route);
            if ($permission === $required) {
                $result->assertStatus(200);
            } else {
                $result->assertRedirectTo($denied);
            }
        }
        $beforeCount = auth()->getProvider()->countAllResults();
        $import      = $this->post('/en/admin/users/import', [csrf_token() => csrf_hash()]);
        if ($permission === 'users.create') {
            $import->assertRedirectTo('/en/admin/users/create');
            $this->assertSame(lang('Admin.invalidUserCsv'), session('alert')['message']);
        } else {
            $import->assertRedirectTo($denied);
        }
        $this->assertSame($beforeCount, auth()->getProvider()->countAllResults());

        $this->createUser('permissioninvite', 'permissioninvite@example.com');
        $invited  = auth()->getProvider()->findByCredentials(['email' => 'permissioninvite@example.com']);
        $settings = service('settings')->getMany(['Email.fromEmail', 'Auth.allowMagicLinkLogins']);
        service('settings')->setMany(['Email.fromEmail' => 'sender@example.com', 'Auth.allowMagicLinkLogins' => true]);
        $email = $this->createMock(QueuedEmail::class);
        if ($permission === 'users.edit') {
            $email->expects($this->once())->method('setInvitationUserId')->with($invited->id);
            $email->expects($this->once())->method('send')->willReturn(true);
        } else {
            $email->expects($this->never())->method('setInvitationUserId');
            $email->expects($this->never())->method('send');
        }
        Services::injectMock('email', $email);

        try {
            $result = $this->post('/en/admin/users/' . $invited->id . '/invite', [csrf_token() => csrf_hash()]);
            if ($permission === 'users.edit') {
                $result->assertRedirectTo('/en/admin/users/' . $invited->id . '/edit');
                $this->assertSame(lang('Admin.userInviteQueued'), session('alert')['message']);
            } else {
                $result->assertRedirectTo($denied);
            }
        } finally {
            service('settings')->setMany($settings);
            Services::resetSingle('email');
        }

        $created = $this->post('/en/admin/users/create', [csrf_token() => csrf_hash(), 'username' => 'permissioncreated', 'email' => 'permissioncreated@example.com']);
        if ($permission === 'users.create') {
            $created->assertRedirectTo('/en/admin/users/create');
            $this->assertSame(['user'], auth()->getProvider()->findByCredentials(['email' => 'permissioncreated@example.com'])->getGroups());
        } else {
            $created->assertRedirectTo($denied);
            $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'permissioncreated@example.com']));
        }

        $updated = $this->post('/en/admin/users/' . $target->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'updatedtarget', 'email' => 'permissiontarget@example.com', 'role' => 'user', 'status' => 'banned']);
        if ($permission === 'users.edit') {
            $updated->assertRedirectTo('/en/admin/users/' . $target->id . '/edit');
            $this->assertSame('updatedtarget', auth()->getProvider()->findById($target->id)->username);
            $this->assertTrue(auth()->getProvider()->findById($target->id)->isBanned());
        } else {
            $updated->assertRedirectTo($denied);
            $this->assertSame('permissiontarget', auth()->getProvider()->findById($target->id)->username);
            $this->assertFalse(auth()->getProvider()->findById($target->id)->isBanned());
        }
    }

    public static function provideUserOperationPermissionsAreIndependent(): iterable
    {
        return [['users.view'], ['users.create'], ['users.edit'], ['users.manage-admins']];
    }

    public function testOnlyAdminRoleNeedsAdditionalManagementPermission(): void
    {
        $originalMatrix      = setting('AuthGroups.matrix');
        $matrix              = $originalMatrix;
        $matrix['admin']     = ['users.edit'];
        $matrix['developer'] = ['users.edit'];
        setting('AuthGroups.matrix', $matrix);

        try {
            $this->loginAs('admin');

            foreach (['admin', 'developer'] as $role) {
                $this->createUser('target' . $role, 'target' . $role . '@example.com');
                $target = auth()->getProvider()->findByCredentials(['email' => 'target' . $role . '@example.com']);
                $target->addGroup($role);
                $route = '/en/admin/users/' . $target->id . '/edit';
                if ($role === 'admin') {
                    $this->get($route)->assertStatus(404);
                    auth()->user()->addPermission('users.manage-admins');
                    $this->createUser('promotedordinary', 'promotedordinary@example.com');
                    $promoted = auth()->getProvider()->findByCredentials(['email' => 'promotedordinary@example.com']);
                    $promoted->addGroup('user');
                    $this->post('/en/admin/users/' . $promoted->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'promotedordinary', 'email' => 'promotedordinary@example.com', 'role' => 'admin', 'status' => 'enabled'])->assertRedirectTo('/en/admin/users/' . $promoted->id . '/edit');
                    $this->assertSame(['admin'], auth()->getProvider()->findById($promoted->id)->getGroups());
                }
                $page = $this->get($route);
                $page->assertOK();
                $this->post($route, [csrf_token() => csrf_hash(), 'username' => 'changed' . $role, 'email' => 'target' . $role . '@example.com', 'role' => $role, 'status' => 'enabled'])->assertRedirect();
                $this->assertSame('changed' . $role, auth()->getProvider()->findById($target->id)->username);
                if ($role === 'admin') {
                    auth()->user()->removePermission('users.manage-admins');
                }
            }
        } finally {
            setting('AuthGroups.matrix', $originalMatrix);
        }
    }

    public function testUploadHistoryWithoutBusinessPermissionIsEmpty(): void
    {
        $this->loginAs('user');
        auth()->user()->addPermission('users.view');
        $announcementId = (int) (new AnnouncementModel())->insert(['title' => 'Hidden upload', 'body' => 'Body', 'status' => 'draft']);
        (new AttachmentModel())->insert(['resource_type' => 'announcement', 'resource_id' => $announcementId, 'filename' => bin2hex(random_bytes(16)) . '.txt', 'original_name' => 'hidden-upload.txt', 'mime_type' => 'text/plain', 'size_bytes' => 7, 'uploaded_by' => auth()->id()]);
        $history = service('uploadhistory');
        $this->assertNotSame($history, service('uploadhistory'));
        $result = $history->paginate((int) auth()->id(), auth()->user());
        $this->assertSame([], $result['attachments']);
        $this->assertSame(0, $result['pager']->getTotal());
        $page = $this->get('/en/admin/users/' . auth()->id() . '/attachments');
        $page->assertOK();
        $page->assertSee(lang('Admin.uploadHistoryEmpty'));
        $this->assertStringNotContainsString('enctype="multipart/form-data"', $page->response()->getBody());
    }

    public function testReadOnlyUserUploadHistoryRequiresBusinessAndTargetAccess(): void
    {
        $this->loginAs('user');
        auth()->user()->addPermission('users.view');
        $this->createUser('readonlytarget', 'readonlytarget@example.com');
        $target         = auth()->getProvider()->findByCredentials(['email' => 'readonlytarget@example.com']);
        $announcementId = (int) (new AnnouncementModel())->insert(['title' => 'Visible record', 'body' => 'Body', 'status' => 'published', 'published_at' => '2026-10-01 12:00:00']);
        (new AttachmentModel())->insert(['resource_type' => 'announcement', 'resource_id' => $announcementId, 'filename' => bin2hex(random_bytes(16)) . '.txt', 'original_name' => 'readonly.txt', 'mime_type' => 'text/plain', 'size_bytes' => 2048, 'uploaded_by' => $target->id]);
        $route = '/en/admin/users/' . $target->id . '/attachments';
        $list  = $this->get('/en/admin/users');
        $list->assertOK();
        $listBody = $list->response()->getBody();
        $this->assertStringNotContainsString('href="/en/admin/users/create"', $listBody);
        $this->assertStringNotContainsString('action="/en/admin/users/import"', $listBody);
        $this->assertStringNotContainsString('href="/en/admin/users/' . $target->id . '/edit"', $listBody);
        $attachments = $this->get($route);
        $attachments->assertOK();
        $this->assertStringNotContainsString('readonly.txt', $attachments->response()->getBody());
        auth()->user()->addPermission('announcements.access');
        $attachments = $this->get($route);
        $attachments->assertOK();
        $attachments->assertSee('readonly.txt');
        $this->assertStringNotContainsString('enctype="multipart/form-data"', $attachments->response()->getBody());
        $this->assertStringNotContainsString('/remove', $attachments->response()->getBody());
        $target->addGroup('admin');
        $this->get($route)->assertStatus(404);
        auth()->user()->addPermission('users.edit');
        $this->get($route)->assertStatus(404);
        auth()->user()->addPermission('users.manage-admins');
        $this->get($route)->assertOK();
    }

    public function testDelegatedUserManagerCannotAccessOtherSuperadminAttachments(): void
    {
        $this->loginAs('admin');
        auth()->user()->addPermission('users.view', 'users.edit', 'users.manage-admins');
        $this->createUser('superattachmenttarget', 'superattachmenttarget@example.com');
        $target = auth()->getProvider()->findByCredentials(['email' => 'superattachmenttarget@example.com']);
        $target->addGroup('superadmin');
        $filename  = bin2hex(random_bytes(16)) . '.txt';
        $directory = WRITEPATH . 'uploads/attachments/';
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        file_put_contents($directory . $filename, 'protected content');
        $model        = new AttachmentModel();
        $attachmentId = (int) $model->insert(['resource_type' => 'user', 'resource_id' => $target->id, 'filename' => $filename, 'original_name' => 'protected.txt', 'mime_type' => 'text/plain', 'size_bytes' => 17, 'uploaded_by' => auth()->id()]);
        $route        = '/en/admin/users/' . $target->id . '/attachments';

        try {
            $page = $this->get('/en/admin/users');
            $page->assertOK();
            $this->assertStringNotContainsString('href="' . $route . '"', $page->response()->getBody());
            $this->get($route)->assertStatus(404);
            $this->get($route . '/' . $attachmentId)->assertStatus(404);
            $this->post($route, [csrf_token() => csrf_hash()])->assertStatus(404);
            $this->post($route . '/' . $attachmentId . '/remove', [csrf_token() => csrf_hash()])->assertStatus(404);
            $this->assertSame(1, $model->countAllResults());
            $this->assertNotNull($model->find($attachmentId));
            $this->assertSame('protected content', file_get_contents($directory . $filename));
        } finally {
            unlink($directory . $filename);
        }
    }

    public function testUploadHistoryRequiresUserReadPermission(): void
    {
        $this->get('/en/admin/users/1/attachments')->assertRedirect();
        $this->loginAs('user');
        $this->get('/en/admin/users/1/attachments')->assertRedirect();
        $this->assertSame(0, (new AttachmentModel())->countAllResults());
        $this->loginAs('superadmin');
        $this->get('/en/admin/users/' . auth()->id() . '/attachments')->assertStatus(200);
    }

    public function testProtectedAccountsAllowUploadHistoryButRemainUneditable(): void
    {
        $this->loginAs('superadmin');
        $actorId = (int) auth()->id();
        $this->createUser('protectedattachment', 'protectedattachment@example.com');
        $protected = auth()->getProvider()->findByCredentials(['email' => 'protectedattachment@example.com']);
        $protected->addGroup('superadmin');
        $this->createUser('multiroleattachment', 'multiroleattachment@example.com');
        $multiple = auth()->getProvider()->findByCredentials(['email' => 'multiroleattachment@example.com']);
        $multiple->addGroup('admin', 'beta');
        $listBody       = $this->get('/en/admin/users')->response()->getBody();
        $announcementId = (int) (new AnnouncementModel())->insert(['title' => 'Protected user upload', 'body' => 'Body', 'status' => 'draft']);

        foreach ([$actorId, (int) $protected->id, (int) $multiple->id] as $resourceId) {
            $filename = 'protected-' . $resourceId . '.txt';
            (new AttachmentModel())->insert(['resource_type' => 'announcement', 'resource_id' => $announcementId, 'filename' => bin2hex(random_bytes(16)) . '.txt', 'original_name' => $filename, 'mime_type' => 'text/plain', 'size_bytes' => 7, 'uploaded_by' => $resourceId]);
            $this->assertStringContainsString('href="/en/admin/users/' . $resourceId . '/attachments"', $listBody);
            $page = $this->get('/en/admin/users/' . $resourceId . '/attachments');
            $page->assertOK();
            $page->assertSee($filename);
            $this->assertStringContainsString('href="/en/admin/users"', $page->response()->getBody());
            $this->assertStringNotContainsString('enctype="multipart/form-data"', $page->response()->getBody());
            $this->get('/en/admin/users/' . $resourceId . '/edit')->assertStatus(404);
            $this->post('/en/admin/users/' . $resourceId, [csrf_token() => csrf_hash()])->assertStatus(404);
            $this->post('/en/admin/users/' . $resourceId . '/invite', [csrf_token() => csrf_hash()])->assertStatus(404);
        }
    }

    public function testAttachmentActionsRejectMissingUsers(): void
    {
        $this->loginAs('superadmin');
        $route = '/en/admin/users/99999999/attachments';
        $this->get($route)->assertStatus(404);
        $this->post($route, [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->get($route . '/1')->assertStatus(404);
        $this->post($route . '/1/remove', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->assertSame(0, (new AttachmentModel())->countAllResults());
    }

    public function testUserAttachmentWriteAndDownloadRoutesAreClosed(): void
    {
        $this->loginAs('superadmin');
        $route        = '/en/admin/users/' . auth()->id() . '/attachments';
        $model        = new AttachmentModel();
        $attachmentId = (int) $model->insert(['resource_type' => 'user', 'resource_id' => auth()->id(), 'filename' => bin2hex(random_bytes(16)) . '.txt', 'original_name' => 'legacy.txt', 'mime_type' => 'text/plain', 'size_bytes' => 7, 'uploaded_by' => auth()->id()]);
        $this->post($route, [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->get($route . '/' . $attachmentId)->assertStatus(404);
        $this->post($route . '/' . $attachmentId . '/remove', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->assertNotNull($model->find($attachmentId));
        $page = $this->get($route);
        $page->assertOK();
        $this->assertStringNotContainsString('legacy.txt', $page->response()->getBody());
    }

    public function testUploadHistoryFiltersByUploaderAndBusinessVisibilityBeforePagination(): void
    {
        $this->loginAs('user');
        auth()->user()->addPermission('users.view', 'announcements.access');
        $this->createUser('uploadtarget', 'uploadtarget@example.com');
        $target        = auth()->getProvider()->findByCredentials(['email' => 'uploadtarget@example.com']);
        $announcements = new AnnouncementModel();
        $published     = (int) $announcements->insert(['title' => '<b>Published record</b>', 'body' => 'Body', 'status' => 'published', 'published_at' => '2026-10-01 12:00:00']);
        $draft         = (int) $announcements->insert(['title' => 'Secret draft', 'body' => 'Body', 'status' => 'draft']);
        $model         = new AttachmentModel();
        $ids           = [];

        for ($index = 1; $index <= 21; $index++) {
            $ids[] = (int) $model->insert(['resource_type' => 'announcement', 'resource_id' => $published, 'filename' => bin2hex(random_bytes(16)) . '.txt', 'original_name' => 'visible-' . $index . '.txt', 'mime_type' => 'text/plain', 'size_bytes' => 2048, 'uploaded_by' => $target->id]);
        }

        foreach ([['announcement', $draft, 'draft-only.txt', $target->id], ['announcement', 99999999, 'orphan.txt', $target->id], ['unknown', $published, 'unknown.txt', $target->id], ['announcement', $published, 'other-uploader.txt', auth()->id()]] as [$type, $resourceId, $name, $uploaderId]) {
            $model->insert(['resource_type' => $type, 'resource_id' => $resourceId, 'filename' => bin2hex(random_bytes(16)) . '.txt', 'original_name' => $name, 'mime_type' => 'text/plain', 'size_bytes' => 2048, 'uploaded_by' => $uploaderId]);
        }

        $route = '/en/admin/users/' . $target->id . '/attachments';
        $page  = $this->get($route);
        $page->assertOK();
        $body = $page->response()->getBody();
        TableLayoutAssertions::assertTablesInCards($body, paginated: true);
        $this->assertStringContainsString('Upload history: 21 (1 - 20)', $body);
        $this->assertSame(1, substr_count($body, 'id="attachment-preview"'));
        $this->assertSame(1, substr_count($body, 'src="/static/js/attachment-preview.js"'));
        $page->assertSee('Announcements', 'td');
        $this->assertStringContainsString('visible-21.txt', $body);
        $this->assertStringNotContainsString('visible-1.txt', $body);

        foreach (['draft-only.txt', 'Secret draft', 'orphan.txt', 'unknown.txt', 'other-uploader.txt'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $body);
        }

        $this->assertStringContainsString('&lt;b&gt;Published record&lt;/b&gt;', $body);
        $this->assertStringContainsString('href="/en/admin/announcements/show/' . $published . '"', $body);
        $this->assertStringContainsString('href="/en/admin/announcements/' . $published . '/attachments/' . $ids[20] . '"', $body);
        $this->assertStringContainsString('href="/en/admin/announcements/' . $published . '/attachments/' . $ids[20] . '/preview" target="_blank" rel="noopener noreferrer"', $body);
        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath   = new DOMXPath($document);
        $preview = $xpath->query('//a[@data-attachment-preview]')->item(0);
        $this->assertSame('visible-21.txt', $preview->getAttribute('data-preview-name'));
        $this->assertSame('text/plain', $preview->getAttribute('data-preview-mime'));
        $this->assertSame('/en/admin/announcements/' . $published . '/attachments/' . $ids[20], $preview->getAttribute('data-preview-download'));
        $this->assertSame('tooltip', $preview->getAttribute('data-bs-toggle'));
        $this->assertSame(lang('Admin.attachmentPreview'), $preview->getAttribute('data-bs-title'));
        $download = $xpath->query('//table//a[@href="/en/admin/announcements/' . $published . '/attachments/' . $ids[20] . '"]')->item(0);
        $this->assertNotNull($download);
        $this->assertSame('tooltip', $download->getAttribute('data-bs-toggle'));
        $this->assertSame(lang('Admin.attachmentDownload'), $download->getAttribute('data-bs-title'));
        $this->assertSame(0, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " page-body ")]//form')->length);
        $this->assertStringContainsString('private, no-store', $page->response()->getHeaderLine('Cache-Control'));
        $result = service('uploadhistory')->paginate((int) $target->id, auth()->user());
        $this->assertSame(array_reverse(array_slice($ids, 1)), array_map(static fn (array $item): int => (int) $item['id'], $result['attachments']));
        $this->assertSame(21, $result['pager']->getTotal());
        $secondPage = $this->get($route . '?page=2');
        $secondPage->assertOK();
        TableLayoutAssertions::assertTablesInCards($secondPage->response()->getBody(), paginated: true);
        $secondPage->assertSee('visible-1.txt');
        $this->assertStringNotContainsString('visible-21.txt', $secondPage->response()->getBody());

        auth()->user()->removePermission('announcements.access');
        $result = service('uploadhistory')->paginate((int) $target->id, auth()->user());
        $this->assertSame([], $result['attachments']);
        $this->assertSame(0, $result['pager']->getTotal());
        auth()->user()->addPermission('announcements.manage');
        $result = service('uploadhistory')->paginate((int) $target->id, auth()->user());
        $this->assertSame(22, $result['pager']->getTotal());
        $this->assertSame('draft-only.txt', $result['attachments'][0]['original_name']);
    }

    public function testUploadHistoryCombinesDifferentBusinessRecordsAndPreservesLocale(): void
    {
        $this->loginAs('superadmin');
        $viewer           = auth()->user();
        $viewer->timezone = 'Asia/Shanghai';
        auth()->getProvider()->save($viewer);
        auth()->logout();
        auth()->login(auth()->getProvider()->findById($viewer->id));
        $announcements = new AnnouncementModel();
        $model         = new AttachmentModel();
        $uploads       = [];

        foreach (['First record', 'Second record'] as $title) {
            $resourceId = (int) $announcements->insert(['title' => $title, 'body' => 'Body', 'status' => 'draft']);
            $uploads[]  = (int) $model->insert(['resource_type' => 'announcement', 'resource_id' => $resourceId, 'filename' => bin2hex(random_bytes(16)) . '.txt', 'original_name' => '<script>.txt', 'mime_type' => 'text/plain', 'size_bytes' => 2048, 'uploaded_by' => auth()->id()]);
        }

        $result = service('uploadhistory')->paginate((int) auth()->id(), auth()->user());
        $this->assertSame(array_reverse($uploads), array_map(static fn (array $item): int => (int) $item['id'], $result['attachments']));
        db_connect()->table('admin_attachments')->whereIn('id', $uploads)->update(['created_at' => '2026-10-01 12:00:00']);
        $page = $this->get('/zh-Hans/admin/users/' . auth()->id() . '/attachments');
        $page->assertOK();
        $page->assertSee('First record');
        $page->assertSee('Second record');
        $body = $page->response()->getBody();
        $this->assertStringContainsString('&lt;script&gt;.txt', $body);
        $this->assertStringNotContainsString('<script>.txt', $body);
        $this->assertStringContainsString('href="/zh-Hans/admin/announcements/', $body);
        $this->assertStringContainsString('上传时间', $body);
        $page->assertSee('所属业务', 'th');
        $page->assertSee('公告', 'td');
        $this->assertStringContainsString('2.0 KB', $body);
        $this->assertStringContainsString('2026-10-01 20:00:00', $body);
    }

    public function testUserListAndExportShareSearchFilter(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('searchable', 'searchable@example.com');
        $this->createUser('elsewhere', 'elsewhere@example.com');

        $page = $this->get('/en/admin/users?q=searchable%40example.com');
        $page->assertOK();
        TableLayoutAssertions::assertTablesInCards($page->response()->getBody(), paginated: true);
        $page->assertSee('searchable@example.com');
        $this->assertStringNotContainsString('elsewhere@example.com', $page->response()->getBody());

        $export = $this->get('/en/admin/users/export?q=searchable%40example.com');
        $export->assertOK();
        $this->assertStringContainsString('searchable@example.com', $export->response()->getBody());
        $this->assertStringNotContainsString('elsewhere@example.com', $export->response()->getBody());

        $this->createUser('Mixed.Name', 'Mixed.Address@example.com');

        foreach (['mixed.name', 'MIXED.ADDRESS@EXAMPLE.COM'] as $query) {
            $page = $this->get('/en/admin/users?q=' . rawurlencode($query));
            $page->assertSee('Mixed.Address@example.com');
            $export = $this->get('/en/admin/users/export?q=' . rawurlencode($query));
            $this->assertStringContainsString('Mixed.Address@example.com', $export->response()->getBody());
        }
    }

    public function testUserListAndExportShareCreatedDateRange(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('dateincluded', 'dateincluded@example.com');
        $this->createUser('dateexcluded', 'dateexcluded@example.com');
        $table = db_connect()->table(config('Auth')->tables['users']);
        $table->where('username', 'dateincluded')->update(['created_at' => '2026-10-02 23:59:59']);
        $table->where('username', 'dateexcluded')->update(['created_at' => '2026-10-03 00:00:00']);
        $parameters = '?q=date&created_from=2026-10-01&created_to=2026-10-02&sort=email&direction=ASC';
        $page       = $this->get('/en/admin/users' . $parameters);
        $page->assertOK();
        $page->assertSee('dateincluded@example.com');
        $this->assertStringNotContainsString('dateexcluded@example.com', $page->response()->getBody());
        $this->assertSame(4, substr_count($page->response()->getBody(), 'name="created_from"'));
        $this->assertStringContainsString('created_to=2026-10-02', $page->response()->getBody());
        $document = new DOMDocument();
        $document->loadHTML($page->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        foreach (['q' => 'date', 'created_from' => '2026-10-01', 'created_to' => '2026-10-02'] as $name => $value) {
            $this->assertSame(3, $xpath->query('//thead//input[@name="' . $name . '" and @value="' . $value . '"]')->length);
        }
        $this->assertSame(1, $xpath->query('//thead//th[@aria-sort="ascending"]')->length);
        $this->assertSame(1, $xpath->query('//thead//th[.//button[@value="email"]]//input[@name="direction" and @value="DESC"]')->length);
        $export = $this->get('/en/admin/users/export' . $parameters);
        $export->assertOK();
        $this->assertStringContainsString('dateincluded@example.com', $export->response()->getBody());
        $this->assertStringNotContainsString('dateexcluded@example.com', $export->response()->getBody());
    }

    public function testEmptyDateFilteredListOffersClearFilters(): void
    {
        $this->loginAs('superadmin');
        $page = $this->get('/en/admin/users?created_from=2099-01-01');
        $page->assertOK();
        TableLayoutAssertions::assertTablesInCards($page->response()->getBody(), paginated: true);
        $page->assertSee(lang('Admin.userClear'), 'tbody');
    }

    public function testUserListDisplaysCreationTimeInViewerTimezone(): void
    {
        $this->loginAs('superadmin');
        $viewer           = auth()->user();
        $viewer->timezone = 'Asia/Shanghai';
        auth()->getProvider()->save($viewer);
        $this->createUser('dateduser', 'dateduser@example.com');
        $created = auth()->getProvider()->findByCredentials(['email' => 'dateduser@example.com']);

        $page = $this->get('/en/admin/users?q=dateduser');
        $page->assertOK();
        $this->assertStringContainsString('<td>' . $viewer->formatDateTime($created->created_at) . '</td>', $page->response()->getBody());
    }

    public function testExportAcceptsLimitAndRejectsOneMoreMatchingUser(): void
    {
        $this->loginAs('superadmin');
        $rows = [];

        for ($number = 0; $number < 10000; $number++) {
            $rows[] = ['username' => 'exportlimit' . $number, 'active' => 1];
        }
        $table = db_connect()->table(config('Auth')->tables['users']);
        $table->insertBatch($rows);

        $export = $this->get('/en/admin/users/export?q=exportlimit');
        $export->assertOK();
        $this->assertSame(10001, substr_count($export->response()->getBody(), "\n"));

        $table->insert(['username' => 'exportlimit10000', 'active' => 1]);
        $export = $this->get('/en/admin/users/export?q=exportlimit');
        $export->assertStatus(413);
        $this->assertSame(lang('Admin.exportLimit'), $export->response()->getBody());
        $this->assertStringNotContainsString('username,email', $export->response()->getBody());
    }

    public function testUserListShowsEditActionAndProtectedAccountReason(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('editable', 'editable@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'editable@example.com']);
        $this->createUser('protected', 'protected@example.com');
        $protected = auth()->getProvider()->findByCredentials(['email' => 'protected@example.com']);
        $protected->addGroup('superadmin');

        $page = $this->get('/en/admin/users');
        $page->assertOK();
        $body = $page->response()->getBody();
        $this->assertStringContainsString('href="' . route_to('admin/users/edit', $user->id) . '"', $body);
        $this->assertStringNotContainsString('href="' . route_to('admin/users/edit', auth()->id()) . '"', $body);
        $this->assertStringNotContainsString('href="' . route_to('admin/users/edit', $protected->id) . '"', $body);
        $this->assertStringContainsString('data-bs-target="#user-permissions-' . $protected->id . '"', $body);
        $this->assertStringContainsString('data-bs-target="#user-permissions-' . auth()->id() . '"', $body);
        $this->assertStringContainsString('Your account', $body);
        $this->assertStringContainsString('Protected account', $body);
        $this->assertStringContainsString('Enabled', $body);
        $this->assertStringContainsString('class="form-label required" for="user-file"', $body);
        $this->assertStringContainsString('id="user-csv-hint" class="form-hint"', $body);
        $document = new DOMDocument();
        @$document->loadHTML($body);
        $xpath   = new DOMXPath($document);
        $buttons = $xpath->query('//table//a[contains(@class, "btn-icon")] | //table//button[contains(@class, "btn-icon") and not(@data-bs-toggle="offcanvas")]');
        $this->assertGreaterThan(0, $buttons->length);

        foreach ($buttons as $button) {
            $this->assertSame('', $button->getAttribute('title'));
            $this->assertNotSame('', $button->getAttribute('aria-label'));
            $this->assertSame('tooltip', $button->getAttribute('data-bs-toggle'));
            $this->assertSame($button->getAttribute('aria-label'), $button->getAttribute('data-bs-title'));
        }

        $panels = $xpath->query('//button[@data-bs-toggle="offcanvas"]');
        $this->assertGreaterThan(0, $panels->length);

        foreach ($panels as $button) {
            $this->assertSame('span', $button->parentNode->nodeName);
            $this->assertSame('tooltip', $button->parentNode->getAttribute('data-bs-toggle'));
            $this->assertSame(lang('Admin.viewUserPermissions'), $button->parentNode->getAttribute('data-bs-title'));
        }
        $closeButtons = $xpath->query('//button[@data-bs-dismiss="offcanvas"]');
        $this->assertGreaterThan(0, $closeButtons->length);

        foreach ($closeButtons as $button) {
            $this->assertSame('tooltip', $button->getAttribute('data-bs-toggle'));
            $this->assertSame(lang('Admin.close'), $button->getAttribute('data-bs-title'));
        }

        $empty = $this->get('/en/admin/users?q=no-such-user');
        $empty->assertOK();
        $this->assertStringContainsString('No users found.', $empty->response()->getBody());
        $this->assertStringContainsString('Clear', $empty->response()->getBody());
    }

    public function testUserListShowsConfiguredRoleTitles(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('developeruser', 'developer@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'developer@example.com']);
        $user->syncGroups('developer');

        $page = $this->get('/en/admin/users?q=developeruser');
        $page->assertOK();
        $body = $page->response()->getBody();
        $this->assertStringContainsString('<th scope="col">' . lang('Admin.userRole') . '</th>', $body);
        $this->assertStringContainsString('<td>Developer</td>', $body);

        $empty = $this->get('/en/admin/users?q=no-such-user');
        $this->assertStringContainsString('<tr><td colspan="7"', $empty->response()->getBody());
    }

    public function testUserListShowsEffectiveCatalogPermissions(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('grantviewer', 'grantviewer@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'grantviewer@example.com']);
        $user->syncGroups('beta');
        $user->addPermission('users.create');
        $originalMatrix   = setting('AuthGroups.matrix');
        $matrix           = $originalMatrix;
        $matrix['beta'][] = 'admin.*';
        $matrix['beta'][] = 'unlisted.*';
        setting('AuthGroups.matrix', $matrix);

        try {
            $page = $this->get('/en/admin/users?q=grantviewer');
            $page->assertOK();
            $body = $page->response()->getBody();
            $this->assertStringContainsString('beta.access', $body);
            $this->assertStringContainsString('users.create', $body);
            $this->assertStringContainsString('admin.settings', $body);
            $this->assertStringContainsString('Permissions: 4', $body);
            $this->assertStringNotContainsString('users.edit', $body);
            $this->assertStringNotContainsString('unlisted.*', $body);
            $this->assertStringContainsString('data-bs-toggle="offcanvas" data-bs-target="#user-permissions-' . $user->id . '"', $body);
            $this->assertStringContainsString('<th scope="col" class="text-end">Actions</th>', $body);
            $this->assertStringNotContainsString('<th scope="col">Effective permissions</th>', $body);
            $this->assertStringContainsString('id="user-permissions-' . $user->id . '" aria-labelledby="user-permissions-title-' . $user->id . '"', $body);
            $this->assertGreaterThan(strpos($body, '</table>'), strpos($body, 'class="offcanvas offcanvas-end"'));
            $this->assertStringNotContainsString('<details>', $body);

            $this->get('/zh-Hans/admin/users?q=grantviewer')->assertSee('有效权限');
            $this->get('/zh-Hant/admin/users?q=grantviewer')->assertSee('有效權限');
            $this->createUser('noaccessviewer', 'noaccessviewer@example.com');
            $emptyPermissions = $this->get('/en/admin/users?q=noaccessviewer');
            $emptyPermissions->assertSee('No catalog permissions');
            $emptyUser = auth()->getProvider()->findByCredentials(['email' => 'noaccessviewer@example.com']);
            $this->assertStringContainsString('data-bs-target="#user-permissions-' . $emptyUser->id . '"', $emptyPermissions->response()->getBody());
        } finally {
            setting('AuthGroups.matrix', $originalMatrix);
        }
    }

    public function testUserListHeaderSortingKeepsSearchAndTogglesDirection(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('sortcase-z', 'sortcase-z@example.com');
        $this->createUser('sortcase-a', 'sortcase-a@example.com');

        $ascending = $this->get('/en/admin/users?q=sortcase&sort=username&direction=ASC');
        $ascending->assertOK();
        $body = $ascending->response()->getBody();
        $this->assertStringContainsString('class="table-sort asc"', $body);
        $this->assertStringContainsString('aria-sort="ascending"', $body);
        $this->assertStringContainsString('name="q" value="sortcase"', $body);
        $this->assertStringContainsString('name="direction" value="DESC"', $body);
        $this->assertStringContainsString('<td>sortcase-a</td>', $body);
        $this->assertStringContainsString('<td>sortcase-z</td>', $body);
        $this->assertLessThan(strpos($body, '<td>sortcase-z</td>'), strpos($body, '<td>sortcase-a</td>'));

        $descending = $this->get('/en/admin/users?q=sortcase&sort=username&direction=DESC');
        $descending->assertOK();
        $body = $descending->response()->getBody();
        $this->assertStringContainsString('class="table-sort desc"', $body);
        $this->assertStringContainsString('aria-sort="descending"', $body);
        $this->assertLessThan(strpos($body, '<td>sortcase-a</td>'), strpos($body, '<td>sortcase-z</td>'));
    }

    public function testEmailHeaderSortsAllUsersByEmail(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('alpha', 'zeta@example.com');
        $this->createUser('zulu', 'alpha@example.com');

        $ascending = $this->get('/en/admin/users?sort=email&direction=ASC');
        $ascending->assertOK();
        $body = $ascending->response()->getBody();
        $this->assertStringContainsString('class="table-sort asc"', $body);
        $this->assertStringContainsString('aria-sort="ascending"', $body);
        $this->assertStringContainsString('name="sort" value="email"', $body);
        $this->assertStringContainsString('name="direction" value="DESC"', $body);
        $this->assertStringContainsString('alpha@example.com', $body);
        $this->assertStringContainsString('zeta@example.com', $body);
        $this->assertLessThan(strpos($body, '<td>zeta@example.com</td>'), strpos($body, '<td>alpha@example.com</td>'));

        $descending = $this->get('/en/admin/users?sort=email&direction=DESC');
        $descending->assertOK();
        $body = $descending->response()->getBody();
        $this->assertStringContainsString('class="table-sort desc"', $body);
        $this->assertLessThan(strpos($body, '<td>alpha@example.com</td>'), strpos($body, '<td>zeta@example.com</td>'));

        $filtered = $this->get('/en/admin/users?q=example.com&sort=email&direction=ASC');
        $filtered->assertOK();
        $body = $filtered->response()->getBody();
        $this->assertStringContainsString('name="q" value="example.com"', $body);
        $this->assertLessThan(strpos($body, '<td>zeta@example.com</td>'), strpos($body, '<td>alpha@example.com</td>'));

        $export = $this->get('/en/admin/users/export?q=example.com&sort=email&direction=ASC');
        $export->assertOK();
        $csv = $export->response()->getBody();
        $this->assertLessThan(strpos($csv, 'zeta@example.com'), strpos($csv, 'alpha@example.com'));
    }

    public function testUsernameAndEmailSortingIgnoreCase(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('Zulu', 'Zoo@example.com');
        $this->createUser('apple', 'apple@example.com');

        foreach (['username', 'email'] as $sort) {
            $page = $this->get('/en/admin/users?q=example.com&sort=' . $sort . '&direction=ASC');
            $page->assertOK();
            $body = $page->response()->getBody();
            $this->assertLessThan(strpos($body, '<td>Zulu</td>'), strpos($body, '<td>apple</td>'));

            $export = $this->get('/en/admin/users/export?q=example.com&sort=' . $sort . '&direction=ASC');
            $this->assertLessThan(strpos($export->response()->getBody(), 'Zulu,Zoo@example.com'), strpos($export->response()->getBody(), 'apple,apple@example.com'));
        }
    }

    public function testOrdinaryUsernameLookupUsesCitext(): void
    {
        $this->createUser('Mixed.Name', 'mixed.name@example.com');
        $users = auth()->getProvider();

        $this->assertSame('Mixed.Name', $users->where('username', 'mixed.name')->first()->username);
        $this->assertSame('citext', $users->db->query("SELECT format_type(atttypid, atttypmod) AS type FROM pg_attribute WHERE attrelid = 'users'::regclass AND attname = 'username'")->getRow('type'));
    }

    public function testUsernameUniqueIndexRejectsDifferentCase(): void
    {
        $this->createUser('Mixed.Name', 'mixed.name@example.com');

        $this->expectException(DatabaseException::class);
        auth()->getProvider()->db->table(config('Auth')->tables['users'])->insert(['username' => 'mixed.name']);
    }

    public function testImportPostRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);
        $this->post('/en/admin/users/import', []);
    }

    public function testImportRejectsMissingFileWithCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->post('/en/admin/users/import', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(lang('Admin.invalidUserCsv'), session('alert')['message']);
    }

    public function testForgedUploadIsRejected(): void
    {
        $this->loginAs('superadmin');
        $filename = tempnam(sys_get_temp_dir(), 'user-csv-');
        file_put_contents($filename, "username,email\nnewuser,newuser@example.com\nduplicate,NEWUSER@example.com\n");
        service('superglobals')->setFilesArray([
            'file' => ['name' => 'users.csv', 'type' => 'text/csv', 'tmp_name' => $filename, 'error' => UPLOAD_ERR_OK, 'size' => filesize($filename)],
        ]);

        try {
            $this->post('/en/admin/users/import', [csrf_token() => csrf_hash()])->assertRedirect();
            $this->assertSame(lang('Admin.invalidUserCsv'), session('alert')['message']);
            $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'newuser@example.com']));
        } finally {
            service('superglobals')->setFilesArray([]);
            unlink($filename);
        }
    }

    public function testCsvImportProcessesValidFileAndRejectsInvalidUploadMetadata(): void
    {
        $this->loginAs('superadmin');
        $filename = tempnam(sys_get_temp_dir(), 'user-csv-');
        file_put_contents($filename, "username,email\nnewuser,newuser@example.com\n");
        $imageFile = tempnam(sys_get_temp_dir(), 'user-image-');
        file_put_contents($imageFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==', true));
        $badHeaderFile = tempnam(sys_get_temp_dir(), 'user-invalid-');
        file_put_contents($badHeaderFile, "email,username\nnewuser@example.com,newuser\n");

        try {
            $this->assertContains((new File($filename))->getMimeType(), ['text/plain', 'text/csv', 'application/vnd.ms-excel']);
            $this->assertSame('image/png', (new File($imageFile))->getMimeType());

            foreach ([
                ['users.csv', $filename, 1024 * 1024 + 1, false],
                ['users.txt', $filename, filesize($filename), false],
                ['users.csv', $imageFile, filesize($imageFile), false],
                ['users.csv', $badHeaderFile, filesize($badHeaderFile), false],
                ['users.csv', $filename, filesize($filename), true],
            ] as [$clientName, $source, $size, $accepted]) {
                session()->remove('alert');
                session()->remove('user_import_report');
                $file = $this->getMockBuilder(UploadedFile::class)
                    ->setConstructorArgs([$source, $clientName, null, $size, UPLOAD_ERR_OK])
                    ->onlyMethods(['isValid'])
                    ->getMock();
                $file->expects($this->once())->method('isValid')->willReturn(true);
                $request = $this->getMockBuilder(IncomingRequest::class)
                    ->setConstructorArgs([config('App'), service('uri'), null, new UserAgent()])
                    ->onlyMethods(['getFile'])
                    ->getMock();
                $request->expects($this->once())->method('getFile')->with('file')->willReturn($file);
                $controller = new Users();
                $controller->initController($request, Services::response(null, false), Services::logger());
                $response = $controller->import();

                $this->assertSame('/en/admin/users', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
                $this->assertSame(lang($accepted ? 'Admin.importFinished' : 'Admin.invalidUserCsv'), session('alert')['message']);
                $this->assertSame($accepted ? 1 : 0, auth()->getProvider()->countAllResults() - 1);
                $this->assertSame($accepted ? 'created' : null, session('user_import_report')[0]['result'] ?? null);
            }

            $this->assertSame('newuser', auth()->getProvider()->findByCredentials(['email' => 'newuser@example.com'])->username);
        } finally {
            unlink($filename);
            unlink($imageFile);
            unlink($badHeaderFile);
        }
    }

    public function testCsvExportEscapesSpreadsheetFormulas(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('=1+1', 'formula@example.com');

        $csv = $this->get('/en/admin/users/export?q=formula%40example.com');
        $csv->assertOK();
        $this->assertStringContainsString("'=1+1,formula@example.com", $csv->response()->getBody());
    }

    public function testSuperadminCanAssignAdminRoleAndBanImportedAccount(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('imported', 'imported@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'imported@example.com']);

        $this->get('/en/admin/users/' . $user->id . '/edit')->assertOK();
        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'imported', 'email' => 'imported@example.com', 'role' => 'admin', 'status' => 'banned'])->assertRedirect();
        $updated = auth()->getProvider()->findById($user->id);
        $this->assertTrue($updated->inGroup('admin'));
        $this->assertTrue($updated->isBanned());
        $this->get('/en/admin/users/' . auth()->id() . '/edit')->assertStatus(404);
    }

    public function testEditRolesComeFromShieldConfiguration(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('developeruser', 'developer@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'developer@example.com']);
        $url  = '/en/admin/users/' . $user->id . '/edit';

        $form = $this->get($url);
        $form->assertOK();
        $body = $form->response()->getBody();
        $this->assertStringContainsString('<option value="developer">Developer</option>', $body);
        $this->assertStringNotContainsString('<option value="superadmin"', $body);

        $this->post($url, [csrf_token() => csrf_hash(), 'username' => 'developeruser', 'email' => 'developer@example.com', 'role' => 'developer', 'status' => 'enabled'])->assertRedirect();
        $this->assertTrue(auth()->getProvider()->findById($user->id)->inGroup('developer'));
        $this->assertStringContainsString('<option value="developer" selected>Developer</option>', $this->get($url)->response()->getBody());
    }

    public function testMultiGroupAccountCannotLoseGroupsThroughSingleRoleForm(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('multigroup', 'multigroup@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'multigroup@example.com']);
        $user->addGroup('admin', 'beta');
        $url = '/en/admin/users/' . $user->id . '/edit';

        $this->assertStringNotContainsString('href="' . route_to('admin/users/edit', $user->id) . '"', $this->get('/en/admin/users')->response()->getBody());
        $this->get($url)->assertStatus(404);
        $this->post($url, [csrf_token() => csrf_hash(), 'username' => 'multigroup', 'email' => 'multigroup@example.com', 'role' => 'user', 'status' => 'enabled'])->assertStatus(404);
        $this->assertTrue(auth()->getProvider()->findById($user->id)->inGroup('beta'));
    }

    public function testSuperadminCanUpdateUsernameAndEmailWithoutChangingPasswordOrMicrosoftIdentity(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('original', 'original@example.com');
        $user         = auth()->getProvider()->findByCredentials(['email' => 'original@example.com']);
        $passwordHash = $user->getEmailIdentity()->secret2;
        $form         = $this->get('/en/admin/users/' . $user->id . '/edit');
        $form->assertOK();
        $this->assertStringContainsString('name="username"', $form->response()->getBody());
        $this->assertStringContainsString('aria-describedby="user-username-hint"', $form->response()->getBody());
        $this->assertStringContainsString('id="user-username-hint" class="form-hint">' . lang('Admin.usernameHint'), $form->response()->getBody());
        $this->assertStringContainsString('class="form-label required" for="user-role"', $form->response()->getBody());
        $this->assertStringContainsString('class="form-label required" for="user-status"', $form->response()->getBody());
        $this->assertStringContainsString('name="email"', $form->response()->getBody());
        model(UserIdentityModel::class)->create([
            'user_id' => $user->id,
            'type'    => MicrosoftLinks::IDENTITY_TYPE,
            'secret'  => 'tenant/object',
        ]);

        $this->post('/en/admin/users/' . $user->id . '/edit', [
            csrf_token() => csrf_hash(),
            'username'   => 'renamed',
            'email'      => 'NEW@example.com',
            'role'       => 'admin',
            'status'     => 'banned',
        ])->assertRedirect();

        $updated = auth()->getProvider()->findById($user->id);
        $this->assertSame('renamed', $updated->username);
        $this->assertSame('new@example.com', $updated->email);
        $this->assertSame($passwordHash, $updated->getEmailIdentity()->secret2);
        $this->assertSame('tenant/object', $updated->getIdentity(MicrosoftLinks::IDENTITY_TYPE)->secret);
        $this->assertTrue($updated->inGroup('admin'));
        $this->assertTrue($updated->isBanned());
        $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'original@example.com']));
    }

    public function testDuplicateUsernameOrEmailDoesNotPartiallyUpdateAccount(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('existing', 'existing@example.com');
        $this->createUser('target', 'target@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);
        $url  = '/en/admin/users/' . $user->id . '/edit';

        $this->post($url, [csrf_token() => csrf_hash(), 'username' => 'EXISTING', 'email' => 'changed@example.com', 'role' => 'admin', 'status' => 'banned'])->assertRedirect();
        $this->assertSame(lang('Admin.userReason_username'), session('user_errors.username'));
        $this->post($url, [csrf_token() => csrf_hash(), 'username' => 'changed', 'email' => 'EXISTING@example.com', 'role' => 'admin', 'status' => 'banned'])->assertRedirect();
        $this->assertSame(lang('Admin.userReason_duplicate'), session('user_errors.email'));

        $updated = auth()->getProvider()->findById($user->id);
        $this->assertSame('target', $updated->username);
        $this->assertSame('target@example.com', $updated->email);
        $this->assertFalse($updated->inGroup('admin'));
        $this->assertFalse($updated->isBanned());
    }

    public function testEditingUsernameRejectsCaseInsensitiveConflictButAllowsUnchangedName(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('owner', 'target@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);
        $this->createUser('TARGET', 'legacycase@example.com');

        $this->post('/en/admin/users/' . $user->id . '/edit', [
            csrf_token() => csrf_hash(),
            'username'   => 'target',
            'email'      => 'target@example.com',
            'role'       => 'admin',
            'status'     => 'banned',
        ])->assertRedirect();

        $this->assertSame(lang('Admin.userReason_username'), session('user_errors.username'));
        $updated = auth()->getProvider()->findById($user->id);
        $this->assertFalse($updated->inGroup('admin'));
        $this->assertFalse($updated->isBanned());

        $this->post('/en/admin/users/' . $user->id . '/edit', [
            csrf_token() => csrf_hash(),
            'username'   => 'owner',
            'email'      => 'target@example.com',
            'role'       => 'admin',
            'status'     => 'enabled',
        ])->assertRedirect();
        $this->assertTrue(auth()->getProvider()->findById($user->id)->inGroup('admin'));
    }

    public function testEmailOnlyUpdatePreservesPasswordHash(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('sameuser', 'before@example.com');
        $user         = auth()->getProvider()->findByCredentials(['email' => 'before@example.com']);
        $passwordHash = $user->getEmailIdentity()->secret2;

        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'sameuser', 'email' => 'after@example.com', 'role' => 'user', 'status' => 'enabled'])->assertRedirect();

        $updated = auth()->getProvider()->findById($user->id);
        $this->assertSame('sameuser', $updated->username);
        $this->assertSame('after@example.com', $updated->email);
        $this->assertSame($passwordHash, $updated->getEmailIdentity()->secret2);
        $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'before@example.com']));
    }

    public function testEditingUserRequiresEmail(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('targetuser', 'target@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);

        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'renamed', 'role' => 'admin', 'status' => 'banned'])->assertRedirect();
        $this->assertArrayHasKey('email', session('user_errors'));

        $updated = auth()->getProvider()->findById($user->id);
        $this->assertSame('targetuser', $updated->username);
        $this->assertSame('target@example.com', $updated->email);
        $this->assertFalse($updated->inGroup('admin'));
        $this->assertFalse($updated->isBanned());
    }

    public function testOrdinaryAdminCannotPromoteUsersAndInvalidRoleCannotBeAssigned(): void
    {
        $this->loginAs('admin');
        $this->createUser('targetuser', 'target@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);
        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'targetuser', 'email' => 'target@example.com', 'role' => 'admin', 'status' => 'enabled'])->assertRedirect();
        $this->assertArrayHasKey('role', session('user_errors'));
        $this->assertFalse(auth()->getProvider()->findById($user->id)->inGroup('admin'));

        auth()->logout();
        $this->loginAs('superadmin');
        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'targetuser', 'email' => 'target@example.com', 'role' => 'superadmin', 'status' => 'enabled'])->assertRedirect();
        $this->assertFalse(auth()->getProvider()->findById($user->id)->inGroup('superadmin'));
    }

    public function testRoleUpdateRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('targetuser', 'target@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);
        $this->expectException(SecurityException::class);
        $this->post('/en/admin/users/' . $user->id . '/edit', ['role' => 'admin', 'status' => 'enabled']);
    }

    public function testCreateUserSharesImportProvisioningRules(): void
    {
        $this->loginAs('superadmin');
        $form = $this->get('/en/admin/users/create');
        $form->assertOK();
        $this->assertStringContainsString('aria-describedby="new-username-hint"', $form->response()->getBody());
        $this->assertStringContainsString('id="new-username-hint" class="form-hint">' . lang('Admin.usernameHint'), $form->response()->getBody());
        $this->post('/en/admin/users/create', [csrf_token() => csrf_hash(), 'username' => 'createduser', 'email' => 'created@example.com'])->assertRedirect();
        $user = auth()->getProvider()->findByCredentials(['email' => 'created@example.com']);
        $this->assertNotNull($user);
        $this->assertTrue($user->inGroup('user'));
        $this->assertFalse($user->can('admin.access'));

        $this->post('/en/admin/users/create', [csrf_token() => csrf_hash(), 'username' => 'otheruser', 'email' => 'CREATED@example.com'])->assertRedirect();
        $this->assertSame(lang('Admin.userReason_duplicate'), session('user_errors.email'));

        $this->post('/en/admin/users/create', [csrf_token() => csrf_hash(), 'username' => 'CREATEDUSER', 'email' => 'another@example.com'])->assertRedirect();
        $this->assertSame(lang('Admin.userReason_username'), session('user_errors.username'));
        $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'another@example.com']));
    }

    public function testInviteReportsQueueAcceptanceAndOffersRetry(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('invitee', 'invitee@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'invitee@example.com']);
        service('settings')->set('Email.fromEmail', 'sender@example.com');

        $page = $this->get('/en/admin/users?q=invitee');
        $page->assertOK();
        $this->assertStringContainsString('action="' . route_to('admin/users/invite', $user->id) . '"', $page->response()->getBody());
        $this->assertStringContainsString('aria-label="' . esc(lang('Admin.userInvite') . ': ' . $user->username, 'attr') . '"', $page->response()->getBody());

        foreach ([true, false] as $sent) {
            $email = $this->createMock(QueuedEmail::class);
            $email->expects($this->once())->method('setFrom')->with('sender@example.com');
            $email->expects($this->once())->method('setTo')->with('invitee@example.com');
            $email->expects($this->once())->method('setSubject')->with(lang('Admin.userInviteSubject'));
            $email->expects($this->once())->method('setMailType')->with('html');
            $email->expects($this->once())->method('setMessage')->with($this->callback(static fn (string $body): bool => str_contains($body, '<!DOCTYPE html PUBLIC')
                && str_contains($body, '<a href="' . url_to('magic-link') . '"')
                && str_contains($body, 'Hello invitee')
                && ! str_contains($body, 'GeminusAdmin')));
            $email->expects($this->once())->method('send')->willReturn($sent);
            $email->expects($this->never())->method('sendDirect');
            Services::injectMock('email', $email);

            $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
            $this->assertSame($sent ? 'success' : 'danger', session('alert')['type']);
            $this->assertSame(lang($sent ? 'Admin.userInviteQueued' : 'Admin.userInviteFailed'), session('alert')['message']);
            Services::resetSingle('email');
        }
    }

    public function testInviteUsesSavedMailTemplate(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('invitee', 'invitee@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'invitee@example.com']);
        service('settings')->set('Email.fromEmail', 'sender@example.com');
        service('settings')->set(MailTemplates::settingKey('invitation', 'en', 'subject'), 'Welcome {username}');
        service('settings')->set(MailTemplates::settingKey('invitation', 'en', 'body'), 'Open {link}');

        $email = $this->createMock(QueuedEmail::class);
        $email->expects($this->once())->method('setSubject')->with('Welcome invitee');
        $email->expects($this->once())->method('setMailType')->with('html');
        $email->expects($this->once())->method('setMessage')->with($this->callback(static fn (string $body): bool => str_contains($body, '<body>Open ' . url_to('magic-link') . '</body>')));
        $email->method('send')->willReturn(true);
        Services::injectMock('email', $email);

        $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
    }

    public function testInviteIncludesMicrosoftInstructionsOnlyWhenEnabled(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('invitee', 'invitee@example.com');
        $user     = auth()->getProvider()->findByCredentials(['email' => 'invitee@example.com']);
        $settings = service('settings');
        $settings->set('Email.fromEmail', 'sender@example.com');
        $previous = $settings->get('MicrosoftOAuth.enabled');

        try {
            foreach (['en' => 'sign in with Microsoft', 'zh-Hans' => '通过微软登录', 'zh-Hant' => '透過微軟登入'] as $locale => $label) {
                foreach ([false, true] as $enabled) {
                    $settings->set('MicrosoftOAuth.enabled', $enabled);
                    $email = $this->createMock(QueuedEmail::class);
                    $email->expects($this->once())->method('setSubject')->with(lang('Admin.userInviteSubject', [], $locale));
                    $email->expects($this->once())->method('setMessage')->with($this->callback(function (string $body) use ($enabled, $locale, $label): bool {
                        $this->assertStringContainsString('<!DOCTYPE html PUBLIC', $body);
                        $this->assertStringContainsString(url_to('magic-link', $locale), $body);
                        $this->assertStringNotContainsString('{microsoftLogin}', $body);
                        $this->assertStringNotContainsString('DEBUG-VIEW', $body);
                        if ($enabled) {
                            $this->assertStringContainsString('<a href="' . url_to('microsoft/start', $locale) . '">' . $label . '</a>', $body);
                        } else {
                            $this->assertStringNotContainsString(url_to('microsoft/start', $locale), $body);
                        }

                        return true;
                    }));
                    $email->method('send')->willReturn(true);
                    Services::injectMock('email', $email);

                    $this->post('/' . $locale . '/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
                    $this->assertSame('success', session('alert')['type']);
                }
            }
        } finally {
            $settings->set('MicrosoftOAuth.enabled', $previous);
        }
    }

    public function testInvitationQueueAuditShowsLatestDeliveryStatus(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('invitee', 'invitee@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'invitee@example.com']);
        service('settings')->set('Email.fromEmail', 'sender@example.com');

        $this->assertStringContainsString('Not sent', $this->get('/en/admin/users?q=invitee')->response()->getBody());

        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->exactly(2))->method('push')->with('email', 'send-email', $this->callback(static fn (array $data): bool => $data['to'] === ['invitee@example.com']
            && $data['subject'] === lang('Admin.userInviteSubject')
            && $data['type'] === 'html'
            && str_contains($data['body'], '<!DOCTYPE html PUBLIC')
            && ! str_contains($data['body'], 'GeminusAdmin')))
            ->willReturnOnConsecutiveCalls(QueuePushResult::success(41), QueuePushResult::success(42));
        Services::injectMock('queue', $queue);
        Services::injectMock('email', service('email', null, false));

        $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(lang('Admin.userInviteQueued'), session('alert')['message']);
        $log = db_connect()->table('email_delivery_logs')->where('invited_user_id', $user->id)->get()->getRowArray();
        $this->assertSame('queued', $log['status']);
        $this->assertStringContainsString('Queued', $this->get('/en/admin/users?q=invitee')->response()->getBody());

        db_connect()->table('email_delivery_logs')->where('id', $log['id'])->update(['status' => 'sent']);
        $this->assertStringContainsString('Sent', $this->get('/en/admin/users?q=invitee')->response()->getBody());

        $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertStringContainsString('Queued', $this->get('/en/admin/users?q=invitee')->response()->getBody());
        $latest = db_connect()->table('email_delivery_logs')->where('invited_user_id', $user->id)->orderBy('id', 'DESC')->get()->getRowArray();
        db_connect()->table('email_delivery_logs')->where('id', $latest['id'])->update(['status' => 'failed']);
        $this->assertStringContainsString('Failed', $this->get('/en/admin/users?q=invitee')->response()->getBody());
    }

    public function testInviteRejectsUnauthorizedOrUnavailableAccounts(): void
    {
        $this->loginAs('admin');
        $this->createUser('invitee', 'invitee@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'invitee@example.com']);
        $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();

        auth()->logout();
        $this->loginAs('superadmin');
        $this->post('/en/admin/users/' . auth()->id() . '/invite', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->post('/en/admin/users/999999/invite', [csrf_token() => csrf_hash()])->assertStatus(404);
        service('settings')->set('Email.fromEmail', '');
        $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(lang('Admin.inviteUnavailable'), session('alert')['message']);
    }

    public function testInviteRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('invitee', 'invitee@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'invitee@example.com']);
        $this->expectException(SecurityException::class);
        $this->post('/en/admin/users/' . $user->id . '/invite', []);
    }

    #[DataProvider('provideInviteRejectsDisabledDelivery')]
    public function testInviteRejectsDisabledDelivery(bool $banned, bool $magicLinks): void
    {
        $this->loginAs('superadmin');
        $this->createUser('disabled-invite', 'disabled-invite@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'disabled-invite@example.com']);
        if ($banned) {
            $user->ban();
        }
        service('settings')->set('Email.fromEmail', 'sender@example.com');
        $previous = setting('Auth.allowMagicLinkLogins');
        service('settings')->set('Auth.allowMagicLinkLogins', $magicLinks);
        $email = $this->createMock(QueuedEmail::class);
        $email->expects($this->never())->method('send');
        Services::injectMock('email', $email);

        try {
            $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
            $this->assertSame(lang('Admin.inviteUnavailable'), session('alert')['message']);
            $this->assertSame(0, db_connect()->table('email_delivery_logs')->countAllResults());
        } finally {
            service('settings')->set('Auth.allowMagicLinkLogins', $previous);
        }
    }

    public static function provideInviteRejectsDisabledDelivery(): iterable
    {
        yield 'banned target' => [true, true];

        yield 'magic links disabled' => [false, false];
    }

    public function testCreationAndImportDoNotInviteOrBindAccounts(): void
    {
        $this->loginAs('superadmin');
        service('settings')->set('Email.fromEmail', 'sender@example.com');
        $previous = setting('Auth.allowMagicLinkLogins');
        service('settings')->set('Auth.allowMagicLinkLogins', true);
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->never())->method('push');
        Services::injectMock('queue', $queue);
        Services::injectMock('email', service('email', null, false));

        try {
            $this->post('/en/admin/users/create', [csrf_token() => csrf_hash(), 'username' => 'no.invite', 'email' => 'no-invite@example.com'])->assertRedirect();
            $created = auth()->getProvider()->findByCredentials(['email' => 'no-invite@example.com']);
            $this->assertNotNull($created);

            foreach (['', 'password', 'changeme', $created->username, $created->email] as $password) {
                $this->assertFalse(auth()->check(['email' => $created->email, 'password' => $password])->isOK());
            }
            $filename = tempnam(sys_get_temp_dir(), 'no-invite-csv-');

            try {
                file_put_contents($filename, "username,email\nno.invite.one,no-invite-one@example.com\nno.invite.two,no-invite-two@example.com\n");
                $file = $this->getMockBuilder(UploadedFile::class)
                    ->setConstructorArgs([$filename, 'users.csv', null, filesize($filename), UPLOAD_ERR_OK])
                    ->onlyMethods(['isValid'])->getMock();
                $file->expects($this->atLeastOnce())->method('isValid')->willReturn(true);
                $request = $this->getMockBuilder(IncomingRequest::class)
                    ->setConstructorArgs([config('App'), service('uri'), null, new UserAgent()])
                    ->onlyMethods(['getFile'])->getMock();
                $request->expects($this->atLeastOnce())->method('getFile')->with('file')->willReturn($file);
                $controller = new Users();
                $controller->initController($request, Services::response(null, false), Services::logger());
                $response = $controller->import();
                $this->assertSame('/en/admin/users', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
                $this->assertSame(lang('Admin.importFinished'), session('alert')['message']);
                $report = session('user_import_report');
                $this->assertSame(['created', 'created'], array_column($report, 'result'));
            } finally {
                unlink($filename);
            }
            $this->assertSame(0, db_connect()->table('email_delivery_logs')->countAllResults());
            $this->assertSame(0, db_connect()->table('auth_identities')->where('type', MicrosoftLinks::IDENTITY_TYPE)->countAllResults());

            foreach (['no.invite', 'no.invite.one', 'no.invite.two'] as $username) {
                $stored = auth()->getProvider()->findByCredentials(['username' => $username]);
                $this->assertNotNull($stored);
                $this->assertSame(['user'], $stored->getGroups());
                $this->assertFalse($stored->can('admin.access'));
            }
        } finally {
            service('settings')->set('Auth.allowMagicLinkLogins', $previous);
        }
    }

    public function testUserListPaginatesTwentyMatchingUsers(): void
    {
        $this->loginAs('superadmin');

        for ($number = 1; $number <= 21; $number++) {
            $this->createUser('page-user-' . $number, 'page-user-' . $number . '@example.com');
        }
        $first = $this->get('/en/admin/users?q=page-user-');
        $first->assertSee('Total: 21 (1 - 20)');
        $document = new DOMDocument();
        $document->loadHTML($first->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $this->assertSame(20, $xpath->query('//table/tbody/tr')->length);
        Services::resetSingle('pager');
        $second = $this->get('/en/admin/users?q=page-user-&page=2');
        $second->assertSee('Total: 21 (21 - 21)');
        $document->loadHTML($second->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->assertSame(1, (new DOMXPath($document))->query('//table/tbody/tr')->length);
    }

    public function testImportReportDoesNotRenderMissingReasonKey(): void
    {
        $this->loginAs('superadmin');
        session()->setFlashdata('user_import_report', [
            ['row' => 2, 'email' => 'created@example.com', 'result' => 'created', 'reason' => ''],
            ['row' => 3, 'email' => 'created@example.com', 'result' => 'skipped', 'reason' => 'duplicate'],
        ]);

        $page = $this->withSession($_SESSION)->get('/en/admin/users');
        $page->assertOK();
        $this->assertStringContainsString('Email already exists.', $page->response()->getBody());
        $this->assertStringNotContainsString('Admin.userReason_', $page->response()->getBody());
    }

    private function loginAs(string $group): void
    {
        $user        = new AdminUser(['username' => 'userstest' . $group]);
        $user->email = 'userstest' . $group . '@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($group);
        auth()->login($user);
    }

    private function createUser(string $username, string $email): void
    {
        $user        = new AdminUser(['username' => $username]);
        $user->email = $email;
        $user->setPassword('A-local-password-123!');
        auth()->getProvider()->save($user);
    }
}
