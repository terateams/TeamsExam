<?php

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\Postgre\Builder;
use CodeIgniter\Database\Postgre\Connection;
use CodeIgniter\Database\Postgre\Result;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Authorization\PermissionMatcher;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\AuthGroups;
use Config\Services;
use Geminus\Admin\Config\AdminMenu;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\DataManagement\Attachments;
use Geminus\Admin\Libraries\DataManagement\UploadStorage;
use Geminus\Admin\Models\AttachmentModel;
use Geminus\Admin\Models\NotificationModel;
use Modules\Announcements\Config\Registrar;
use Modules\Announcements\Controllers\AnnouncementAttachments;
use Modules\Announcements\Controllers\Announcements;
use Modules\Announcements\Database\Migrations\AddPublicationState;
use Modules\Announcements\Database\Migrations\GrantAnnouncementsToSuperadmin;
use Modules\Announcements\Libraries\AnnouncementDeletion;
use Modules\Announcements\Models\AnnouncementModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Libraries\TableLayoutAssertions;

/**
 * @internal
 */
final class AnnouncementsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testPresenterRoutesUseNativeMethodsAndDistinctNames(): void
    {
        $this->loginAs('admin', ['announcements.manage']);
        $model = new AnnouncementModel();
        $owner = $model->insert(['title' => 'Presenter announcement', 'body' => 'Content']);

        foreach (['en', 'zh-Hans', 'zh-Hant'] as $locale) {
            service('request')->setLocale($locale);
            service('language')->setLocale($locale);
            $base = '/' . $locale . '/admin/announcements';
            $this->assertSame($base, route_to('admin/announcements'));

            foreach (['new', 'create', 'edit', 'update', 'delete', 'show'] as $action) {
                $parameters = in_array($action, ['new', 'create'], true) ? [] : [$owner];
                $this->assertSame($base . '/' . $action . ($parameters === [] ? '' : '/' . $owner), route_to('admin/announcements/' . $action, ...$parameters));
            }
            $new = $this->get($base . '/new');
            $new->assertOK();
            $newForm = $this->dashboardXPath($new->response()->getBody())->query('//form[.//input[@name="title"]]')->item(0);
            $this->assertSame('post', $newForm->getAttribute('method'));
            $this->assertSame($base . '/create', $newForm->getAttribute('action'));
            $this->get($base . '/show/' . $owner)->assertOK();
            $this->get($base . '/' . $owner)->assertOK();
            $edit = $this->get($base . '/edit/' . $owner);
            $edit->assertOK();
            $form = $this->dashboardXPath($edit->response()->getBody())->query('//form[@method="post" and .//input[@name="title"]]')->item(0);
            $this->assertSame($base . '/update/' . $owner, $form->getAttribute('action'));
        }
    }

    public function testPresenterRoutesRejectWrongHttpMethodsWithoutChangingData(): void
    {
        $this->loginAs('admin', ['announcements.manage']);
        $model    = new AnnouncementModel();
        $owner    = $model->insert(['title' => 'Unchanged presenter record', 'body' => 'Original content']);
        $original = $model->find($owner);

        foreach (['create', 'update/' . $owner, 'delete/' . $owner] as $path) {
            $this->get('/en/admin/announcements/' . $path)->assertStatus(404);
        }

        foreach (['new', 'edit/' . $owner, 'show/' . $owner] as $path) {
            $this->post('/en/admin/announcements/' . $path, [csrf_token() => csrf_hash(), 'title' => 'Tampered', 'body' => 'Tampered'])->assertStatus(404);
        }
        $this->get('/en/admin/announcements/' . $owner . '/edit')->assertStatus(404);
        $this->post('/en/admin/announcements/' . $owner . '/edit', [csrf_token() => csrf_hash(), 'title' => 'Tampered', 'body' => 'Tampered'])->assertStatus(404);
        $this->assertSame($original, $model->find($owner));
        $this->assertSame(1, $model->countAllResults());
    }

    public function testAccessRequiresPermission(): void
    {
        $this->get('/en/admin/announcements')->assertRedirect();
        $this->get('/en/admin/announcements/show/1')->assertRedirect();
        $this->post('/en/admin/announcements/create', [csrf_token() => csrf_hash(), 'title' => 'Hidden', 'body' => 'Hidden'])->assertRedirect();
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());

        $this->loginAs('admin');
        $this->get('/en/admin/announcements')->assertRedirect();
        $this->get('/en/admin/announcements/show/1')->assertRedirect();
        $this->get('/en/admin/announcements/new')->assertRedirect();
        $this->post('/en/admin/announcements/create', [csrf_token() => csrf_hash(), 'title' => 'Hidden', 'body' => 'Hidden'])->assertRedirect();
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        $dashboard = $this->get('/en/admin/dashboard');
        $dashboard->assertOK();
        $this->assertStringNotContainsString('href="/en/admin/announcements"', $dashboard->response()->getBody());
    }

    public function testDashboardManagerAndRevokedPermissionsDoNotShareData(): void
    {
        $model = new AnnouncementModel();
        $draft = $model->insert(['title' => 'Secret dashboard draft', 'body' => 'Hidden', 'status' => 'draft']);
        $model->insert(['title' => 'Second private draft', 'body' => 'Hidden', 'status' => 'draft']);
        $model->insert(['title' => 'Public dashboard announcement', 'body' => 'Visible', 'status' => 'published', 'published_at' => '2026-10-10 01:02:03']);
        $this->loginAs('admin', ['announcements.manage', 'announcements.access']);
        $result = $this->get('/zh-Hans/admin/dashboard?status=published');
        $result->assertOK();
        $result->assertSee('公告草稿');
        $result->assertSee('Secret dashboard draft');
        $result->assertSee('Public dashboard announcement');
        $body = $result->response()->getBody();
        $this->assertSame('2', $this->dashboardDraftValue($body));
        $this->assertSame(2, array_column(service('dashboard')->sections(auth()->user(), 'zh-Hans'), null, 'id')['announcements']['items'][0]['value']);
        $this->assertContains('/zh-Hans/admin/announcements/show/' . $draft, $this->dashboardLinks($body));
        $this->assertContains('/zh-Hans/admin/announcements?status=draft', $this->dashboardLinks($body));
        $this->assertContains('/zh-Hans/admin/announcements/new', $this->dashboardLinks($body));
        $this->assertStringNotContainsString('最近发布的公告', $body);
        $this->assertStringContainsString('private', $result->response()->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
        $this->get('/zh-Hans/admin/announcements?status=draft')->assertSee('Secret dashboard draft');

        auth()->user()->removePermission('announcements.manage');
        $reader = $this->get('/zh-Hans/admin/dashboard');
        $reader->assertOK();
        $reader->assertSee('Public dashboard announcement');
        $readerBody = $reader->response()->getBody();
        $this->assertStringNotContainsString('Secret dashboard draft', $readerBody);
        $this->assertStringNotContainsString('Second private draft', $readerBody);
        $this->assertStringNotContainsString('公告草稿', $readerBody);
        $this->assertNotContains('/zh-Hans/admin/announcements/new', $this->dashboardLinks($readerBody));
        $this->get('/zh-Hans/admin/announcements/' . $draft)->assertStatus(404);
        $this->get('/zh-Hans/admin/announcements/new')->assertRedirect();

        auth()->user()->removePermission('announcements.access');
        $empty = $this->get('/zh-Hans/admin/dashboard');
        $empty->assertOK();
        $remaining = service('dashboard')->sections(auth()->user(), 'zh-Hans');
        $this->assertNotEmpty($remaining);
        $this->assertNotContains('announcements', array_column($remaining, 'id'));
        $this->assertStringContainsString('dashboard-' . $remaining[0]['id'], $empty->response()->getBody());
        $this->assertStringNotContainsString('dashboard-announcements', $empty->response()->getBody());
        $this->get('/zh-Hans/admin/announcements')->assertRedirect();
    }

    public function testDashboardReaderHasBoundedStablePublishedRowsAndEscapedTitles(): void
    {
        $model = new AnnouncementModel();
        $model->insert(['title' => 'Never visible draft', 'body' => 'Secret', 'status' => 'draft']);
        $identifiers = [];

        for ($index = 0; $index < 7; $index++) {
            $identifiers[] = $model->insert(['title' => $index === 6 ? '<script>alert("dashboard")</script>' : 'Published row ' . $index, 'body' => 'Visible', 'status' => 'published', 'published_at' => $index === 0 ? '2026-10-11 01:02:03' : '2026-10-10 01:02:03']);
        }
        $this->loginAs('admin', ['announcements.access']);
        $user           = auth()->user();
        $user->timezone = 'Asia/Shanghai';
        auth()->getProvider()->save($user);
        $sections = service('dashboard')->sections($user, 'zh-Hant');
        $rows     = array_column($sections, null, 'id')['announcements']['items'][0]['rows'];
        $this->assertCount(5, $rows);
        $this->assertSame(array_map(static fn (int $identifier): string => '/zh-Hant/admin/announcements/show/' . $identifier, [$identifiers[0], $identifiers[6], $identifiers[5], $identifiers[4], $identifiers[3]]), array_column(array_column($rows, 'link'), 'url'));
        $this->assertStringContainsString('LIMIT 5', (string) $model->db->getLastQuery());
        $result = $this->get('/zh-Hant/admin/dashboard?status=draft');
        $result->assertOK();
        $result->assertSee('最近發佈的公告');
        $result->assertSee('2026-10-10 09:02:03', 'time');
        $body  = $result->response()->getBody();
        $times = $this->dashboardXPath($body)->query('//time[@datetime="2026-10-10T01:02:03Z"]');
        $this->assertCount(4, $times);
        $this->assertSame('2026-10-10 09:02:03', trim($times->item(0)->textContent));
        $this->assertStringContainsString('&lt;script&gt;', $body);
        $this->assertStringNotContainsString('<script>alert("dashboard")</script>', $body);
        $this->assertStringNotContainsString('Never visible draft', $body);
        $this->assertStringNotContainsString('Published row 1', $body);
        $this->assertStringNotContainsString('公告草稿', $body);
    }

    public function testDashboardManagerRecentRecordsUseCreationTimeAndBoundedQuery(): void
    {
        $model       = new AnnouncementModel();
        $identifiers = [];

        for ($index = 0; $index < 7; $index++) {
            $identifiers[] = $model->insert(['title' => 'Managed row ' . $index, 'body' => 'Content', 'status' => $index === 6 ? 'draft' : 'published', 'published_at' => $index === 6 ? null : '2026-10-10 01:02:03']);
            $model->db->table('example_announcements')->where('id', $identifiers[$index])->update(['created_at' => $index === 0 ? '2026-10-11 02:00:00' : '2026-10-10 01:02:03']);
        }
        $this->loginAs('admin', ['announcements.manage']);
        $sections = service('dashboard')->sections(auth()->user(), 'zh-Hans');
        $rows     = array_column($sections, null, 'id')['announcements']['items'][2]['rows'];
        $this->assertCount(5, $rows);
        $this->assertSame(array_map(static fn (int $identifier): string => '/zh-Hans/admin/announcements/show/' . $identifier, [$identifiers[0], $identifiers[6], $identifiers[5], $identifiers[4], $identifiers[3]]), array_column(array_column($rows, 'link'), 'url'));
        $this->assertSame('2026-10-11T02:00:00Z', $rows[0]['time']);
        $this->assertStringContainsString('LIMIT 5', (string) $model->db->getLastQuery());
        $result = $this->get('/zh-Hans/admin/dashboard');
        $result->assertSee('Managed row 0');
        $result->assertSee('Managed row 6');
        $this->assertStringNotContainsString('Managed row 1', $result->response()->getBody());
    }

    public function testDashboardZeroAndEmptyPublishedAreNormalStates(): void
    {
        $this->loginAs('admin', ['announcements.manage', 'announcements.access']);
        $result = $this->get('/en/admin/dashboard');
        $result->assertOK();
        $this->assertSame('0', $this->dashboardDraftValue($result->response()->getBody()));
        $this->assertSame(0, array_column(service('dashboard')->sections(auth()->user(), 'en'), null, 'id')['announcements']['items'][0]['value']);
        $result->assertSee('No announcements yet.');
        $this->assertStringNotContainsString('Temporarily unavailable', $result->response()->getBody());
        auth()->user()->removePermission('announcements.manage');
        $reader = $this->get('/en/admin/dashboard');
        $reader->assertOK();
        $reader->assertSee('No published announcements yet.');
        $this->assertStringNotContainsString('Draft announcements', $reader->response()->getBody());
    }

    private function dashboardLinks(string $body): array
    {
        $links = [];

        foreach ($this->dashboardXPath($body)->query('//a[@href]') as $link) {
            $links[] = $link->getAttribute('href');
        }

        return $links;
    }

    private function dashboardXPath(string $body): DOMXPath
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $body);

        return new DOMXPath($document);
    }

    private function dashboardDraftValue(string $body): string
    {
        $values = $this->dashboardXPath($body)->query('//*[@data-dashboard-item="announcements:drafts"]//*[contains(concat(" ", normalize-space(@class), " "), " h1 ")]');
        $this->assertCount(1, $values);

        return trim($values->item(0)->textContent);
    }

    public function testDeleteRemovesDraftAndPublishedAnnouncementsAndOnlyTheirRelatedData(): void
    {
        $this->loginAs('admin', ['announcements.manage']);
        $model = new AnnouncementModel();
        $other = $model->insert(['title' => 'Keep', 'body' => 'Other announcement']);

        foreach (['draft', 'published'] as $status) {
            $owner     = $model->insert(['title' => 'Delete ' . $status, 'body' => 'Content', 'status' => $status, 'published_at' => $status === 'published' ? '2026-10-14 01:00:00' : null]);
            $directory = WRITEPATH . 'uploads/attachments/';
            if (! is_dir($directory)) {
                mkdir($directory, 0770, true);
            }
            $filename = bin2hex(random_bytes(16)) . '.txt';
            file_put_contents($directory . $filename, 'Attachment');
            $attachments   = new AttachmentModel();
            $data          = ['resource_type' => 'announcement', 'resource_id' => $owner, 'filename' => $filename, 'original_name' => 'note.txt', 'mime_type' => 'text/plain', 'size_bytes' => 10, 'uploaded_by' => auth()->id()];
            $attachment    = $attachments->insert($data);
            $unrelated     = $attachments->insert(array_replace($data, ['resource_type' => 'user', 'filename' => bin2hex(random_bytes(16)) . '.txt']));
            $otherFilename = bin2hex(random_bytes(16)) . '.txt';
            file_put_contents($directory . $otherFilename, 'Other announcement file');
            $otherAttachment = $attachments->insert(array_replace($data, ['resource_id' => $other, 'filename' => $otherFilename]));
            $notifications   = new NotificationModel();
            $notification    = ['user_id' => auth()->id(), 'source' => 'announcements.published', 'reference' => $owner, 'title' => 'Notice', 'target_route' => 'admin/announcements/show', 'target_parameters' => json_encode([$owner]), 'permissions' => '[]'];
            $notifications->deliver($notification);
            $notifications->deliver(array_replace($notification, ['source' => 'other.event']));
            $notifications->deliver(array_replace($notification, ['reference' => $other]));

            try {
                $this->post('/en/admin/announcements/delete/' . $owner, [csrf_token() => csrf_hash()])->assertRedirectTo('/en/admin/announcements');
                $this->assertSame(lang('Announcements.deleted'), session('alert.message'));
                $this->assertNull($model->find($owner));
                $this->assertNotNull($model->find($other));
                $this->assertNull($attachments->find($attachment));
                $this->assertNotNull($attachments->find($unrelated));
                $this->assertNotNull($attachments->find($otherAttachment));
                $this->assertSame('Other announcement file', file_get_contents($directory . $otherFilename));
                $this->assertFileDoesNotExist($directory . $filename);
                $this->assertSame(0, $notifications->where('source', 'announcements.published')->where('reference', $owner)->countAllResults());
                $this->assertSame(1, $notifications->where('source', 'other.event')->where('reference', $owner)->countAllResults());
                $this->assertSame(1, $notifications->where('source', 'announcements.published')->where('reference', $other)->countAllResults());
                $this->get('/en/admin/announcements/' . $owner)->assertStatus(404);
                $this->post('/en/admin/announcements/delete/' . $owner, [csrf_token() => csrf_hash()])->assertStatus(404);
            } finally {
                if (is_file($directory . $filename)) {
                    unlink($directory . $filename);
                }
                if (is_file($directory . $otherFilename)) {
                    unlink($directory . $otherFilename);
                }
            }
        }
    }

    public function testDeleteCommitFailurePreservesAnnouncementRelatedRecordsAndFiles(): void
    {
        $this->loginAs('admin', ['announcements.manage']);
        $model     = new AnnouncementModel();
        $owner     = $model->insert(['title' => 'Keep after failure', 'body' => 'Content']);
        $directory = WRITEPATH . 'uploads/attachments/';
        if (! is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $filename = bin2hex(random_bytes(16)) . '.txt';
        file_put_contents($directory . $filename, 'Keep file');
        $attachment    = (new AttachmentModel())->insert(['resource_type' => 'announcement', 'resource_id' => $owner, 'filename' => $filename, 'original_name' => 'keep.txt', 'mime_type' => 'text/plain', 'size_bytes' => 9, 'uploaded_by' => auth()->id()]);
        $notifications = new NotificationModel();
        $notifications->deliver(['user_id' => auth()->id(), 'source' => 'announcements.published', 'reference' => $owner, 'title' => 'Keep notification', 'target_route' => 'admin/announcements/show', 'target_parameters' => json_encode([$owner]), 'permissions' => '[]']);
        $db          = $this->getMockBuilder(Connection::class)->setConstructorArgs([config('Database')->tests])->onlyMethods(['_transCommit', 'table'])->getMock();
        $resultClass = str_replace('Connection', 'Result', $db::class);
        if (! class_exists($resultClass)) {
            class_alias(Result::class, $resultClass);
        }
        $db->method('table')->willReturnCallback(static fn ($tableName) => new Builder($tableName, $db));
        $db->expects($this->once())->method('_transCommit')->willReturn(false);
        $storage = new UploadStorage('attachments', ['text/plain'], ['txt'], 1024);

        try {
            (new AnnouncementDeletion($db, $storage))->delete((int) $owner);
            $this->fail('Commit failure must not report deletion success.');
        } catch (RuntimeException $exception) {
            $this->assertSame(0, $db->transDepth);
            $this->assertNotNull((new AnnouncementModel($db))->find($owner));
            $this->assertNotNull((new AttachmentModel($db))->find($attachment));
            $this->assertSame(1, (new NotificationModel($db))->where('source', 'announcements.published')->where('reference', $owner)->countAllResults());
            $this->assertSame('Keep file', file_get_contents($directory . $filename));
        } finally {
            if ($db->transDepth > 0) {
                $db->transRollback();
            }
            unlink($directory . $filename);
        }
    }

    public function testDeleteFileCleanupFailureDoesNotRestoreCommittedRecords(): void
    {
        $this->loginAs('admin', ['announcements.manage']);
        $model     = new AnnouncementModel();
        $owner     = $model->insert(['title' => 'Delete despite cleanup failure', 'body' => 'Content']);
        $directory = WRITEPATH . 'uploads/attachments/';
        if (! is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $filename = bin2hex(random_bytes(16)) . '.txt';
        file_put_contents($directory . $filename, 'Pending cleanup');
        $attachment = (new AttachmentModel())->insert(['resource_type' => 'announcement', 'resource_id' => $owner, 'filename' => $filename, 'original_name' => 'cleanup.txt', 'mime_type' => 'text/plain', 'size_bytes' => 15, 'uploaded_by' => auth()->id()]);
        $storage    = new UploadStorage('attachments', ['text/plain'], ['txt'], 1024, static fn (): bool => false);

        try {
            $this->assertTrue((new AnnouncementDeletion($model->db, $storage))->delete((int) $owner));
            $this->assertNull($model->find($owner));
            $this->assertNull((new AttachmentModel())->find($attachment));
            $this->assertFileExists($directory . $filename);
            $this->assertLogged('error', 'Announcement attachment cleanup failed: RuntimeException');
        } finally {
            unlink($directory . $filename);
        }
    }

    public function testDeleteConfirmationIsLocalizedAndOnlyShownToManagers(): void
    {
        $this->loginAs('admin', ['announcements.manage', 'announcements.access']);
        $owner = (new AnnouncementModel())->insert(['title' => '<script>delete</script>', 'body' => 'Content', 'status' => 'published', 'published_at' => '2026-10-14 01:00:00']);

        foreach (['en' => 'Delete this announcement?', 'zh-Hans' => '确认删除此公告？', 'zh-Hant' => '確認刪除此公告？'] as $locale => $title) {
            $body  = $this->get('/' . $locale . '/admin/announcements/' . $owner)->response()->getBody();
            $xpath = $this->dashboardXPath($body);
            $this->assertSame($title, $xpath->query('//*[@id="announcement-delete-title"]')->item(0)->textContent);
            $deleteButton = $xpath->query('//button[@data-bs-toggle="modal" and @data-bs-target="#announcement-delete"]')->item(0);
            $this->assertNotNull($deleteButton);
            $this->assertSame('span', $deleteButton->parentNode->nodeName);
            $this->assertSame('tooltip', $deleteButton->parentNode->getAttribute('data-bs-toggle'));
            $this->assertSame(lang('Announcements.delete'), $deleteButton->parentNode->getAttribute('data-bs-title'));
            $closeButton = $xpath->query('//*[@id="announcement-delete"]//button[@data-bs-dismiss="modal" and contains(@class, "btn-close")]')->item(0);
            $this->assertNotNull($closeButton);
            $this->assertSame('tooltip', $closeButton->getAttribute('data-bs-toggle'));
            $this->assertSame(lang('Announcements.cancel'), $closeButton->getAttribute('data-bs-title'));
            $form = $xpath->query('//form[contains(@action, "/announcements/delete/")]');
            $this->assertCount(1, $form);
            $this->assertSame('/' . $locale . '/admin/announcements/delete/' . $owner, $form->item(0)->getAttribute('action'));
            $this->assertSame('post', $form->item(0)->getAttribute('method'));
            $this->assertCount(1, $xpath->query('.//input[@name="' . csrf_token() . '"]', $form->item(0)));
            $this->assertStringNotContainsString('<script>delete</script>', $body);
        }

        auth()->user()->removePermission('announcements.manage');
        $body = $this->get('/en/admin/announcements/' . $owner)->response()->getBody();
        $this->assertStringNotContainsString('id="announcement-delete"', $body);
        $this->assertStringNotContainsString('/announcements/delete/', $body);
    }

    public function testDeleteRequiresManagementPermissionAndPost(): void
    {
        $model = new AnnouncementModel();
        $owner = $model->insert(['title' => 'Keep', 'body' => 'Content', 'status' => 'published', 'published_at' => '2026-10-14 01:00:00']);
        $route = '/en/admin/announcements/delete/' . $owner;
        $this->post($route, [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertNotNull($model->find($owner));
        $this->loginAs('admin', ['announcements.access']);
        $this->post($route, [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertNotNull($model->find($owner));
        auth()->user()->addPermission('announcements.manage');
        $this->get($route)->assertStatus(404);
        $this->assertNotNull($model->find($owner));
    }

    public function testDeleteRequiresCsrf(): void
    {
        $this->loginAs('admin', ['announcements.manage']);
        $model = new AnnouncementModel();
        $owner = $model->insert(['title' => 'Keep', 'body' => 'Content']);

        try {
            $this->post('/en/admin/announcements/delete/' . $owner);
            $this->fail('Deletion without CSRF must be rejected.');
        } catch (SecurityException $exception) {
            $this->assertNotNull($model->find($owner));
        }
    }

    public function testCreateRequiresCsrf(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $this->expectException(SecurityException::class);
        $this->post('/en/admin/announcements/create', ['title' => 'No token', 'body' => 'Rejected']);
    }

    public function testModulePersistsPermissionAndRegistersMenu(): void
    {
        $this->assertArrayNotHasKey('announcements.access', (new ReflectionClass(AuthGroups::class))->getDefaultProperties()['permissions']);
        $this->assertArrayNotHasKey('announcements.access', (new AuthGroups())->permissions);
        $this->assertArrayNotHasKey('announcements.manage', (new ReflectionClass(AuthGroups::class))->getDefaultProperties()['permissions']);
        $this->assertArrayNotHasKey('announcements.manage', (new AuthGroups())->permissions);
        $this->assertSame([], (new ReflectionClass(AdminMenu::class))->getDefaultProperties()['items']);
        $this->assertContains(Registrar::AdminMenu()['items'][0], config(AdminMenu::class)->items);
    }

    public function testMigrationRegistersPermissionsAndGrantsOnlySuperadminDomain(): void
    {
        Services::resetSingle('settings');
        $original             = setting('AuthGroups.matrix');
        $originalPermissions  = setting('AuthGroups.permissions');
        $matrix               = $original;
        $matrix['superadmin'] = ['admin.*', 'users.*', 'announcements.manage'];
        $matrix['admin']      = ['admin.access', 'announcements.access'];
        setting('AuthGroups.matrix', $matrix);
        service('settings')->forget('AuthGroups.permissions');

        try {
            $settings          = config('Settings')->database;
            $storedPermissions = db_connect($settings['group'])->table($settings['table'])->where('class', AuthGroups::class)->where('key', 'permissions');
            $this->assertSame(0, $storedPermissions->countAllResults());

            $migration = new GrantAnnouncementsToSuperadmin();
            $migration->up();
            $migration->up();

            Services::resetSingle('settings');
            $updated                = setting('AuthGroups.matrix');
            $expected               = $matrix;
            $expected['superadmin'] = ['admin.*', 'users.*', 'announcements.*'];
            $this->assertSame($expected, $updated);
            $this->assertTrue(PermissionMatcher::matches('announcements.publish', $updated['superadmin']));
            $this->assertSame('Can read published announcements', setting('AuthGroups.permissions')['announcements.access']);
            $this->assertSame('Can manage example announcements', setting('AuthGroups.permissions')['announcements.manage']);
            $this->assertSame(1, db_connect($settings['group'])->table($settings['table'])->where('class', AuthGroups::class)->where('key', 'permissions')->countAllResults());

            $permissions                         = setting('AuthGroups.permissions');
            $permissions['announcements.manage'] = 'Custom description';
            $permissions['announcements.access'] = 'Custom reader description';
            setting('AuthGroups.permissions', $permissions);
            Services::resetSingle('settings');
            $migration->up();
            Services::resetSingle('settings');
            $this->assertSame('Custom description', setting('AuthGroups.permissions')['announcements.manage']);
            $this->assertSame('Custom reader description', setting('AuthGroups.permissions')['announcements.access']);
            $this->assertSame($expected, setting('AuthGroups.matrix'));
        } finally {
            setting('AuthGroups.matrix', $original);
            setting('AuthGroups.permissions', $originalPermissions);
        }
    }

    public function testValidationRejectsMissingFields(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $this->post('/en/admin/announcements/create', [csrf_token() => csrf_hash(), 'title' => '', 'body' => ''])->assertRedirectTo('/en/admin/announcements/new');
        $this->assertNotEmpty(session('errors.title'));
        $this->assertNotEmpty(session('errors.body'));
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
    }

    public function testValidationRejectsOverlongFields(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);

        foreach ([['title' => str_repeat('A', 151), 'body' => 'Valid'], ['title' => 'Valid', 'body' => str_repeat('B', 2001)]] as $data) {
            $this->post('/en/admin/announcements/create', [csrf_token() => csrf_hash()] + $data)->assertRedirectTo('/en/admin/announcements/new');
        }
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
    }

    public function testCreateAndListEscapesContent(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $create = $this->get('/en/admin/announcements/new');
        $create->assertOK();
        $create->assertSee('New announcement');
        $created = $this->post('/en/admin/announcements/create', [
            csrf_token() => csrf_hash(),
            'title'      => '<script>alert(1)</script>',
            'body'       => 'Example body',
            'ignored'    => 'not stored',
        ]);

        $saved = (new AnnouncementModel())->first();
        $created->assertRedirectTo('/en/admin/announcements/show/' . $saved['id']);
        $this->assertSame('<script>alert(1)</script>', $saved['title']);
        $this->assertSame('Example body', $saved['body']);
        $this->assertSame(1, (new AnnouncementModel())->countAllResults());
        $page = $this->get('/en/admin/announcements');
        $page->assertOK();
        $page->assertSee('Example body');
        $this->assertStringContainsString('href="/en/admin/announcements"', $page->response()->getBody());
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $page->response()->getBody());
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page->response()->getBody());
        $this->assertStringContainsString('href="/en/admin/announcements/new"', $page->response()->getBody());
        $editLink = $this->dashboardXPath($page->response()->getBody())->query('//table//a[@href="/en/admin/announcements/edit/' . $saved['id'] . '"]')->item(0);
        $this->assertNotNull($editLink);
        $this->assertSame('tooltip', $editLink->getAttribute('data-bs-toggle'));
        $this->assertSame(lang('Announcements.edit'), $editLink->getAttribute('data-bs-title'));
    }

    public function testListReusesEmptyStateAndPaginationInBothLocales(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);

        foreach (['en', 'zh-Hans'] as $locale) {
            service('language')->setLocale($locale);
            $page = $this->get('/' . $locale . '/admin/announcements');
            $page->assertOK();
            TableLayoutAssertions::assertTablesInCards($page->response()->getBody(), paginated: true);
            $this->assertStringContainsString('empty-title', $page->response()->getBody());
            $this->assertStringContainsString('href="/' . $locale . '/admin/announcements/new"', $page->response()->getBody());
            $page->assertSee(lang('Announcements.empty'));
        }

        for ($index = 0; $index < 16; $index++) {
            (new AnnouncementModel())->insert(['title' => 'Page ' . $index, 'body' => 'Body']);
        }
        Services::resetSingle('pager');
        $page = $this->get('/en/admin/announcements?page=2');
        $page->assertOK();
        TableLayoutAssertions::assertTablesInCards($page->response()->getBody(), paginated: true);
        $this->assertStringContainsString('(16 - 16)', $page->response()->getBody());
        $this->assertStringNotContainsString('empty-title', $page->response()->getBody());
        $document = new DOMDocument();
        $document->loadHTML($page->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $this->assertCount(1, $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " page-link ") and contains(@href, "announcements?page=1")]'));
    }

    public function testListFiltersAndSortsWithSafeDefaults(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $model = new AnnouncementModel();
        $model->insert(['title' => 'Alpha MATCH', 'body' => 'First']);
        $first = $model->getInsertID();
        $model->insert(['title' => 'Beta', 'body' => 'match in body']);
        $second = $model->getInsertID();
        $model->insert(['title' => 'Outside', 'body' => 'match']);
        $model->builder()->where('id', $first)->update(['created_at' => '2026-10-01 00:00:00']);
        $model->builder()->where('id', $second)->update(['created_at' => '2026-10-01 23:59:59']);
        $model->builder()->where('title', 'Outside')->update(['created_at' => '2026-10-02 00:00:00']);

        $page = $this->get('/en/admin/announcements?q=MaTcH&created_from=2026-10-01&created_to=2026-10-01&sort=title&direction=asc');
        $page->assertOK();
        $page->assertSee('Alpha MATCH');
        $page->assertSee('Beta');
        $page->assertDontSee('Outside');
        $body = $page->response()->getBody();
        $this->assertLessThan(strpos($body, 'Beta'), strpos($body, 'Alpha MATCH'));

        $page = $this->get('/en/admin/announcements?sort=DROP%20TABLE&direction=invalid&created_from=invalid');
        $page->assertOK();
        $page->assertSee('Outside');
        $this->assertSame(3, $model->countAllResults());
    }

    public function testCsvEndpointsRequirePermissionAndImportRequiresCsrf(): void
    {
        foreach (['import', 'template', 'export'] as $endpoint) {
            $this->get('/en/admin/announcements/' . $endpoint)->assertRedirect();
        }
        $this->loginAs('admin');

        foreach (['import', 'template', 'export'] as $endpoint) {
            $this->get('/en/admin/announcements/' . $endpoint)->assertRedirect();
        }
        $this->post('/en/admin/announcements/import', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        auth()->logout();
        $this->loginAs('superadmin', ['announcements.manage']);
        $this->post('/en/admin/announcements/import', [csrf_token() => csrf_hash()])->assertRedirectTo('/en/admin/announcements/import');
        $this->assertNotEmpty(session('errors.file'));
        $this->expectException(SecurityException::class);
        $this->post('/en/admin/announcements/import');
    }

    public function testCsvImportValidatesRowsAndRendersEscapedReport(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $source = tempnam(sys_get_temp_dir(), 'announcement-csv-');
        file_put_contents($source, "title,body\n<script>Title</script>,Valid body\n,Invalid body\nLast,Last body\nExtra,Body,Column\n");

        try {
            $file = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$source, 'announcements.csv', null, filesize($source), UPLOAD_ERR_OK])
                ->onlyMethods(['isValid', 'getMimeType'])->getMock();
            $file->expects($this->once())->method('isValid')->willReturn(true);
            $file->method('getMimeType')->willReturn('text/csv');
            $controller = $this->csvController($file);
            $response   = $controller->import();
            $this->assertSame('/en/admin/announcements/import', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
            $this->assertSame(2, (new AnnouncementModel())->countAllResults());
            $report = session('announcement_import_report');
            $this->assertSame(['created', 'error', 'created', 'error'], array_column($report, 'result'));
            $this->assertSame([2, 3, 4, 5], array_column($report, 'row'));
            $this->assertNotEmpty($report[1]['errors']['title']);
            $this->assertSame('invalid', $report[3]['reason']);
            $page = $this->withSession(['announcement_import_report' => $report])->get('/en/admin/announcements/import');
            $page->assertOK();
            $page->assertSee('Import results');
            $page->assertSee('Last');
            $this->assertStringContainsString('&lt;script&gt;Title&lt;/script&gt;', $page->response()->getBody());
            $this->assertStringNotContainsString('<script>Title</script>', $page->response()->getBody());
            $this->assertStringNotContainsString('Admin.user', $page->response()->getBody());
            $document = new DOMDocument();
            @$document->loadHTML($page->response()->getBody());
            $xpath = new DOMXPath($document);
            $this->assertSame(1, $xpath->query('//form[@enctype="multipart/form-data" and contains(concat(" ", normalize-space(@class), " "), " card ")]/div[@class="card-body"]//input[@id="announcement-csv" and @required and @aria-describedby="announcement-csv-hint"]')->length);
            $this->assertSame(1, $xpath->query('//form[@enctype="multipart/form-data"]/div[@class="card-footer"]/button[@type="submit"]')->length);
            $this->assertSame(1, $xpath->query('//label[@for="announcement-csv" and contains(concat(" ", normalize-space(@class), " "), " required ")]')->length);
            $this->assertSame(1, $xpath->query('//*[@id="announcement-csv-hint" and @class="form-hint"]')->length);
            $this->assertSame(1, $xpath->query('//form[@enctype="multipart/form-data"]//input[@name="' . csrf_token() . '"]')->length);
            $invalid = $this->withSession(['errors' => ['file' => 'Invalid CSV']])->get('/en/admin/announcements/import');
            $invalid->assertOK();
            $this->assertStringContainsString('aria-describedby="announcement-csv-hint announcement-csv-error" aria-invalid="true"', $invalid->response()->getBody());
            $this->assertStringContainsString('id="announcement-csv-error" class="invalid-feedback">Invalid CSV', $invalid->response()->getBody());
        } finally {
            unlink($source);
        }
    }

    public function testCsvImportRejectsInvalidFilesBeforeWriting(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $source = tempnam(sys_get_temp_dir(), 'announcement-invalid-');

        try {
            foreach ([
                ['announcements.txt', "title,body\nTitle,Body\n", 30, true],
                ['announcements.csv', "title,body\nTitle,Body\n", 1024 * 1024 + 1, true],
                ['announcements.csv', "body,title\nBody,Title\n", 30, true],
                ['announcements.csv', "title,body\n" . str_repeat("Title,Body\n", 501), 6000, true],
                ['announcements.csv', "title,body\nTitle,Body\n", 30, false],
                ['announcements.csv', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==', true), 70, true],
            ] as [$name, $content, $size, $valid]) {
                file_put_contents($source, $content);
                session()->remove('errors');
                $file = $this->getMockBuilder(UploadedFile::class)
                    ->setConstructorArgs([$source, $name, null, $size, UPLOAD_ERR_OK])
                    ->onlyMethods(['isValid'])->getMock();
                $file->expects($this->once())->method('isValid')->willReturn($valid);
                $this->csvController($file)->import();
                $this->assertNotEmpty(session('errors.file'));
                $this->assertSame(0, (new AnnouncementModel())->countAllResults());
            }
        } finally {
            unlink($source);
        }
    }

    public function testCsvExportUsesListFiltersAndProtectsSpreadsheetCells(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $model = new AnnouncementModel();
        $model->insert(['title' => '=1+1', 'body' => 'Matching']);
        $model->builder()->update(['created_at' => '2026-10-01 23:59:59']);
        $model->insert(['title' => 'Excluded', 'body' => 'Matching']);
        $csv = $this->get('/en/admin/announcements/export?q=matching&created_to=2026-10-01');
        $csv->assertOK();
        $this->assertStringContainsString("'=1+1,Matching", $csv->response()->getBody());
        $this->assertStringNotContainsString('Excluded', $csv->response()->getBody());
        $this->assertStringContainsString('attachment;', $csv->response()->getHeaderLine('Content-Disposition'));
        $this->assertSame('nosniff', $csv->response()->getHeaderLine('X-Content-Type-Options'));
        $this->assertStringContainsString('private', $csv->response()->getHeaderLine('Cache-Control'));
        $template = $this->get('/en/admin/announcements/template');
        $template->assertOK();
        $this->assertSame("title,body\n", str_replace("\r\n", "\n", $template->response()->getBody()));
    }

    public function testAttachmentRoutesRequirePermissionAndExistingAnnouncement(): void
    {
        $route = '/en/admin/announcements/999999/attachments';

        foreach ([false, true] as $loggedIn) {
            if ($loggedIn) {
                $this->loginAs('admin');
            }
            $this->get($route)->assertRedirect();
            $this->get($route . '/1')->assertRedirect();
            $this->get($route . '/1/preview')->assertRedirect();
            $this->post($route, [csrf_token() => csrf_hash()])->assertRedirect();
            $this->post($route . '/1/remove', [csrf_token() => csrf_hash()])->assertRedirect();
        }
        auth()->logout();
        $this->loginAs('superadmin', ['announcements.manage']);
        $this->get($route)->assertStatus(404);
        $this->get($route . '/1')->assertStatus(404);
        $this->get($route . '/1/preview')->assertStatus(404);
        $this->post($route, [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->post($route . '/1/remove', [csrf_token() => csrf_hash()])->assertStatus(404);
    }

    public function testAttachmentWritesRequireCsrf(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);

        foreach (['/en/admin/announcements/1/attachments', '/en/admin/announcements/1/attachments/1/remove'] as $route) {
            try {
                $this->post($route);
                $this->fail('Missing CSRF token must be rejected.');
            } catch (SecurityException $exception) {
                $this->assertSame(0, (new AttachmentModel())->countAllResults());
            }
        }
    }

    public function testAttachmentsAreScopedAndCanBeUploadedDownloadedAndRemoved(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $model = new AnnouncementModel();
        $owner = (int) $model->insert(['title' => 'Owner', 'body' => 'Body']);
        $other = (int) $model->insert(['title' => 'Other', 'body' => 'Body']);
        $route = '/en/admin/announcements/' . $owner . '/attachments';
        $page  = $this->get($route);
        $page->assertOK();
        TableLayoutAssertions::assertTablesInCards($page->response()->getBody(), paginated: true);
        $maxSize = Attachments::maxBytes() / (1024 * 1024);
        $page->assertSee('Maximum ' . $maxSize . ' MB.');
        $this->post($route, [csrf_token() => csrf_hash()])->assertRedirectTo($route);
        $this->assertSame('Select an allowed file of ' . $maxSize . ' MB or less.', session('attachment_errors.file'));
        $this->assertSame(0, (new AttachmentModel())->countAllResults());
        $source = tempnam(sys_get_temp_dir(), 'announcement-file-');
        file_put_contents($source, 'private announcement attachment');
        $storedPath = null;

        try {
            $upload = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$source, '<script>.txt', null, filesize($source), UPLOAD_ERR_OK])
                ->onlyMethods(['isValid', 'move'])->getMock();
            $upload->expects($this->atLeastOnce())->method('isValid')->willReturn(true);
            $upload->expects($this->once())->method('move')->willReturnCallback(static function (string $target, ?string $name) use ($source, &$storedPath): bool {
                $storedPath = $target . '/' . $name;

                return copy($source, $storedPath);
            });
            $request = $this->getMockBuilder(IncomingRequest::class)
                ->setConstructorArgs([config('App'), service('uri'), null, new UserAgent()])
                ->onlyMethods(['getFile'])->getMock();
            $request->expects($this->once())->method('getFile')->with('file')->willReturn($upload);
            $controller = new AnnouncementAttachments();
            $controller->initController($request, Services::response(null, false), service('logger'));
            $response = $controller->upload($owner);
            $this->assertSame($route, parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
            $attachment = (new AttachmentModel())->first();
            $this->assertSame('announcement', $attachment['resource_type']);
            $this->assertSame($owner, (int) $attachment['resource_id']);
            $this->assertSame((int) auth()->id(), (int) $attachment['uploaded_by']);
            $this->assertFileExists($storedPath);
            $page = $this->get($route);
            $page->assertOK();
            TableLayoutAssertions::assertTablesInCards($page->response()->getBody(), paginated: true);
            $this->assertStringContainsString('&lt;script&gt;.txt', $page->response()->getBody());
            $this->assertStringNotContainsString('<script>.txt', $page->response()->getBody());
            $this->assertStringContainsString('action="' . $route . '/' . $attachment['id'] . '/remove"', $page->response()->getBody());
            $this->assertStringContainsString('href="' . $route . '/' . $attachment['id'] . '/preview"', $page->response()->getBody());
            $this->assertSame(1, substr_count($page->response()->getBody(), 'id="attachment-preview"'));
            $this->assertSame(1, substr_count($page->response()->getBody(), 'src="/static/js/attachment-preview.js"'));
            $otherRoute = '/en/admin/announcements/' . $other . '/attachments';
            $this->get($otherRoute . '/' . $attachment['id'])->assertStatus(404);
            $this->get($otherRoute . '/' . $attachment['id'] . '/preview')->assertStatus(404);
            $this->post($otherRoute . '/' . $attachment['id'] . '/remove', [csrf_token() => csrf_hash()])->assertStatus(404);
            $this->assertFileExists($storedPath);
            $otherPage = $this->get($otherRoute);
            $this->assertStringNotContainsString('&lt;script&gt;.txt', $otherPage->response()->getBody());
            $download = $this->get($route . '/' . $attachment['id']);
            $download->assertStatus(200);
            $this->assertInstanceOf(DownloadResponse::class, $download->response());
            $download->response()->buildHeaders();
            $this->assertStringContainsString('attachment;', $download->response()->getHeaderLine('Content-Disposition'));
            $this->assertStringContainsString('private', $download->response()->getHeaderLine('Cache-Control'));
            $this->assertSame('nosniff', $download->response()->getHeaderLine('X-Content-Type-Options'));
            $preview = $this->get($route . '/' . $attachment['id'] . '/preview');
            $preview->assertStatus(200);
            $this->assertInstanceOf(DownloadResponse::class, $preview->response());
            $preview->response()->buildHeaders();
            $this->assertStringStartsWith('inline;', $preview->response()->getHeaderLine('Content-Disposition'));
            $this->assertSame('text/plain; charset=UTF-8', $preview->response()->getHeaderLine('Content-Type'));
            $this->assertStringContainsString('private', $preview->response()->getHeaderLine('Cache-Control'));
            $this->assertStringContainsString('no-store', $preview->response()->getHeaderLine('Cache-Control'));
            $this->assertSame('nosniff', $preview->response()->getHeaderLine('X-Content-Type-Options'));
            $this->assertStringContainsString("default-src 'none'", $preview->response()->getHeaderLine('Content-Security-Policy'));
            $this->assertStringContainsString("style-src 'unsafe-inline'", $preview->response()->getHeaderLine('Content-Security-Policy'));
            $this->assertStringContainsString("frame-ancestors 'self'", $preview->response()->getHeaderLine('Content-Security-Policy'));
            $this->assertSame('SAMEORIGIN', $preview->response()->getHeaderLine('X-Frame-Options'));
            ob_start();
            $preview->response()->sendBody();
            $this->assertSame('private announcement attachment', ob_get_clean());
            $manager = auth()->user();
            auth()->logout();
            $this->loginAs('admin', ['announcements.access']);
            $reader = auth()->user();
            $this->get('/en/admin/announcements/' . $owner)->assertStatus(404);
            $this->get($route . '/' . $attachment['id'])->assertStatus(404);
            $this->get($route . '/' . $attachment['id'] . '/preview')->assertStatus(404);
            auth()->logout();
            auth()->login($manager);
            $this->post('/en/admin/announcements/' . $owner . '/publish', [csrf_token() => csrf_hash()])->assertRedirect();
            $this->post('/en/admin/announcements/' . $other . '/publish', [csrf_token() => csrf_hash()])->assertRedirect();
            auth()->logout();
            auth()->login($reader);
            $detail = $this->get('/en/admin/announcements/' . $owner);
            $detail->assertOK();
            $this->assertStringContainsString('&lt;script&gt;.txt', $detail->response()->getBody());
            $this->assertStringNotContainsString('<script>.txt', $detail->response()->getBody());
            TableLayoutAssertions::assertTablesInCards($detail->response()->getBody(), paginated: true);
            $this->assertStringContainsString('href="' . $route . '/' . $attachment['id'] . '"', $detail->response()->getBody());
            $this->assertStringContainsString('href="' . $route . '/' . $attachment['id'] . '/preview"', $detail->response()->getBody());
            $this->assertSame(1, substr_count($detail->response()->getBody(), 'id="attachment-preview"'));
            $this->assertSame(1, substr_count($detail->response()->getBody(), 'src="/static/js/attachment-preview.js"'));
            $this->assertStringNotContainsString('type="file"', $detail->response()->getBody());
            $this->assertStringNotContainsString('/remove"', $detail->response()->getBody());
            $readerDownload = $this->get($route . '/' . $attachment['id']);
            $readerDownload->assertStatus(200);
            $this->get($route . '/' . $attachment['id'] . '/preview')->assertStatus(200);
            $this->assertInstanceOf(DownloadResponse::class, $readerDownload->response());
            $this->get($otherRoute . '/' . $attachment['id'])->assertStatus(404);
            $this->post($route . '/' . $attachment['id'] . '/remove', [csrf_token() => csrf_hash()])->assertRedirect();
            $this->assertFileExists($storedPath);
            $reader->removeGroup('admin');
            $reader->addGroup('user');
            $reader->removePermission('announcements.access');
            $reader->addPermission('announcements.manage');
            auth()->logout();
            auth()->login(auth()->getProvider()->findById($reader->id));
            $this->assertFalse(auth()->user()->can('admin.access'));
            $this->assertFalse(auth()->user()->can('announcements.access'));
            $model->update($owner, ['status' => 'draft', 'published_at' => null]);
            $this->get('/en/admin/announcements/' . $owner)->assertOK();
            $this->get($route . '/' . $attachment['id'])->assertStatus(200);
            $this->get($route . '/' . $attachment['id'] . '/preview')->assertStatus(200);
            auth()->logout();
            auth()->login($manager);
            $this->post($route . '/' . $attachment['id'] . '/remove', [csrf_token() => csrf_hash()])->assertRedirectTo($route);
            $this->assertFileDoesNotExist($storedPath);
            $this->assertNull((new AttachmentModel())->find($attachment['id']));
            $emptyBody = $this->get('/en/admin/announcements/' . $owner)->response()->getBody();
            $this->assertStringNotContainsString('id="attachment-preview"', $emptyBody);
            $this->assertStringNotContainsString('src="/static/js/attachment-preview.js"', $emptyBody);
            $this->get($route . '/' . $attachment['id'])->assertStatus(404);
            $this->get($route . '/' . $attachment['id'] . '/preview')->assertStatus(404);
        } finally {
            unlink($source);
            if ($storedPath !== null && is_file($storedPath)) {
                unlink($storedPath);
            }
        }
    }

    public function testAttachmentPreviewTypesAndMissingFiles(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $owner     = (int) (new AnnouncementModel())->insert(['title' => 'Preview types', 'body' => 'Body']);
        $directory = WRITEPATH . 'uploads/attachments/';
        if (! is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $filename = bin2hex(random_bytes(16)) . '.txt';
        file_put_contents($directory . $filename, 'preview content');
        $model        = new AttachmentModel();
        $attachmentId = (int) $model->insert([
            'resource_type' => 'announcement', 'resource_id' => $owner, 'filename' => $filename,
            'original_name' => 'preview.txt', 'mime_type' => 'text/plain', 'size_bytes' => 15, 'uploaded_by' => auth()->id(),
        ]);
        $route = '/en/admin/announcements/' . $owner . '/attachments/' . $attachmentId;

        try {
            foreach ([
                'application/pdf'          => 'application/pdf',
                'image/jpeg'               => 'image/jpeg', 'image/png' => 'image/png', 'image/webp' => 'image/webp',
                'text/plain'               => 'text/plain; charset=UTF-8', 'text/csv' => 'text/plain; charset=UTF-8',
                'application/vnd.ms-excel' => 'text/plain; charset=UTF-8',
                'text/html'                => null, 'image/svg+xml' => null, 'application/octet-stream' => null,
            ] as $mime => $expected) {
                $model->update($attachmentId, ['mime_type' => $mime]);
                $preview = $this->get($route . '/preview');
                $preview->assertStatus($expected === null ? 415 : 200);
                if ($expected !== null) {
                    $preview->response()->buildHeaders();
                    $this->assertSame($expected, $preview->response()->getHeaderLine('Content-Type'));
                    $this->assertStringStartsWith('inline;', $preview->response()->getHeaderLine('Content-Disposition'));
                    $this->assertStringContainsString('filename="preview.txt"', $preview->response()->getHeaderLine('Content-Disposition'));
                    $this->assertSame('15', $preview->response()->getHeaderLine('Content-Length'));
                } else {
                    $download = $this->get($route);
                    $download->assertStatus(200);
                    $download->response()->buildHeaders();
                    $this->assertStringStartsWith('attachment;', $download->response()->getHeaderLine('Content-Disposition'));
                    $this->assertSame('application/octet-stream', $download->response()->getHeaderLine('Content-Type'));
                }
            }
            unlink($directory . $filename);
            $this->get($route . '/preview')->assertStatus(404);
            $this->get($route)->assertStatus(404);
        } finally {
            if (is_file($directory . $filename)) {
                unlink($directory . $filename);
            }
        }
    }

    public function testUserAttachmentWithMatchingResourceIdIsNotAccessible(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $owner      = (int) (new AnnouncementModel())->insert(['title' => 'Owner', 'body' => 'Body']);
        $attachment = (int) (new AttachmentModel())->insert([
            'resource_type' => 'user', 'resource_id' => $owner,
            'filename'      => bin2hex(random_bytes(16)) . '.txt', 'original_name' => 'user-only.txt',
            'mime_type'     => 'text/plain', 'size_bytes' => 4, 'uploaded_by' => auth()->id(),
        ]);
        $route = '/en/admin/announcements/' . $owner . '/attachments';
        $page  = $this->get($route);
        $page->assertDontSee('user-only.txt');
        $this->get($route . '/' . $attachment)->assertStatus(404);
        $this->get($route . '/' . $attachment . '/preview')->assertStatus(404);
        $this->post($route . '/' . $attachment . '/remove', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->assertNotNull((new AttachmentModel())->find($attachment));
    }

    public function testFilteredPaginationAndSortLinksPreserveOnlySupportedParameters(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $model = new AnnouncementModel();

        for ($index = 0; $index < 16; $index++) {
            $model->insert(['title' => 'Matching ' . chr(65 + $index), 'body' => 'Body']);
        }
        $model->builder()->update(['created_at' => '2026-10-01 12:00:00']);
        Services::resetSingle('pager');
        $page = $this->get('/en/admin/announcements?q=matching&created_from=2026-10-01&sort=title&direction=asc&page=2&ignored=unsafe');
        $page->assertOK();
        $page->assertSee('(16 - 16)');
        $document = new DOMDocument();
        @$document->loadHTML($page->response()->getBody());
        $xpath = new DOMXPath($document);
        $links = $xpath->query('//a[contains(@href, "announcements?")]');
        $this->assertGreaterThanOrEqual(1, $links->length);

        foreach ($links as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $parameters);
            $this->assertSame('matching', $parameters['q']);
            $this->assertSame('2026-10-01', $parameters['created_from']);
            $this->assertArrayNotHasKey('ignored', $parameters);
        }
        $sortForms = $xpath->query('//th/form');
        $this->assertSame(3, $sortForms->length);

        foreach ($sortForms as $form) {
            $this->assertSame('matching', $xpath->query('.//input[@name="q"]', $form)->item(0)->getAttribute('value'));
            $this->assertSame('2026-10-01', $xpath->query('.//input[@name="created_from"]', $form)->item(0)->getAttribute('value'));
            $this->assertSame(0, $xpath->query('.//input[@name="page" or @name="ignored"]', $form)->length);
        }
        $empty = $this->get('/en/admin/announcements?q=0');
        $empty->assertOK();
        $empty->assertSee('No matching announcements.');
    }

    public function testCsvExportRejectsMoreThanTenThousandRows(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        (new AnnouncementModel())->insertBatch(array_fill(0, 10001, ['title' => 'Bulk', 'body' => 'Body']));
        $result = $this->get('/en/admin/announcements/export');
        $result->assertStatus(413);
        $this->assertSame(lang('Admin.exportLimit'), $result->response()->getBody());
    }

    public function testRepeatedCsvImportCreatesNewRecordsWithOriginalFields(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $source = tempnam(sys_get_temp_dir(), 'announcement-repeat-');
        file_put_contents($source, "title,body\nRepeated title,Original body\n");

        try {
            for ($iteration = 1; $iteration <= 2; $iteration++) {
                $file = $this->getMockBuilder(UploadedFile::class)
                    ->setConstructorArgs([$source, 'announcements.csv', null, filesize($source), UPLOAD_ERR_OK])
                    ->onlyMethods(['isValid'])->getMock();
                $file->expects($this->once())->method('isValid')->willReturn(true);
                $this->csvController($file)->import();
                $this->assertSame('created', session('announcement_import_report')[0]['result']);
                $rows = (new AnnouncementModel())->findAll();
                $this->assertCount($iteration, $rows);

                foreach ($rows as $row) {
                    $this->assertSame('Repeated title', $row['title']);
                    $this->assertSame('Original body', $row['body']);
                    $this->assertSame('draft', $row['status']);
                    $this->assertNull($row['published_at']);
                }
            }
        } finally {
            unlink($source);
        }
    }

    public function testCsvImportAcceptsExactlyFiveHundredRowsAndOneMegabyte(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $source = tempnam(sys_get_temp_dir(), 'announcement-limit-');
        $csv    = "title,body\n";

        for ($index = 0; $index < 500; $index++) {
            $csv .= str_repeat('A', $index === 0 ? 128 : ($index === 1 ? 127 : 95)) . ',' . str_repeat('B', 2000) . "\n";
        }
        $this->assertSame(1024 * 1024, strlen($csv));
        file_put_contents($source, $csv);

        try {
            $file = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$source, 'announcements.csv', null, filesize($source), UPLOAD_ERR_OK])
                ->onlyMethods(['isValid'])->getMock();
            $file->expects($this->once())->method('isValid')->willReturn(true);
            $this->csvController($file)->import();
            $this->assertSame(500, (new AnnouncementModel())->countAllResults());
            $report = session('announcement_import_report');
            $this->assertCount(500, $report);
            $this->assertSame(['created'], array_values(array_unique(array_column($report, 'result'))));
            $this->assertSame(501, $report[499]['row']);
        } finally {
            unlink($source);
        }
    }

    public function testExportPreservesOrderingAndDateBoundsWithoutPagination(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $model = new AnnouncementModel();

        for ($index = 15; $index >= 0; $index--) {
            $model->insert(['title' => sprintf('Selected %02d', $index), 'body' => 'Matching']);
        }
        $model->builder()->update(['created_at' => '2026-10-01 12:00:00']);
        $before = $model->insert(['title' => 'Selected Before', 'body' => 'Matching']);
        $after  = $model->insert(['title' => 'Selected After', 'body' => 'Matching']);
        $model->builder()->where('id', $before)->update(['created_at' => '2026-09-30 23:59:59']);
        $model->builder()->where('id', $after)->update(['created_at' => '2026-10-02 00:00:00']);
        $parameters = '?q=selected&created_from=2026-10-01&created_to=2026-10-01&sort=title&direction=asc&page=2';
        Services::resetSingle('pager');
        $page = $this->get('/en/admin/announcements' . $parameters);
        $page->assertOK();
        $page->assertDontSee('Selected Before');
        $page->assertDontSee('Selected After');
        $page->assertSee('Selected 15');
        $page->assertDontSee('Selected 00');
        $export = $this->get('/en/admin/announcements/export' . $parameters);
        $export->assertOK();
        $stream = fopen('php://temp', 'w+b');

        try {
            fwrite($stream, $export->response()->getBody());
            rewind($stream);
            $this->assertSame(['title', 'body'], fgetcsv($stream, escape: ''));

            for ($index = 0; $index < 16; $index++) {
                $this->assertSame([sprintf('Selected %02d', $index), 'Matching'], fgetcsv($stream, escape: ''));
            }
            $this->assertFalse(fgetcsv($stream, escape: ''));
        } finally {
            fclose($stream);
        }
    }

    public function testCsvExportAcceptsExactlyTenThousandRows(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        (new AnnouncementModel())->insertBatch(array_fill(0, 10000, ['title' => 'Bulk', 'body' => 'Body']));
        $result = $this->get('/en/admin/announcements/export');
        $result->assertOK();
        $this->assertSame(10000, substr_count($result->response()->getBody(), "Bulk,Body\n"));
    }

    public function testPublicationDefaultsToDraftAndReadersOnlySeePublishedRecords(): void
    {
        $model       = new AnnouncementModel();
        $draftId     = $model->insert(['title' => 'Private draft', 'body' => 'Draft body']);
        $publishedId = $model->insert(['title' => 'Published notice', 'body' => 'Public body', 'status' => 'published', 'published_at' => '2026-10-09 12:00:00']);
        $draft       = $model->find($draftId);
        $this->assertSame('draft', $draft['status']);
        $this->assertNull($draft['published_at']);
        $readable = (new AnnouncementModel())->visibleTo(false)->findAll();
        $this->assertCount(1, $readable);
        $this->assertSame((int) $publishedId, (int) $readable[0]['id']);
        $this->assertCount(2, (new AnnouncementModel())->visibleTo(true)->findAll());
    }

    public function testDraftEditPublishAndPublishedEditPreserveLifecycleFields(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $created = $this->post('/en/admin/announcements/create', [
            csrf_token() => csrf_hash(), 'title' => 'Draft title', 'body' => 'Draft body',
            'status'     => 'published', 'published_at' => '2000-01-01 00:00:00',
        ]);
        $model       = new AnnouncementModel();
        $draft       = $model->first();
        $route       = '/en/admin/announcements/' . $draft['id'];
        $detailRoute = '/en/admin/announcements/show/' . $draft['id'];
        $editRoute   = '/en/admin/announcements/edit/' . $draft['id'];
        $updateRoute = '/en/admin/announcements/update/' . $draft['id'];
        $created->assertRedirectTo($detailRoute);
        $this->assertSame('draft', $draft['status']);
        $this->assertNull($draft['published_at']);
        $detail = $this->get($route);
        $detail->assertOK();
        $this->assertStringContainsString('action="' . $route . '/publish"', $detail->response()->getBody());
        $this->assertStringContainsString('href="' . $route . '/attachments"', $detail->response()->getBody());
        $this->assertStringNotContainsString('attachment-file', $detail->response()->getBody());
        $edit = $this->get($editRoute);
        $edit->assertOK();
        $document = new DOMDocument();
        @$document->loadHTML($edit->response()->getBody());
        $xpath = new DOMXPath($document);
        $this->assertSame('Draft title', $xpath->query('//input[@name="title"]')->item(0)->getAttribute('value'));
        $this->assertSame($updateRoute, $xpath->query('//form[contains(@action, "announcements")]')->item(0)->getAttribute('action'));
        $this->post($updateRoute, [csrf_token() => csrf_hash(), 'title' => '<img src=x onerror=alert(1)>', 'body' => '<script>unsafe()</script>', 'status' => 'published', 'published_at' => '2000-01-01 00:00:00'])->assertRedirectTo($detailRoute);
        $edited = $model->find($draft['id']);
        $this->assertSame('draft', $edited['status']);
        $this->assertNull($edited['published_at']);
        $detail = $this->get($route);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $detail->response()->getBody());
        $this->assertStringContainsString('&lt;script&gt;unsafe()&lt;/script&gt;', $detail->response()->getBody());
        $this->assertStringNotContainsString('<script>unsafe()</script>', $detail->response()->getBody());
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');

        try {
            $beforePublication = gmdate('Y-m-d H:i:s');
            $this->post($route . '/publish', [csrf_token() => csrf_hash(), 'published_at' => '2000-01-01 00:00:00'])->assertRedirectTo($detailRoute);
            $afterPublication = gmdate('Y-m-d H:i:s');
        } finally {
            date_default_timezone_set($originalTimezone);
        }
        $published = $model->find($draft['id']);
        $this->assertSame('published', $published['status']);
        $this->assertNotNull($published['published_at']);
        $this->assertNotSame('2000-01-01 00:00:00', $published['published_at']);
        $this->assertGreaterThanOrEqual($beforePublication, $published['published_at']);
        $this->assertLessThanOrEqual($afterPublication, $published['published_at']);
        $model->update($draft['id'], ['published_at' => '2026-10-01 12:00:00']);
        $this->post($route . '/publish', [csrf_token() => csrf_hash()])->assertRedirectTo($detailRoute);
        $this->assertSame('2026-10-01 12:00:00', $model->find($draft['id'])['published_at']);
        $edit = $this->get($editRoute);
        $edit->assertSee('Saved changes will be visible to readers immediately.');
        $this->post($updateRoute, [csrf_token() => csrf_hash(), 'title' => 'Corrected publication', 'body' => 'Corrected content', 'status' => 'draft', 'published_at' => null])->assertRedirectTo($detailRoute);
        $updated = $model->find($draft['id']);
        $this->assertSame('published', $updated['status']);
        $this->assertSame('2026-10-01 12:00:00', $updated['published_at']);
        $this->assertSame('Corrected publication', $updated['title']);
        $this->assertSame('Corrected content', $updated['body']);
        auth()->logout();
        $this->loginAs('admin', ['announcements.access']);
        $this->get($route)->assertSee('Corrected content');
    }

    public function testBackendReadersOnlySeePublishedAnnouncementsAndReadingControls(): void
    {
        $model       = new AnnouncementModel();
        $draftId     = $model->insert(['title' => 'Confidential draft', 'body' => 'Secret body']);
        $publishedId = $model->insert(['title' => 'Published first', 'body' => 'Readable body', 'status' => 'published', 'published_at' => '2026-10-01 23:59:59']);
        $model->insert(['title' => 'Published second', 'body' => 'Readable body', 'status' => 'published', 'published_at' => '2026-10-02 00:00:00']);
        $this->loginAs('user', ['announcements.access']);
        $this->assertFalse(auth()->user()->can('admin.access'));
        $page = $this->get('/en/admin/announcements');
        $page->assertOK();
        $page->assertDontSee('Confidential draft');
        $page->assertDontSee('Secret body');
        $page->assertSee('Published first');
        $this->assertLessThan(strpos($page->response()->getBody(), 'Published first'), strpos($page->response()->getBody(), 'Published second'));
        $this->assertStringNotContainsString('/announcements/new"', $page->response()->getBody());
        $this->assertStringNotContainsString('/announcements/import"', $page->response()->getBody());
        $this->assertStringNotContainsString('/announcements/export?', $page->response()->getBody());
        $this->assertStringNotContainsString('name="status"', $page->response()->getBody());
        $page->assertSee('Published from (UTC)');
        $filtered = $this->get('/en/admin/announcements?q=Secret&status=draft');
        $filtered->assertDontSee('Confidential draft');
        $this->get('/en/admin/announcements/' . $draftId)->assertStatus(404);
        $this->get('/en/admin/announcements/show/' . $draftId)->assertStatus(404);
        $detail = $this->get('/en/admin/announcements/' . $publishedId);
        $detail->assertOK();
        $detail->assertSee('Readable body');
        $this->get('/en/admin/announcements/show/' . $publishedId)->assertSee('Readable body');
        $this->assertStringNotContainsString('/publish"', $detail->response()->getBody());
        $this->assertStringNotContainsString('/announcements/edit/', $detail->response()->getBody());
        $this->assertStringContainsString('no-store', $detail->response()->getHeaderLine('Cache-Control'));
        $range = $this->get('/en/admin/announcements?created_from=2026-10-01&created_to=2026-10-01');
        $range->assertSee('Published first');
        $range->assertDontSee('Published second');
        $route                 = '/en/admin/announcements/' . $publishedId;
        $originalAnnouncements = $model->findAll();
        $attachments           = new AttachmentModel();
        $originalAttachments   = $attachments->countAllResults();
        $deniedUrl             = config('Auth')->permissionDeniedRedirect();

        foreach (['/en/admin/announcements/new', '/en/admin/announcements/edit/' . $publishedId, '/en/admin/announcements/import', '/en/admin/announcements/export', '/en/admin/announcements/template', $route . '/attachments'] as $managementRoute) {
            $this->get($managementRoute)->assertRedirectTo($deniedUrl);
        }

        foreach (['/en/admin/announcements/create', '/en/admin/announcements/update/' . $publishedId, $route . '/publish', '/en/admin/announcements/import', $route . '/attachments', $route . '/attachments/1/remove'] as $managementRoute) {
            $this->post($managementRoute, [csrf_token() => csrf_hash(), 'title' => 'Unauthorized change', 'body' => 'Unauthorized content'])->assertRedirectTo($deniedUrl);
            $this->assertSame($originalAnnouncements, $model->findAll());
            $this->assertSame($originalAttachments, $attachments->countAllResults());
        }

        auth()->user()->removePermission('announcements.access');
        $readerId = auth()->id();
        auth()->logout();
        auth()->login(auth()->getProvider()->findById($readerId));
        $this->get('/en/admin/announcements')->assertRedirect();
        $this->get('/en/admin/announcements/' . $publishedId)->assertRedirect();
    }

    public function testLifecycleWritesRequireManagementPermissionAndCsrf(): void
    {
        $model          = new AnnouncementModel();
        $announcementId = $model->insert(['title' => 'Protected draft', 'body' => 'Unchanged']);
        $route          = '/en/admin/announcements/' . $announcementId;
        $this->get($route)->assertRedirect();

        foreach ([null, 'admin', 'user'] as $group) {
            if ($group !== null) {
                auth()->logout();
                $this->loginAs($group);
            }
            $this->get('/en/admin/announcements/edit/' . $announcementId)->assertRedirect();
            $this->post('/en/admin/announcements/update/' . $announcementId, [csrf_token() => csrf_hash(), 'title' => 'Tampered', 'body' => 'Tampered'])->assertRedirect();
            $this->post($route . '/publish', [csrf_token() => csrf_hash()])->assertRedirect();
            $this->assertSame('Protected draft', $model->find($announcementId)['title']);
            $this->assertSame('draft', $model->find($announcementId)['status']);
        }
        auth()->logout();
        $this->loginAs('superadmin', ['announcements.manage']);

        foreach (['/en/admin/announcements/update/' . $announcementId, $route . '/publish'] as $writeRoute) {
            try {
                $this->post($writeRoute, ['title' => 'Tampered', 'body' => 'Tampered']);
                $this->fail('Missing CSRF token must be rejected.');
            } catch (SecurityException $exception) {
                $this->assertSame('Protected draft', $model->find($announcementId)['title']);
                $this->assertSame('draft', $model->find($announcementId)['status']);
            }
        }
        $this->get($route . '/publish')->assertStatus(404);
    }

    public function testMissingResourcesAndInvalidUpdatesDoNotChangeAnnouncements(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $missing = '/en/admin/announcements/999999';
        $this->get($missing)->assertStatus(404);
        $this->get('/en/admin/announcements/edit/999999')->assertStatus(404);
        $this->post('/en/admin/announcements/update/999999', [csrf_token() => csrf_hash(), 'title' => 'Missing', 'body' => 'Missing'])->assertStatus(404);
        $this->post($missing . '/publish', [csrf_token() => csrf_hash()])->assertStatus(404);
        $model          = new AnnouncementModel();
        $announcementId = $model->insert(['title' => 'Original', 'body' => 'Original body']);
        $route          = '/en/admin/announcements/update/' . $announcementId;
        $editRoute      = '/en/admin/announcements/edit/' . $announcementId;

        foreach ([['title' => '', 'body' => ''], ['title' => str_repeat('A', 151), 'body' => 'Valid'], ['title' => 'Valid', 'body' => str_repeat('B', 2001)]] as $invalid) {
            $this->post($route, [csrf_token() => csrf_hash()] + $invalid)->assertRedirectTo($editRoute);
            $this->assertNotEmpty(session('errors'));
            $saved = $model->find($announcementId);
            $this->assertSame('Original', $saved['title']);
            $this->assertSame('Original body', $saved['body']);
            $this->assertSame('draft', $saved['status']);
            $this->assertNull($saved['published_at']);
        }
        $this->assertSame(1, $model->countAllResults());
    }

    public function testStatusFilteringIsPreservedInPaginationSortingAndExport(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $model = new AnnouncementModel();
        $model->insertBatch(array_fill(0, 16, ['title' => 'Matching draft', 'body' => 'Body']));
        $model->insert(['title' => 'Matching publication', 'body' => 'Body', 'status' => 'published', 'published_at' => '2026-10-01 12:00:00']);
        Services::resetSingle('pager');
        $page = $this->get('/en/admin/announcements?q=Matching&status=draft&page=2');
        $page->assertOK();
        $page->assertSee('(16 - 16)');
        $page->assertDontSee('Matching publication');
        $document = new DOMDocument();
        @$document->loadHTML($page->response()->getBody());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//select[@name="status"]/option[@selected and @value="draft"]')->length);
        $this->assertSame(3, $xpath->query('//th/form/input[@name="status" and @value="draft"]')->length);
        $links = $xpath->query('//a[contains(@href, "announcements?")]');
        $this->assertGreaterThan(0, $links->length);

        foreach ($links as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $parameters);
            $this->assertSame('draft', $parameters['status']);
        }
        $csv = $this->get('/en/admin/announcements/export?q=Matching&status=draft&page=2');
        $csv->assertOK();
        $stream = fopen('php://temp', 'w+b');

        try {
            fwrite($stream, $csv->response()->getBody());
            rewind($stream);
            $this->assertSame(['title', 'body'], fgetcsv($stream, escape: ''));

            for ($index = 0; $index < 16; $index++) {
                $this->assertSame(['Matching draft', 'Body'], fgetcsv($stream, escape: ''));
            }
            $this->assertFalse(fgetcsv($stream, escape: ''));
        } finally {
            fclose($stream);
        }
        Services::resetSingle('pager');
        $published = $this->get('/en/admin/announcements?status=published');
        $published->assertDontSee('Matching draft');
        $published->assertSee('Matching publication');
        Services::resetSingle('pager');
        $this->get('/en/admin/announcements?status=invalid')->assertSee('Matching draft');
        $this->get('/en/admin/announcements?status%5B%5D=draft')->assertOK();
    }

    public function testPublicationMigrationPreservesExistingContentAsDrafts(): void
    {
        $migration = new AddPublicationState();
        $migration->down();

        try {
            $this->db->table('example_announcements')->insert(['title' => 'Legacy content', 'body' => 'Preserved legacy body', 'created_at' => '2026-10-01 12:00:00']);
        } finally {
            $migration->up();
        }
        $legacy = (new AnnouncementModel())->first();
        $this->assertSame('Legacy content', $legacy['title']);
        $this->assertSame('Preserved legacy body', $legacy['body']);
        $this->assertSame('2026-10-01 12:00:00', $legacy['created_at']);
        $this->assertSame('draft', $legacy['status']);
        $this->assertNull($legacy['published_at']);
        $this->assertCount(0, (new AnnouncementModel())->visibleTo(false)->findAll());
    }

    public function testEditFormRestoresOriginalInputWithoutDoubleEscaping(): void
    {
        $this->loginAs('superadmin', ['announcements.manage']);
        $announcementId = (new AnnouncementModel())->insert(['title' => 'Original', 'body' => 'Original body']);
        $page           = $this->withSession(['_ci_old_input' => ['post' => ['title' => '<script>&"', 'body' => '<img>&"']]])->get('/en/admin/announcements/edit/' . $announcementId);
        $page->assertOK();
        $document = new DOMDocument();
        @$document->loadHTML($page->response()->getBody());
        $xpath = new DOMXPath($document);
        $this->assertSame('<script>&"', $xpath->query('//input[@name="title"]')->item(0)->getAttribute('value'));
        $this->assertSame('<img>&"', $xpath->query('//textarea[@name="body"]')->item(0)->textContent);
        $this->assertStringNotContainsString('<script>&"', $page->response()->getBody());
    }

    public function testManagerOnlyPermissionProvidesCompleteAnnouncementAccess(): void
    {
        $this->loginAs('user');
        $manager = auth()->user();
        $manager->addPermission('announcements.manage');
        auth()->logout();
        auth()->login(auth()->getProvider()->findById($manager->id));
        $this->assertTrue(auth()->user()->can('announcements.manage'));
        $this->assertFalse(auth()->user()->can('admin.access'));
        $this->assertFalse(auth()->user()->can('announcements.access'));
        $dashboard = $this->get('/en/admin/dashboard');
        $dashboard->assertOK();
        $this->assertStringContainsString('href="/en/admin/announcements"', $dashboard->response()->getBody());
        $document = new DOMDocument();
        @$document->loadHTML($dashboard->response()->getBody());
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//div[@id="sidebar-menu"]/ul/li/hr')->length);
        $this->get('/en/admin/announcements/new')->assertOK();
        $created     = $this->post('/en/admin/announcements/create', [csrf_token() => csrf_hash(), 'title' => 'Module-only draft', 'body' => 'Module-only body']);
        $draft       = (new AnnouncementModel())->first();
        $route       = '/en/admin/announcements/' . $draft['id'];
        $detailRoute = '/en/admin/announcements/show/' . $draft['id'];
        $created->assertRedirectTo($detailRoute);
        $this->get('/en/admin/announcements')->assertSee('Module-only draft');
        $this->get($route)->assertSee('Module-only body');
        $this->get('/en/admin/announcements/edit/' . $draft['id'])->assertOK();
        $this->get($route . '/attachments')->assertOK();
        $this->post($route . '/publish', [csrf_token() => csrf_hash()])->assertRedirectTo($detailRoute);
        $this->get($route)->assertOK();
    }

    #[DataProvider('providePublicationConstraintRejectsInvalidStates')]
    public function testPublicationConstraintRejectsInvalidStates(array $state): void
    {
        $this->db->transException(true);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('example_announcements_publication_check');

        try {
            $this->db->table('example_announcements')->insert(['title' => 'Invalid publication', 'body' => 'Body'] + $state);
        } finally {
            $this->db->transException(false);
        }
    }

    public static function providePublicationConstraintRejectsInvalidStates(): iterable
    {
        return [
            'published without timestamp' => [['status' => 'published', 'published_at' => null]],
            'draft with timestamp'        => [['status' => 'draft', 'published_at' => '2026-10-01 12:00:00']],
            'unsupported status'          => [['status' => 'archived', 'published_at' => null]],
        ];
    }

    #[DataProvider('provideDefaultRolesHaveNoAnnouncementPermissions')]
    public function testDefaultRolesHaveNoAnnouncementPermissions(string $group): void
    {
        $this->loginAs($group);
        $this->assertFalse(auth()->user()->can('announcements.access'));
        $this->assertFalse(auth()->user()->can('announcements.manage'));
        $this->get('/en/admin/announcements')->assertRedirect();
        $this->get('/en/admin/announcements/1')->assertRedirect();
        $this->get('/en/admin/announcements/1/attachments/1')->assertRedirect();
        $this->get('/en/admin/announcements/new')->assertRedirect();
        $dashboard = $this->get('/en/admin/dashboard');
        $dashboard->assertOK();
        $this->assertStringNotContainsString('href="/en/admin/announcements"', $dashboard->response()->getBody());
    }

    public static function provideDefaultRolesHaveNoAnnouncementPermissions(): iterable
    {
        return [['admin'], ['developer'], ['user']];
    }

    public function testSuperadminHasAnnouncementDomainPermissionByDefault(): void
    {
        $draftId = (new AnnouncementModel())->insert(['title' => 'Superadmin draft', 'body' => 'Private content']);
        $this->loginAs('superadmin');
        $this->assertSame([], auth()->user()->getPermissions());
        $this->assertContains('announcements.*', setting('AuthGroups.matrix')['superadmin']);
        $this->assertTrue(auth()->user()->can('announcements.access'));
        $this->assertTrue(auth()->user()->can('announcements.manage'));
        $this->get('/en/admin/announcements')->assertSee('Superadmin draft');
        $this->get('/en/admin/announcements/' . $draftId)->assertSee('Private content');
        $this->get('/en/admin/announcements/new')->assertOK();
        $dashboard = $this->get('/en/admin/dashboard');
        $this->assertStringContainsString('href="/en/admin/announcements"', $dashboard->response()->getBody());
        $document = new DOMDocument();
        @$document->loadHTML($dashboard->response()->getBody());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//div[@id="sidebar-menu"]/ul/li/hr')->length);
        $this->assertSame(1, $xpath->query("//div[@id='sidebar-menu']/ul/li[a[@href='/en/admin/announcements']]/following-sibling::li[hr]")->length);
        $this->assertSame('/en/admin/users', $xpath->query('//div[@id="sidebar-menu"]/ul/li[hr]/following-sibling::li[1]/a')->item(0)->getAttribute('href'));
        $this->get('/en/admin/settings/roles?role=superadmin')->assertSee('announcements.*');
    }

    public function testAnnouncementPermissionsAreAssignedAndRevokedThroughRoleSettings(): void
    {
        $originalMatrix = setting('AuthGroups.matrix');
        $model          = new AnnouncementModel();
        $publishedId    = $model->insert(['title' => 'Published via role access', 'body' => 'Reader content', 'status' => 'published', 'published_at' => '2026-10-01 12:00:00']);
        $draftId        = $model->insert(['title' => 'Role-managed draft', 'body' => 'Private content']);
        $this->loginAs('user');
        $readerId = auth()->id();
        $this->get('/en/admin/announcements')->assertRedirect();
        auth()->logout();
        $this->loginAs('superadmin');
        $operatorId = auth()->id();
        $this->assertTrue(auth()->user()->can('announcements.manage'));
        $roles = $this->get('/en/admin/settings/roles?role=user');
        $roles->assertOK();
        $this->assertStringContainsString('announcements.access', $roles->response()->getBody());
        $this->assertStringContainsString('announcements.manage', $roles->response()->getBody());

        try {
            $route = '/en/admin/settings/roles/user/permissions';
            $this->post($route, [csrf_token() => csrf_hash(), 'permissions' => ['announcements.access']])->assertRedirectTo('/en/admin/settings/roles?role=user');
            $expected         = $originalMatrix;
            $expected['user'] = ['announcements.access'];
            $this->assertSame($expected, setting('AuthGroups.matrix'));
            auth()->logout();
            auth()->login(auth()->getProvider()->findById($readerId));
            $this->assertTrue(auth()->user()->can('announcements.access'));
            $this->assertFalse(auth()->user()->can('admin.access'));
            $this->assertFalse(auth()->user()->can('announcements.manage'));
            $dashboard = $this->get('/en/admin/dashboard');
            $this->assertStringContainsString('href="/en/admin/announcements"', $dashboard->response()->getBody());
            $this->get('/en/admin/announcements')->assertSee('Published via role access');
            $this->get('/en/admin/announcements/' . $publishedId)->assertSee('Reader content');
            $this->get('/en/admin/announcements/' . $draftId)->assertStatus(404);
            $this->get('/en/admin/announcements/new')->assertRedirect();
            $this->post('/en/admin/announcements/' . $draftId . '/publish', [csrf_token() => csrf_hash()])->assertRedirect();
            $this->assertSame('draft', $model->find($draftId)['status']);

            auth()->logout();
            auth()->login(auth()->getProvider()->findById($operatorId));
            $this->post($route, [csrf_token() => csrf_hash(), 'permissions' => ['announcements.manage']])->assertRedirectTo('/en/admin/settings/roles?role=user');
            auth()->logout();
            auth()->login(auth()->getProvider()->findById($readerId));
            $this->assertTrue(auth()->user()->can('announcements.manage'));
            $this->assertFalse(auth()->user()->can('announcements.access'));
            $this->get('/en/admin/announcements/new')->assertOK();
            $this->get('/en/admin/announcements/' . $draftId)->assertSee('Private content');
            $this->post('/en/admin/announcements/' . $draftId . '/publish', [csrf_token() => csrf_hash()])->assertRedirectTo('/en/admin/announcements/show/' . $draftId);
            $this->assertSame('published', $model->find($draftId)['status']);

            auth()->logout();
            auth()->login(auth()->getProvider()->findById($operatorId));
            $this->post($route, [csrf_token() => csrf_hash(), 'permissions' => []])->assertRedirectTo('/en/admin/settings/roles?role=user');
            auth()->logout();
            auth()->login(auth()->getProvider()->findById($readerId));
            $this->get('/en/admin/announcements')->assertRedirect();
            $this->get('/en/admin/announcements/' . $publishedId)->assertRedirect();
            $dashboard = $this->get('/en/admin/dashboard');
            $this->assertStringNotContainsString('href="/en/admin/announcements"', $dashboard->response()->getBody());
        } finally {
            setting('AuthGroups.matrix', $originalMatrix);
        }
    }

    private function csvController(UploadedFile $file): Announcements
    {
        $request = $this->getMockBuilder(IncomingRequest::class)
            ->setConstructorArgs([config('App'), service('uri'), null, new UserAgent()])
            ->onlyMethods(['getFile'])->getMock();
        $request->expects($this->once())->method('getFile')->with('file')->willReturn($file);
        $controller = new Announcements();
        $controller->initController($request, Services::response(null, false), service('logger'));

        return $controller;
    }

    private function loginAs(string $group, array $permissions = []): void
    {
        $user        = new AdminUser(['username' => 'example' . $group]);
        $user->email = 'example' . $group . '@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($group);
        if ($permissions !== []) {
            $user->addPermission(...$permissions);
        }
        auth()->login($user);
    }
}
