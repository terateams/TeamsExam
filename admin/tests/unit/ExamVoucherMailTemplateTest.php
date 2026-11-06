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
}
