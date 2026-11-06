<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Geminus\Admin\Database\Migrations\RegisterQueueJobsPermission;
use Geminus\Admin\Entities\AdminUser;
use Tests\Support\Libraries\TableLayoutAssertions;

/**
 * @internal
 */
final class QueueJobsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        Services::resetSingle('settings');
        parent::tearDown();
    }

    public function testListsAllQueuesAndFiltersWithoutExposingPrivateData(): void
    {
        $this->loginAs('superadmin');
        $emailId    = $this->insertJob('email', 0);
        $businessId = $this->insertJob('exam-voucher-email', 1, 3);
        db_connect()->table('email_delivery_logs')->insert([
            'job_id' => $businessId, 'recipient' => 'private-recipient@example.com', 'subject' => 'private-subject',
            'status' => 'failed', 'attempts' => 1, 'created_at' => '2026-01-01 00:00:00', 'failure_reason' => 'private-exception',
        ]);

        $page = $this->get('/en/admin/queue-jobs');
        $page->assertOK();
        $body = $page->response()->getBody();
        TableLayoutAssertions::assertTablesInCards($body, paginated: true);
        $rows = $this->tableRows($body);
        $this->assertCount(2, $rows);
        $this->assertSame([(string) $businessId, 'exam-voucher-email', 'Reserved', '3', '2026-01-01 00:00:00', '2026-01-01 01:00:00'], $rows[0]);
        $this->assertSame([(string) $emailId, 'email', 'Pending', '1', '2026-01-01 00:00:00', '2026-01-01 01:00:00'], $rows[1]);

        foreach (['private-payload', 'private-recipient@example.com', 'private-subject', 'private-exception', 'private-token', 'private-voucher', 'smtp-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
        $this->assertStringContainsString('private, no-store', $page->response()->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('<option value="exam-voucher-email"', $body);

        $filtered = $this->get('/en/admin/queue-jobs?queue=exam-voucher-email&status=1');
        $filtered->assertOK();
        $rows = $this->tableRows($filtered->response()->getBody());
        $this->assertCount(1, $rows);
        $this->assertSame('exam-voucher-email', $rows[0][1]);
        $this->assertSame('Reserved', $rows[0][2]);

        $pending = $this->get('/en/admin/queue-jobs?status=0');
        $pending->assertOK();
        $rows = $this->tableRows($pending->response()->getBody());
        $this->assertCount(1, $rows);
        $this->assertSame('Pending', $rows[0][2]);
        $this->assertStringContainsString('<option value="0" selected>', $pending->response()->getBody());

        $empty = $this->get('/en/admin/queue-jobs?queue=missing&status=2');
        $empty->assertOK();
        $empty->assertSee('No records found.', 'h3');
        $this->assertStringContainsString('class="empty-action"', $empty->response()->getBody());
        $this->assertSame(['0', '1'], array_column(db_connect()->table('queue_jobs')->select('status')->orderBy('id')->get()->getResultArray(), 'status'));
        $this->assertSame(['1', '3'], array_column(db_connect()->table('queue_jobs')->select('attempts')->orderBy('id')->get()->getResultArray(), 'attempts'));
    }

    public function testUsesConfiguredQueueDatabaseConnection(): void
    {
        $this->loginAs('superadmin');
        $this->insertJob('default-queue', 0);
        $settings             = config('Database')->tests;
        $settings['DBPrefix'] = 'queue_view_test_';
        $connection           = db_connect($settings);
        $connection->query('CREATE TEMPORARY TABLE queue_view_test_queue_jobs (id integer, queue varchar(64), status integer, attempts integer, created_at integer, available_at integer)');
        $connection->table('queue_jobs')->insert(['id' => 999, 'queue' => 'configured-queue', 'status' => 0, 'attempts' => 0, 'created_at' => 1767225600, 'available_at' => 1767229200]);
        $queueConfig                      = config('Queue');
        $original                         = $queueConfig->database['dbGroup'];
        $queueConfig->database['dbGroup'] = $settings;

        try {
            $page = $this->get('/en/admin/queue-jobs');
            $page->assertOK();
            $this->assertSame([['999', 'configured-queue', 'Pending', '0', '2026-01-01 00:00:00', '2026-01-01 01:00:00']], $this->tableRows($page->response()->getBody()));
        } finally {
            $queueConfig->database['dbGroup'] = $original;
            $connection->query('DROP TABLE queue_view_test_queue_jobs');
        }
    }

    public function testPaginationRetainsFiltersAndShowsNextPage(): void
    {
        $this->loginAs('superadmin');

        for ($index = 0; $index < 21; $index++) {
            $this->insertJob('exam-voucher-email', 2);
        }
        $first = $this->get('/en/admin/queue-jobs?queue=exam-voucher-email&status=2');
        $first->assertOK();
        $first->assertSee('Total: 21 (1 - 20)');
        $document = new DOMDocument();
        $document->loadHTML($first->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = (new DOMXPath($document))->query('//a[contains(@class, "page-link") and contains(@href, "page=2")]');
        $this->assertCount(1, $links);
        parse_str((string) parse_url($links->item(0)->getAttribute('href'), PHP_URL_QUERY), $parameters);
        $this->assertSame(['queue' => 'exam-voucher-email', 'status' => '2', 'page' => '2'], $parameters);
        $second = $this->get('/en/admin/queue-jobs?queue=exam-voucher-email&status=2&page=2');
        $second->assertOK();
        $second->assertSee('Total: 21 (21 - 21)');
        $this->assertCount(1, $this->tableRows($second->response()->getBody()));
    }

    public function testAccessAndLegacyRedirectRequireIndependentPermissions(): void
    {
        $this->get('/en/admin/queue-jobs')->assertRedirect();
        $this->loginAs('admin', ['email-deliveries.view']);
        $denied = config('Auth')->permissionDeniedRedirect();
        $mail   = $this->get('/en/admin/mail/deliveries');
        $mail->assertOK();
        $this->assertStringNotContainsString('view=queue', $mail->response()->getBody());
        $this->assertStringNotContainsString('href="/en/admin/queue-jobs"', $mail->response()->getBody());
        $this->get('/en/admin/queue-jobs')->assertRedirectTo($denied);
        $legacy = $this->get('/en/admin/mail/deliveries?view=queue');
        $legacy->assertRedirectTo('/en/admin/queue-jobs');
        $this->get('/en/admin/queue-jobs')->assertRedirectTo($denied);
    }

    public function testUnsupportedBackendIsExplicit(): void
    {
        $this->loginAs('superadmin');
        $config                 = config('Queue');
        $original               = $config->defaultHandler;
        $config->defaultHandler = 'redis';

        try {
            $result = $this->get('/en/admin/queue-jobs');
            $result->assertStatus(501);
            $result->assertSee('The configured queue backend does not support this view.');
            $this->assertStringNotContainsString('No records found.', $result->response()->getBody());
        } finally {
            $config->defaultHandler = $original;
        }
    }

    public function testPermissionMigrationPreservesExistingGrants(): void
    {
        $permissions                          = setting('AuthGroups.permissions');
        $matrix                               = setting('AuthGroups.matrix');
        $customPermissions                    = $permissions;
        $customPermissions['queue-jobs.view'] = 'Custom description';
        $customMatrix                         = $matrix;
        $customMatrix['superadmin']           = ['admin.*', 'legacy.view'];
        setting('AuthGroups.permissions', $customPermissions);
        setting('AuthGroups.matrix', $customMatrix);

        try {
            $migration = new RegisterQueueJobsPermission();
            $migration->up();
            $migration->up();
            $this->assertSame($customPermissions, setting('AuthGroups.permissions'));
            $customMatrix['superadmin'][] = 'queue-jobs.*';
            $this->assertSame($customMatrix, setting('AuthGroups.matrix'));
            unset($customPermissions['queue-jobs.view']);
            setting('AuthGroups.permissions', $customPermissions);
            $migration->up();
            $this->assertSame('View queue jobs', setting('AuthGroups.permissions')['queue-jobs.view']);
            $this->assertSame($customMatrix, setting('AuthGroups.matrix'));
        } finally {
            service('settings')->setMany(['AuthGroups.permissions' => $permissions, 'AuthGroups.matrix' => $matrix]);
        }
    }

    private function insertJob(string $queue, int $status, int $attempts = 1): int
    {
        db_connect()->table('queue_jobs')->insert([
            'queue'      => $queue, 'status' => $status, 'attempts' => $attempts,
            'created_at' => 1767225600, 'available_at' => 1767229200,
            'payload'    => json_encode(['to' => 'private-recipient@example.com', 'subject' => 'private-subject', 'body' => 'private-payload', 'token' => 'private-token', 'voucher' => 'private-voucher', 'error' => 'smtp-secret']),
        ]);

        return (int) db_connect()->insertID();
    }

    private function tableRows(string $body): array
    {
        $document = new DOMDocument();
        $document->loadHTML($body, LIBXML_NOERROR | LIBXML_NOWARNING);
        $rows = (new DOMXPath($document))->query('//table/tbody/tr[not(td[@colspan])]');

        return array_map(static fn (DOMNode $row): array => array_map(static fn (DOMNode $cell): string => trim($cell->textContent), iterator_to_array((new DOMXPath($document))->query('./td', $row))), iterator_to_array($rows));
    }

    private function loginAs(string $group, array $permissions = []): void
    {
        $user        = new AdminUser(['username' => 'queueviewer']);
        $user->email = 'queueviewer@example.com';
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
