<?php

use CodeIgniter\Test\CIUnitTestCase;
use Modules\ExamVouchers\Libraries\VoucherMailTemplate;

/**
 * @internal
 */
final class ExamVoucherMailTemplateTest extends CIUnitTestCase
{
    public function testTemplateRequiresCodeAndRejectsUnsafeSubjectAndUnknownVariables(): void
    {
        $template = new VoucherMailTemplate(service('settings'));
        $this->assertTrue($template->valid('Voucher', 'Hello {name}, {code}, {exam}, {region}, {expiresOn}'));
        $this->assertFalse($template->valid('Voucher', 'Hello {name}'));
        $this->assertFalse($template->valid('Voucher {code}', '{code}'));
        $this->assertFalse($template->valid("Voucher\nBcc: other@example.com", '{code}'));
        $this->assertFalse($template->valid('Voucher', '{code} {unknown}'));
    }

    public function testDefaultHtmlBodyPreservesLineBreaksInEveryLocale(): void
    {
        foreach (['en', 'zh-Hans', 'zh-Hant'] as $locale) {
            $body = lang('ExamVouchers.mailDefaultBody', [], $locale);

            $this->assertStringContainsString("\n", $body);
            $this->assertStringContainsString('{code}', $body);
        }
    }

    public function testResetRemovesSavedSubjectAndBody(): void
    {
        $template = new VoucherMailTemplate(service('settings'));
        $template->save('Custom subject', 'Custom {code}');

        $template->reset();

        $this->assertSame(lang('ExamVouchers.mailDefaultSubject'), $template->get()['subject']);
        $this->assertSame(lang('ExamVouchers.mailDefaultBody'), $template->get()['body']);
    }
}
