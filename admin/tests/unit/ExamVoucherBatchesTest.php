<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Geminus\Admin\Entities\AdminUser;
use Modules\ExamVouchers\Models\BatchModel;

/**
 * @internal
 */
final class ExamVoucherBatchesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testVoucherMenuProvidesLocalizedLinksAndHighlightsCurrentSection(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $batch = (new BatchModel())->insert(['identifier' => 'B-MENU', 'issuer' => 'Supplier']);

        foreach ([
            'en'      => ['Exam voucher management', 'Purchase batch', 'Exam voucher list'],
            'zh-Hans' => ['考试券管理', '采购批次', '考试券列表'],
            'zh-Hant' => ['考試券管理', '採購批次', '考試券列表'],
        ] as $locale => [$menuLabel, $batchLabel, $voucherLabel]) {
            $batchUrl   = "/{$locale}/admin/exam-voucher-batches";
            $voucherUrl = "/{$locale}/admin/exam-vouchers";

            foreach ([
                "/{$locale}/admin/dashboard"  => null,
                $batchUrl                     => $batchUrl,
                "{$batchUrl}/show/{$batch}"   => $batchUrl,
                "{$batchUrl}/{$batch}/import" => $batchUrl,
                $voucherUrl                   => $voucherUrl,
            ] as $path => $activeUrl) {
                $response = $this->get($path);
                $response->assertOK();
                $document = new DOMDocument();
                @$document->loadHTML('<?xml encoding="UTF-8">' . $response->response()->getBody());
                $xpath = new DOMXPath($document);
                $group = $xpath->query("//div[@id='sidebar-menu']//li[div/a[@href='{$batchUrl}']]");
                $this->assertSame(1, $group->length, $path);
                $this->assertStringContainsString($menuLabel, $group->item(0)->textContent);
                $this->assertSame($batchLabel, trim($xpath->query("./div/a[@href='{$batchUrl}']", $group->item(0))->item(0)->textContent));
                $this->assertSame($voucherLabel, trim($xpath->query("./div/a[@href='{$voucherUrl}']", $group->item(0))->item(0)->textContent));
                $this->assertSame($activeUrl === null ? 0 : 1, $xpath->query("self::li[contains(concat(' ', normalize-space(@class), ' '), ' active ')]", $group->item(0))->length);
                $this->assertSame($activeUrl === null ? 'false' : 'true', $xpath->query('./a', $group->item(0))->item(0)->getAttribute('aria-expanded'));
                $this->assertSame($activeUrl === null ? 0 : 1, $xpath->query("./div[contains(concat(' ', normalize-space(@class), ' '), ' show ')]", $group->item(0))->length);
                $current = $xpath->query("./div/a[@aria-current='page']", $group->item(0));
                $this->assertSame($activeUrl === null ? 0 : 1, $current->length, $path);
                if ($activeUrl !== null) {
                    $this->assertSame($activeUrl, $current->item(0)->getAttribute('href'));
                    $this->assertStringContainsString(' active ', ' ' . $current->item(0)->getAttribute('class') . ' ');
                }
            }
        }
    }

    public function testVoucherMenuIsHiddenWithoutPermission(): void
    {
        $this->loginAs(['announcements.manage']);
        $response = $this->get('/en/admin/dashboard');
        $response->assertOK();
        $this->assertStringNotContainsString('Exam voucher management', $response->response()->getBody());
        $this->assertStringNotContainsString('href="/en/admin/exam-voucher-batches"', $response->response()->getBody());
        $this->assertStringNotContainsString('href="/en/admin/exam-vouchers"', $response->response()->getBody());
        $this->assertStringNotContainsString('href="/en/admin/exam-voucher-mail/template"', $response->response()->getBody());
    }

    public function testVoucherTemplateHasLocalizedStandaloneMenuAndActiveState(): void
    {
        $this->loginAs(['exam-vouchers.manage']);

        foreach (['en' => 'Voucher email template', 'zh-Hans' => '考试券邮件模板', 'zh-Hant' => '考試券郵件範本'] as $locale => $label) {
            $url      = "/{$locale}/admin/exam-voucher-mail/template";
            $response = $this->get($url);
            $response->assertStatus(200);
            $document = new DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $response->response()->getBody());
            $xpath = new DOMXPath($document);
            $links = $xpath->query("//div[@id='sidebar-menu']//li/a[@href='{$url}']");
            $this->assertSame(1, $links->length);
            $this->assertSame($label, trim($links->item(0)->textContent));
            $this->assertSame('page', $links->item(0)->getAttribute('aria-current'));
            $this->assertStringContainsString(' active ', ' ' . $links->item(0)->getAttribute('class') . ' ');
        }
    }

    public function testManagerCreatesViewsUpdatesAndDeletesBatch(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $this->post('/en/admin/exam-voucher-batches/create', [
            csrf_token() => csrf_hash(), 'identifier' => ' B-001 ', 'issuer' => ' Supplier A ',
        ])->assertRedirect();
        $model = new BatchModel();
        $batch = $model->where('identifier', 'B-001')->first();
        $this->assertNotNull($batch);
        $this->assertSame('Supplier A', $batch['issuer']);
        $this->get('/en/admin/exam-voucher-batches')->assertSee('B-001');
        $this->get('/en/admin/exam-voucher-batches/show/' . $batch['id'])->assertSee('Supplier A');
        $this->get('/en/admin/exam-voucher-batches/edit/' . $batch['id'])->assertOK();
        $this->post('/en/admin/exam-voucher-batches/update/' . $batch['id'], [
            csrf_token() => csrf_hash(), 'identifier' => 'B-002', 'issuer' => 'Supplier B',
        ])->assertRedirect();
        $this->assertSame('Supplier B', $model->find($batch['id'])['issuer']);
        $this->assertSame('B-002', $model->find($batch['id'])['identifier']);
        $this->post('/en/admin/exam-voucher-batches/update/' . $batch['id'], [
            csrf_token() => csrf_hash(), 'identifier' => 'B-002', 'issuer' => 'Supplier C',
        ])->assertRedirect();
        $this->assertSame('Supplier C', $model->find($batch['id'])['issuer']);
        $this->assertSame('B-002', $model->find($batch['id'])['identifier']);
        $this->post('/en/admin/exam-voucher-batches/delete/' . $batch['id'], [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertNull($model->find($batch['id']));
    }

    public function testBatchAuditRecordsConfiguredSubmissionsAndResults(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $this->post('/en/admin/exam-voucher-batches/create', [
            csrf_token()   => csrf_hash(), 'identifier' => ' B-AUDIT ', 'issuer' => ' Supplier ',
            'purchased_on' => '2026-10-01', 'private_field' => 'do-not-record',
        ])->assertRedirect();
        $batch = (new BatchModel())->where('identifier', 'B-AUDIT')->first();
        $this->assertNotNull($batch);
        $this->post('/en/admin/exam-voucher-batches/update/' . $batch['id'], [
            csrf_token()   => csrf_hash(), 'identifier' => 'B-AUDIT', 'issuer' => 'Updated supplier',
            'purchased_on' => '2026-10-02', 'private_field' => 'do-not-record',
        ])->assertRedirect();
        $this->post('/en/admin/exam-voucher-batches/update/' . $batch['id'], [
            csrf_token()    => csrf_hash(), 'identifier' => '', 'issuer' => 'Invalid supplier',
            'private_field' => 'do-not-record',
        ])->assertRedirect();

        $logs = db_connect()->table('operation_audit_logs')->orderBy('id')->get()->getResultArray();
        $this->assertCount(3, $logs);
        $this->assertSame(['exam-voucher-batch.create', 'exam-voucher-batch.update', 'exam-voucher-batch.update'], array_column($logs, 'operation'));
        $this->assertSame(['exam-voucher-batch', 'exam-voucher-batch', 'exam-voucher-batch'], array_column($logs, 'target_type'));
        $this->assertSame([null, (string) $batch['id'], (string) $batch['id']], array_column($logs, 'target_id'));
        $this->assertSame(['success', 'success', 'failed'], array_column($logs, 'result'));

        foreach ([
            ['identifier' => ' B-AUDIT ', 'issuer' => ' Supplier ', 'purchased_on' => '2026-10-01'],
            ['identifier' => 'B-AUDIT', 'issuer' => 'Updated supplier', 'purchased_on' => '2026-10-02'],
            ['identifier' => '', 'issuer' => 'Invalid supplier'],
        ] as $index => $expected) {
            $fields = json_decode($logs[$index]['submission'], true)['fields'];
            $this->assertCount(count($expected), $fields);

            foreach ($expected as $field => $value) {
                $this->assertSame($value, $fields[$field]);
            }
        }
        $this->assertStringNotContainsString('do-not-record', json_encode($logs));
        $this->assertSame('Updated supplier', (new BatchModel())->find($batch['id'])['issuer']);
    }

    public function testNewBatchSuggestsAvailableEditableIdentifier(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $model = new BatchModel();
        $this->get('/en/admin/exam-voucher-batches/new')->assertSee('value="BATCH-000001"');
        $id = $model->insert(['identifier' => 'BATCH-000001', 'issuer' => 'Supplier']);
        $model->insert(['identifier' => 'BATCH-000003', 'issuer' => 'Supplier']);
        $model->insert(['identifier' => 'CUSTOM', 'issuer' => 'Supplier']);
        $model->insert(['identifier' => 'BATCH-ABC', 'issuer' => 'Supplier']);
        $model->insert(['identifier' => 'BATCH-123', 'issuer' => 'Supplier']);
        $model->insert(['identifier' => 'BATCH-00000012', 'issuer' => 'Supplier']);

        $response = $this->get('/en/admin/exam-voucher-batches/new');
        $response->assertSee('value="BATCH-000004"');
        $response->assertSee('New batch');
        $this->get('/en/admin/exam-voucher-batches/edit/' . $id)->assertSee('value="BATCH-000001"');
        $model->delete($id);
        $this->get('/en/admin/exam-voucher-batches/new')->assertSee('value="BATCH-000004"');

        $this->post('/en/admin/exam-voucher-batches/create', [
            csrf_token() => csrf_hash(), 'identifier' => 'MY-BATCH', 'issuer' => 'Supplier',
        ])->assertRedirect();
        $this->assertNotNull($model->where('identifier', 'MY-BATCH')->first());
    }

    public function testSuggestedIdentifierContinuesBeyondSixDigits(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $model = new BatchModel();
        $model->insert(['identifier' => 'BATCH-999999', 'issuer' => 'Supplier']);
        $this->get('/en/admin/exam-voucher-batches/new')->assertSee('value="BATCH-1000000"');
        $id = $model->insert(['identifier' => 'BATCH-1000000', 'issuer' => 'Supplier']);
        $this->get('/en/admin/exam-voucher-batches/new')->assertSee('value="BATCH-1000001"');
        $model->delete($id);
        $this->get('/en/admin/exam-voucher-batches/new')->assertSee('value="BATCH-1000000"');
    }

    public function testSuggestedIdentifierRejectsExceedingFieldLength(): void
    {
        $model = new BatchModel();
        $model->insert(['identifier' => 'BATCH-' . str_repeat('9', 143), 'issuer' => 'Supplier']);
        $this->assertSame('BATCH-1' . str_repeat('0', 143), $model->suggestIdentifier());

        $model->insert(['identifier' => 'BATCH-' . str_repeat('9', 144), 'issuer' => 'Supplier']);
        $this->expectException(OverflowException::class);
        $model->suggestIdentifier();
    }

    public function testDuplicateSuggestedIdentifierPreservesInputAndRejectsSave(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $model = new BatchModel();
        $this->get('/en/admin/exam-voucher-batches/new')->assertSee('value="BATCH-000001"');
        $model->insert(['identifier' => 'BATCH-000001', 'issuer' => 'Other supplier']);

        $this->post('/en/admin/exam-voucher-batches/create', [
            csrf_token() => csrf_hash(), 'identifier' => 'BATCH-000001', 'issuer' => 'Supplier',
        ])->assertRedirectTo('/en/admin/exam-voucher-batches/new');
        $this->assertArrayHasKey('identifier', session('errors'));
        $this->assertSame('BATCH-000001', session('_ci_old_input')['post']['identifier']);
        $this->assertSame(1, $model->countAllResults());

        $response = $this->withSession($_SESSION)->get('/en/admin/exam-voucher-batches/new');
        $response->assertSee('value="BATCH-000001"');
        $response->assertSee('is-invalid');
    }

    public function testEmptyIdentifierIsPreservedAfterValidationFails(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $this->post('/en/admin/exam-voucher-batches/create', [
            csrf_token() => csrf_hash(), 'identifier' => '', 'issuer' => 'Supplier',
        ])->assertRedirectTo('/en/admin/exam-voucher-batches/new');
        $this->assertArrayHasKey('identifier', session('errors'));
        $this->assertSame('', session('_ci_old_input')['post']['identifier']);

        $response = $this->withSession($_SESSION)->get('/en/admin/exam-voucher-batches/new');
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->response()->getBody());
        $xpath = new DOMXPath($document);
        $this->assertSame('', $xpath->query('//input[@name="identifier"]')->item(0)->getAttribute('value'));
    }

    public function testInvalidAndDuplicateIdentifiersDoNotChangeData(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $model = new BatchModel();
        $id    = $model->insert(['identifier' => 'B-001', 'issuer' => 'Supplier A']);
        $this->post('/en/admin/exam-voucher-batches/create', [csrf_token() => csrf_hash(), 'identifier' => 'B-001', 'issuer' => 'Supplier B'])->assertRedirect();
        $this->post('/en/admin/exam-voucher-batches/create', [csrf_token() => csrf_hash(), 'identifier' => ' ', 'issuer' => 'Supplier B'])->assertRedirect();
        $other = $model->insert(['identifier' => 'B-002', 'issuer' => 'Supplier B']);
        $this->post('/en/admin/exam-voucher-batches/update/' . $other, [csrf_token() => csrf_hash(), 'identifier' => 'B-001', 'issuer' => 'Changed'])->assertRedirect();
        $this->assertSame(2, $model->countAllResults());
        $this->assertSame('Supplier B', $model->find($other)['issuer']);
        $this->assertSame('Supplier A', $model->find($id)['issuer']);
    }

    public function testPurchaseDateCanBeSavedUpdatedClearedAndValidated(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $model = new BatchModel();
        $this->post('/en/admin/exam-voucher-batches/create', [
            csrf_token() => csrf_hash(), 'identifier' => 'B-DATE', 'issuer' => 'Supplier', 'purchased_on' => '2025-02-28',
        ])->assertRedirect();
        $batch = $model->where('identifier', 'B-DATE')->first();
        $this->assertSame('2025-02-28', $batch['purchased_on']);
        $this->get('/en/admin/exam-voucher-batches')->assertSee('2025-02-28');
        $this->get('/en/admin/exam-voucher-batches/show/' . $batch['id'])->assertSee('2025-02-28');
        $this->get('/en/admin/exam-voucher-batches/edit/' . $batch['id'])->assertSee('value="2025-02-28"');

        foreach (['2025-02-30', '2025-2-01', '0000-01-01'] as $invalid) {
            $this->post('/en/admin/exam-voucher-batches/update/' . $batch['id'], [
                csrf_token() => csrf_hash(), 'identifier' => 'B-DATE', 'issuer' => 'Supplier', 'purchased_on' => $invalid,
            ])->assertRedirectTo('/en/admin/exam-voucher-batches/edit/' . $batch['id']);
            $this->assertArrayHasKey('purchased_on', session('errors'));
            $this->assertSame($invalid, session('_ci_old_input')['post']['purchased_on']);
            $this->assertSame('2025-02-28', $model->find($batch['id'])['purchased_on']);
        }
        $this->post('/en/admin/exam-voucher-batches/update/' . $batch['id'], [
            csrf_token() => csrf_hash(), 'identifier' => 'B-DATE', 'issuer' => 'Supplier', 'purchased_on' => '2024-01-10',
        ])->assertRedirect();
        $this->assertSame('2024-01-10', $model->find($batch['id'])['purchased_on']);
        $this->post('/en/admin/exam-voucher-batches/update/' . $batch['id'], [
            csrf_token() => csrf_hash(), 'identifier' => 'B-DATE', 'issuer' => 'Supplier', 'purchased_on' => '',
        ])->assertRedirect();
        $this->assertNull($model->find($batch['id'])['purchased_on']);
    }

    public function testBatchHeadersSortAndPreserveSearchAndPagination(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $model = new BatchModel();
        $model->insert(['identifier' => 'SORT-C', 'issuer' => 'Supplier A', 'purchased_on' => '2024-03-01']);
        $model->insert(['identifier' => 'SORT-A', 'issuer' => 'Supplier C', 'purchased_on' => '2024-01-01']);
        $model->insert(['identifier' => 'SORT-B', 'issuer' => 'Supplier B', 'purchased_on' => '2024-02-01']);
        $model->insert(['identifier' => 'OLD', 'issuer' => 'Other']);

        foreach (['identifier' => ['SORT-A', 'SORT-B', 'SORT-C'], 'issuer' => ['SORT-C', 'SORT-B', 'SORT-A'], 'purchased_on' => ['SORT-A', 'SORT-B', 'SORT-C']] as $field => $expected) {
            foreach (['ASC', 'DESC'] as $direction) {
                $response = $this->get('/en/admin/exam-voucher-batches?q=SORT&sort=' . $field . '&direction=' . $direction);
                $response->assertOK();
                $document = new DOMDocument();
                @$document->loadHTML('<?xml encoding="UTF-8">' . $response->response()->getBody());
                $xpath  = new DOMXPath($document);
                $rows   = $xpath->query('//table/tbody/tr/td[1]/a');
                $actual = [];

                foreach ($rows as $row) {
                    $actual[] = trim($row->textContent);
                }
                $this->assertSame($direction === 'ASC' ? $expected : array_reverse($expected), $actual);
                $header = $xpath->query('//th[form/button[@name="sort" and @value="' . $field . '"]]')->item(0);
                $this->assertSame($direction === 'ASC' ? 'ascending' : 'descending', $header->getAttribute('aria-sort'));
                $this->assertSame('SORT', $xpath->query('.//input[@name="q"]', $header)->item(0)->getAttribute('value'));
                $this->assertSame($direction === 'ASC' ? 'DESC' : 'ASC', $xpath->query('.//input[@name="direction"]', $header)->item(0)->getAttribute('value'));
            }
        }
        $response = $this->get('/en/admin/exam-voucher-batches?q=OLD');
        $response->assertSee('OLD');
        $response->assertSee('<td class="text-nowrap">-</td>');

        for ($number = 0; $number < 21; $number++) {
            $model->insert(['identifier' => 'PAGE-' . $number, 'issuer' => 'Supplier']);
        }
        $response = $this->get('/en/admin/exam-voucher-batches?q=PAGE&sort=identifier&direction=ASC');
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->response()->getBody());
        $xpath = new DOMXPath($document);
        $link  = $xpath->query('//ul[contains(@class, "pagination")]//a[normalize-space(.)="2"]')->item(0);
        $this->assertNotNull($link);
        parse_str((string) parse_url($link->getAttribute('href'), PHP_URL_QUERY), $parameters);
        $this->assertSame(['q' => 'PAGE', 'sort' => 'identifier', 'direction' => 'ASC', 'page' => '2'], $parameters);
    }

    public function testBatchListShowsVoucherCountsAcrossLocalesSearchAndPages(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $model = new BatchModel();
        $ids   = [];

        for ($number = 0; $number < 23; $number++) {
            $identifier       = sprintf('COUNT-%02d', $number);
            $ids[$identifier] = $model->insert(['identifier' => $identifier, 'issuer' => 'Supplier']);
        }

        $vouchers = db_connect()->table('exam_vouchers');

        foreach ([
            ['COUNT-01', null, null, '2020-01-01'],
            ['COUNT-01', auth()->id(), null, '2030-01-01'],
            ['COUNT-01', null, 'Verified', '2030-01-01'],
            ['COUNT-21', auth()->id(), 'Verified', '2030-01-01'],
        ] as $index => [$identifier, $recipient, $verification, $expiry]) {
            $vouchers->insert([
                'batch_id'     => $ids[$identifier], 'code_ciphertext' => 'test-ciphertext',
                'code_digest'  => hash('sha256', 'batch-count-' . $index), 'expires_on' => $expiry,
                'recipient_id' => $recipient, 'verification_note' => $verification,
            ]);
        }

        foreach (['en' => 'Voucher count', 'zh-Hans' => '考试券数量', 'zh-Hant' => '考試券數量'] as $locale => $label) {
            $response = $this->get("/{$locale}/admin/exam-voucher-batches?q=COUNT-0&sort=identifier&direction=ASC");
            $response->assertOK();
            $document = new DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $response->response()->getBody());
            $xpath = new DOMXPath($document);
            $this->assertSame(1, $xpath->query('//table/thead/tr/th[normalize-space(.)="' . $label . '"]')->length);
            $this->assertSame(10, $xpath->query('//table/tbody/tr')->length);
            $this->assertSame('0', trim($xpath->query('//table/tbody/tr[td/a[normalize-space(.)="COUNT-00"]]/td[4]')->item(0)->textContent));
            $this->assertSame('3', trim($xpath->query('//table/tbody/tr[td/a[normalize-space(.)="COUNT-01"]]/td[4]')->item(0)->textContent));
        }

        Services::resetSingle('pager');
        $response = $this->get('/en/admin/exam-voucher-batches?q=COUNT-&sort=identifier&direction=ASC&page=2');
        $response->assertOK();
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->response()->getBody());
        $xpath = new DOMXPath($document);
        $this->assertSame(3, $xpath->query('//table/tbody/tr')->length);
        $this->assertSame('0', trim($xpath->query('//table/tbody/tr[td/a[normalize-space(.)="COUNT-20"]]/td[4]')->item(0)->textContent));
        $this->assertSame('1', trim($xpath->query('//table/tbody/tr[td/a[normalize-space(.)="COUNT-21"]]/td[4]')->item(0)->textContent));
        $this->assertSame('0', trim($xpath->query('//table/tbody/tr[td/a[normalize-space(.)="COUNT-22"]]/td[4]')->item(0)->textContent));

        Services::resetSingle('pager');
        $response = $this->get('/en/admin/exam-voucher-batches?q=NO-SUCH-BATCH');
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->response()->getBody());
        $this->assertSame(1, (new DOMXPath($document))->query('//table/tbody/tr/td[@colspan="5"]')->length);
    }

    public function testPurchaseDateLabelsAndInvalidInputAreDisplayed(): void
    {
        $this->loginAs(['exam-vouchers.manage']);
        $id = (new BatchModel())->insert(['identifier' => 'B-LABEL', 'issuer' => 'Supplier', 'purchased_on' => '2024-01-01']);

        foreach (['en' => 'Purchase date', 'zh-Hans' => '采购日期', 'zh-Hant' => '採購日期'] as $locale => $label) {
            foreach (["/{$locale}/admin/exam-voucher-batches", "/{$locale}/admin/exam-voucher-batches/show/{$id}", "/{$locale}/admin/exam-voucher-batches/edit/{$id}"] as $path) {
                $response = $this->get($path);
                $response->assertOK();
                $response->assertSee($label);
                $response->assertDontSee('ExamVouchers.purchasedOn');
            }
        }

        $response = $this->withSession([
            '_ci_old_input' => ['post' => ['purchased_on' => '2024-02-30']],
            'errors'        => ['purchased_on' => 'Invalid purchase date'],
        ])->get('/en/admin/exam-voucher-batches/edit/' . $id);
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->response()->getBody());
        $xpath = new DOMXPath($document);
        $this->assertSame('2024-02-30', $xpath->query('//input[@name="purchased_on"]')->item(0)->getAttribute('value'));
        $this->assertSame('Invalid purchase date', trim($xpath->query('//*[@id="batch-purchased-on-error"]')->item(0)->textContent));
        $this->assertStringContainsString('new tabler.Datepicker', $response->response()->getBody());
    }

    public function testUnauthorizedRequestsAndGetWritesCannotChangeData(): void
    {
        $model = new BatchModel();
        $id    = $model->insert(['identifier' => 'B-001', 'issuer' => 'Supplier A']);
        $this->get('/en/admin/exam-voucher-batches')->assertRedirect();
        $this->loginAs(['announcements.manage']);
        $this->get('/en/admin/exam-voucher-batches')->assertRedirect();
        $this->post('/en/admin/exam-voucher-batches/create', [csrf_token() => csrf_hash(), 'identifier' => 'B-002', 'issuer' => 'Supplier B'])->assertRedirect();
        $this->post('/en/admin/exam-voucher-batches/update/' . $id, [csrf_token() => csrf_hash(), 'identifier' => 'Changed', 'issuer' => 'Changed'])->assertRedirect();
        $this->post('/en/admin/exam-voucher-batches/delete/' . $id, [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(1, $model->countAllResults());
        $this->assertSame('B-001', $model->find($id)['identifier']);
        $this->assertSame('Supplier A', $model->find($id)['issuer']);
        auth()->user()->addPermission('exam-vouchers.manage');
        $this->get('/en/admin/exam-voucher-batches/delete/' . $id)->assertStatus(404);
        $this->assertNotNull($model->find($id));
    }

    public function testCreateRequiresCsrf(): void
    {
        $this->loginAs(['exam-vouchers.manage']);

        try {
            $this->post('/en/admin/exam-voucher-batches/create', ['identifier' => 'B-001', 'issuer' => 'Supplier']);
            $this->fail('Missing CSRF token must be rejected.');
        } catch (SecurityException $exception) {
            $this->assertSame(0, (new BatchModel())->countAllResults());
        }
    }

    private function loginAs(array $permissions): void
    {
        $user        = new AdminUser(['username' => 'batchmanager']);
        $user->email = 'batchmanager@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('admin');
        $user->addPermission(...$permissions);
        auth()->login($user);
    }
}
