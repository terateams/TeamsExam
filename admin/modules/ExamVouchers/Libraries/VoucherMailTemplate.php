<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Libraries;

use CodeIgniter\Settings\Settings;

final class VoucherMailTemplate
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function get(): array
    {
        return [
            'subject' => $this->settings->get('VoucherMail.subject') ?? lang('ExamVouchers.mailDefaultSubject'),
            'body'    => $this->settings->get('VoucherMail.body') ?? lang('ExamVouchers.mailDefaultBody'),
        ];
    }

    public function valid(string $subject, string $body): bool
    {
        preg_match_all('/\{([a-zA-Z][a-zA-Z0-9]*)\}/', $body, $matches);

        return trim($subject) !== '' && mb_strlen($subject) <= 255 && ! preg_match('/[\r\n{}]/', $subject)
            && trim($body) !== '' && mb_strlen($body) <= 10000 && str_contains($body, '{code}')
            && array_diff($matches[1], ['name', 'code', 'exam', 'region', 'expiresOn']) === [];
    }

    public function save(string $subject, string $body): void
    {
        $this->settings->setMany(['VoucherMail.subject' => $subject, 'VoucherMail.body' => $body]);
    }
}
