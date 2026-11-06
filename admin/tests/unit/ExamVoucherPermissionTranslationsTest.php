<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ExamVoucherPermissionTranslationsTest extends CIUnitTestCase
{
    public function testDomainLabelsMergeWithBaseTranslations(): void
    {
        foreach ([
            'en'      => ['Exam vouchers', 'Queue jobs'],
            'zh-Hans' => ['考试券管理', '队列任务'],
            'zh-Hant' => ['考試券管理', '佇列工作'],
        ] as $locale => [$vouchers, $queue]) {
            $labels = lang('Admin.permissionDomainLabels', [], $locale);

            $this->assertSame($vouchers, $labels['exam-vouchers']);
            $this->assertSame($queue, $labels['queue-jobs']);
        }
    }
}
