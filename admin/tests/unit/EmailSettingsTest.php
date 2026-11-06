<?php

use CodeIgniter\Language\Language;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\MailTemplates;
use Geminus\Admin\Libraries\QueuedEmail;
use Tests\Support\Libraries\TableLayoutAssertions;

/**
 * @internal
 */
final class EmailSettingsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        foreach (MailTemplates::TYPES as $type => $variables) {
            foreach (config('App')->supportedLocales as $locale) {
                service('settings')->forget(MailTemplates::settingKey($type, $locale, 'subject'));
                service('settings')->forget(MailTemplates::settingKey($type, $locale, 'body'));
            }
        }
        Services::resetSingle('email');
        auth()->logout();
        parent::tearDown();
    }

    public function testAnonymousUserCannotOpenEmailSettings(): void
    {
        $this->get('/en/admin/settings/email')->assertRedirect();
        $this->get('/en/admin/mail/deliveries')->assertRedirect();
        $this->get('/en/admin/mail/templates')->assertRedirect();
        $this->post('/en/admin/mail/templates/invitation/en', [csrf_token() => csrf_hash(), 'subject' => 'Hi', 'body' => '{link}'])->assertRedirect();
        $this->post('/en/admin/mail/templates/invitation/en/reset', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->post('/en/admin/settings/email', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->post('/en/admin/settings/email/test', [csrf_token() => csrf_hash(), 'test_email' => 'recipient@example.com'])->assertRedirect();
    }

    public function testUserWithoutSettingsPermissionCannotReadOrSave(): void
    {
        $this->loginAs('admin');
        service('mailTemplates')->save('invitation', 'en', 'Protected invitation', 'Protected {link}');
        $denied   = config('Auth')->permissionDeniedRedirect();
        $template = service('mailTemplates')->get('invitation', 'en');

        $this->get('/en/admin/settings/email')->assertRedirectTo($denied);
        $this->get('/en/admin/mail/deliveries')->assertRedirectTo($denied);
        $this->get('/en/admin/mail/templates')->assertRedirectTo($denied);
        $this->post('/en/admin/mail/templates/invitation/en', [csrf_token() => csrf_hash(), 'subject' => 'Hi', 'body' => '{link}'])->assertRedirectTo($denied);
        $this->post('/en/admin/mail/templates/invitation/en/reset', [csrf_token() => csrf_hash()])->assertRedirectTo($denied);
        $this->post('/en/admin/settings/email', $this->validSettings())->assertRedirectTo($denied);
        $this->post('/en/admin/settings/email/test', [csrf_token() => csrf_hash(), 'test_email' => 'recipient@example.com'])->assertRedirectTo($denied);
        $this->assertSame('', service('settings')->get('Email.fromEmail'));
        $this->assertSame($template, service('mailTemplates')->get('invitation', 'en'));
    }

    public function testEmailSettingsPostRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);

        $this->post('/en/admin/settings/email', ['fromEmail' => 'sender@example.com']);
    }

    public function testMailNavigationAndTemplateOverview(): void
    {
        $this->loginAs('superadmin');

        $templates = $this->get('/en/admin/mail/templates');
        $templates->assertOK();
        $templates->assertSee('Email templates');
        $templates->assertSee('User invitation');
        $templates->assertSee('Account invitation');
        $this->assertStringContainsString('action="/en/admin/mail/templates/invitation/en"', $templates->response()->getBody());
        $this->assertStringContainsString('id="template-preview"', $templates->response()->getBody());
        $this->assertStringContainsString('link: ' . json_encode(url_to('magic-link', 'en'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), $templates->response()->getBody());
        $this->assertStringNotContainsString('example.invalid', $templates->response()->getBody());
        $this->get('/en/admin/mail/templates?type=invitation&locale=zh-Hans')->assertSee('账户邀请');
        $this->get('/en/admin/mail/templates?type=invitation&locale=zh-Hant')->assertSee('帳戶邀請');
        $this->get('/en/admin/mail/templates?type=magic-link')->assertSee('Sign-in link');
        $traditional = $this->get('/en/admin/mail/templates?type=magic-link&locale=zh-Hant');
        $this->assertStringContainsString('link: ' . json_encode(url_to('verify-magic-link', 'zh-Hant') . '?token=preview', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), $traditional->response()->getBody());
        $this->assertStringContainsString("&lt;/table&gt;\n&lt;table", $traditional->response()->getBody());
        $this->get('/en/admin/mail/templates?type=activation')->assertSee('Account activation');
        $this->get('/en/admin/mail/templates?type=email-2fa')->assertSee('Email verification code');
        $this->assertStringContainsString('href="/en/admin/mail/deliveries"', $templates->response()->getBody());
        $this->assertStringContainsString('href="/en/admin/mail/templates" aria-current="page"', $templates->response()->getBody());
        $this->assertStringContainsString('private, no-store', $templates->response()->getHeaderLine('Cache-Control'));

        $deliveries = $this->get('/zh-Hans/admin/mail/deliveries');
        $deliveries->assertOK();
        $deliveries->assertSee('发送记录');
        $this->assertStringContainsString('href="/zh-Hans/admin/mail/deliveries" aria-current="page"', $deliveries->response()->getBody());
        $this->assertStringContainsString('href="/zh-Hans/admin/mail/templates"', $deliveries->response()->getBody());

        $this->get('/en/admin/settings/email/queue')->assertStatus(404);
    }

    public function testEveryTemplateEscapesHtmlValuesAndProvidesSandboxedPreview(): void
    {
        $this->loginAs('superadmin');
        $templates = service('mailTemplates');

        foreach (['invitation' => 'link', 'magic-link' => 'link', 'activation' => 'code', 'email-2fa' => 'code'] as $type => $required) {
            $templates->save($type, 'en', 'Hello {username}', '<p>{username}</p><div>{' . $required . '}</div>');
            $rendered = $templates->render($type, 'en', ['username' => '<user> & "guest"', $required => '<token> & "value"']);
            $this->assertSame('Hello <user> & "guest"', $rendered['subject']);
            $this->assertSame('<p>&lt;user&gt; &amp; &quot;guest&quot;</p><div>&lt;token&gt; &amp; &quot;value&quot;</div>', $rendered['body']);

            $page = $this->get('/en/admin/mail/templates?type=' . $type);
            $page->assertOK();
            $page->assertSee('HTML message', 'label');
            $body = $page->response()->getBody();
            $this->assertStringContainsString('id="template-preview"', $body);
            $this->assertStringContainsString('sandbox=""', $body);
            $this->assertStringContainsString('referrerpolicy="no-referrer"', $body);
            $this->assertStringContainsString("bodyInput.addEventListener('input', updatePreview)", $body);
            $this->assertStringContainsString("default-src \\'none\\'", $body);
        }
    }

    public function testTemplatesSaveValidateAndRestorePerLocale(): void
    {
        $this->loginAs('superadmin');
        $url = '/en/admin/mail/templates/invitation/zh-Hans';
        $this->post($url, [csrf_token() => csrf_hash(), 'subject' => 'Hi {username}', 'body' => 'Go {link}'])->assertRedirect();
        $template = service('mailTemplates');
        $this->assertSame(['subject' => 'Hi {username}', 'body' => 'Go {link}'], $template->get('invitation', 'zh-Hans'));
        $this->assertSame('帳戶邀請', $template->get('invitation', 'zh-Hant')['subject']);
        $this->assertSame('Go https://example.com', $template->render('invitation', 'zh-Hans', ['link' => 'https://example.com'])['body']);

        $this->post($url, [csrf_token() => csrf_hash(), 'subject' => 'Bad', 'body' => '{unknown}'])->assertRedirect();
        $this->assertSame('Go {link}', $template->get('invitation', 'zh-Hans')['body']);
        $this->post($url, [csrf_token() => csrf_hash(), 'subject' => 'Bad', 'body' => 'No link'])->assertRedirect();
        $this->assertSame('Go {link}', $template->get('invitation', 'zh-Hans')['body']);
        $this->post($url, [csrf_token() => csrf_hash(), 'subject' => 'Secret {link}', 'body' => 'Go {link}'])->assertRedirect();
        $this->assertSame('Hi {username}', $template->get('invitation', 'zh-Hans')['subject']);
        $this->post($url, [csrf_token() => csrf_hash(), 'subject' => "Hi\r\nBcc: other@example.com", 'body' => 'Go {link}'])->assertRedirect();
        $this->assertSame('Hi {username}', $template->get('invitation', 'zh-Hans')['subject']);
        $this->post('/en/admin/mail/templates/email-2fa/en', [csrf_token() => csrf_hash(), 'subject' => 'Code {code}', 'body' => 'Enter {code}'])->assertRedirect();
        $this->assertSame(lang('Auth.email2FASubject'), $template->get('email-2fa', 'en')['subject']);
        $this->post('/en/admin/mail/templates/magic-link/en', [csrf_token() => csrf_hash(), 'subject' => 'From {userAgent}', 'body' => 'Open {link}'])->assertRedirect();
        $this->assertSame(lang('Auth.magicLinkSubject'), $template->get('magic-link', 'en')['subject']);
        $this->post('/en/admin/mail/templates/unknown/en', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->post('/en/admin/mail/templates/invitation/zh-Hans/reset', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame('账户邀请', $template->get('invitation', 'zh-Hans')['subject']);
    }

    public function testTemplateUpdateRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);
        $this->post('/en/admin/mail/templates/invitation/en', ['subject' => 'Hi', 'body' => '{link}']);
    }

    public function testInvitationRendersHtmlWithOptionalMicrosoftInstructions(): void
    {
        $this->loginAs('superadmin');
        $templates = service('mailTemplates');
        $settings  = service('settings');
        $previous  = $settings->get('MicrosoftOAuth.enabled');
        $settings->set(MailTemplates::settingKey('invitation', 'en', 'body'), '<p>{username}</p><a href="{link}">Open</a>{microsoftLogin}');

        try {
            $settings->set('MicrosoftOAuth.enabled', false);
            $rendered = $templates->render('invitation', 'en', ['username' => '<user>', 'link' => 'https://example.invalid/?x=" onclick="evil']);
            $this->assertStringContainsString('<p>&lt;user&gt;</p>', $rendered['body']);
            $this->assertStringContainsString('&quot; onclick=&quot;', $rendered['body']);
            $this->assertStringNotContainsString('Microsoft', $rendered['body']);
            $this->assertStringContainsString('<meta name="viewport"', view('Geminus\Admin\Views\auth\email\html', $rendered));
            $this->assertStringContainsString('microsoftLogin: ""', $this->get('/en/admin/mail/templates?type=invitation&locale=zh-Hant')->response()->getBody());

            $settings->forget(MailTemplates::settingKey('invitation', 'en', 'body'));

            foreach (config('App')->supportedLocales as $locale) {
                $default = $templates->get('invitation', $locale);
                $this->assertStringNotContainsString('GeminusAdmin', $default['subject'] . $default['body']);
                $this->assertStringContainsString('<a href="{link}"', $default['body']);
                $this->assertStringContainsString('{microsoftLogin}', $default['body']);
                $this->assertStringContainsString("\n", $default['body']);
            }

            $settings->set('MicrosoftOAuth.enabled', true);

            foreach (['en' => 'sign in with Microsoft', 'zh-Hans' => '通过微软登录', 'zh-Hant' => '透過微軟登入'] as $locale => $label) {
                $microsoftLoginHtml = view('Geminus\Admin\Views\auth\email\microsoft_login', ['enabled' => true, 'locale' => $locale], ['debug' => false]);
                $body               = $templates->render('invitation', $locale, ['link' => 'https://example.invalid'], $microsoftLoginHtml)['body'];
                $this->assertStringContainsString('<a href="' . site_url($locale . '/microsoft/start') . '">' . $label . '</a>', $body);
                $this->assertStringNotContainsString('<a href="/' . $locale . '/microsoft/start">', $body);
                $this->assertStringNotContainsString('{microsoftLink}', $body);
            }
            $preview = $this->get('/en/admin/mail/templates?type=invitation&locale=zh-Hant')->response()->getBody();
            $this->assertStringContainsString('microsoftLogin: ' . json_encode($microsoftLoginHtml, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), $preview);
        } finally {
            $settings->set('MicrosoftOAuth.enabled', $previous);
        }
    }

    public function testShieldMailViewsUseStoredSubjectAndEscapeDynamicValues(): void
    {
        $this->loginAs('superadmin');
        $email = $this->createMock(QueuedEmail::class);
        $email->expects($this->once())->method('setSubject')->with('Custom link');
        Services::injectMock('email', $email);
        service('settings')->set(MailTemplates::settingKey('magic-link', 'en', 'subject'), 'Custom link');
        service('settings')->set(MailTemplates::settingKey('magic-link', 'en', 'body'), '<p>Hello {username}</p><a href="{link}">Sign in</a>');

        $body = view(config('Auth')->views['magic-link-email'], [
            'user'  => (object) ['username' => '<script>alert(1)</script>'],
            'token' => 'a&b', 'ipAddress' => '127.0.0.1', 'userAgent' => 'Browser', 'date' => 'Today',
        ]);
        $this->assertStringContainsString('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"', $body);
        $this->assertStringContainsString('<meta name="x-apple-disable-message-reformatting">', $body);
        $this->assertStringContainsString('<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">', $body);
        $this->assertStringContainsString('Custom link', $body);
        $this->assertStringContainsString('<p>Hello &lt;script&gt;', $body);
        $this->assertStringContainsString('<a href="', $body);
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
        $this->assertStringContainsString('a%26b', $body);
        $this->assertStringNotContainsString('token=a&b', $body);
        service('settings')->set(MailTemplates::settingKey('magic-link', 'en', 'subject'), 'Hello {username}');
        $rendered = service('mailTemplates')->render('magic-link', 'en', ['username' => '<user>', 'link' => 'https://example.invalid/login']);
        $this->assertSame('Hello <user>', $rendered['subject']);
        $this->assertStringContainsString('&lt;user&gt;', $rendered['body']);
    }

    public function testCoreTemplatesUseInjectedDependenciesAndExplicitLocale(): void
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn(null);
        $language  = new Language('en');
        $templates = new MailTemplates($settings, $language, ['en', 'zh-Hans', 'zh-Hant']);
        $email     = $this->createMock(QueuedEmail::class);
        $email->expects($this->never())->method('setSubject');
        Services::injectMock('email', $email);

        foreach (['zh-Hans' => '账户邀请', 'zh-Hant' => '帳戶邀請', 'en' => 'Account invitation'] as $locale => $subject) {
            $rendered = $templates->render('invitation', $locale, [
                'username' => '<user>', 'link' => 'https://example.invalid', 'microsoftLogin' => '<script>evil</script>',
            ]);
            $this->assertSame($subject, $rendered['subject']);
            $this->assertStringContainsString('&lt;user&gt;', $rendered['body']);
            $this->assertStringNotContainsString('<script>', $rendered['body']);
            $this->assertStringNotContainsString('{microsoftLogin}', $rendered['body']);
            $this->assertStringNotContainsString('<html>', $rendered['body']);
            $this->assertSame('en', $language->getLocale());
        }

        $this->expectException(InvalidArgumentException::class);
        $templates->get('invitation', 'fr');
    }

    public function testCoreTemplatePersistenceUsesInjectedSettings(): void
    {
        $subjectKey = MailTemplates::settingKey('invitation', 'zh-Hans', 'subject');
        $bodyKey    = MailTemplates::settingKey('invitation', 'zh-Hans', 'body');
        $settings   = $this->createMock(Settings::class);
        $settings->expects($this->exactly(2))->method('get')->willReturnMap([
            [$subjectKey, null, 'Injected subject'],
            [$bodyKey, null, 'Injected {link}'],
        ]);
        $settings->expects($this->once())->method('setMany')->with([
            $subjectKey => 'Saved subject',
            $bodyKey    => 'Saved {link}',
        ]);
        $settings->expects($this->once())->method('forgetMany')->with([$subjectKey, $bodyKey]);
        $templates = new MailTemplates($settings, new Language('en'), ['en', 'zh-Hans']);

        $this->assertSame(['subject' => 'Injected subject', 'body' => 'Injected {link}'], $templates->get('invitation', 'zh-Hans'));
        $templates->save('invitation', 'zh-Hans', 'Saved subject', 'Saved {link}');
        $templates->reset('invitation', 'zh-Hans');
    }

    public function testShieldMailViewsUseRequestLocaleForEveryTemplate(): void
    {
        $request  = service('request');
        $previous = $request->getLocale();

        try {
            foreach (config('App')->supportedLocales as $locale) {
                $request->setLocale($locale);

                foreach (['magic-link-email' => 'magic-link', 'action_email_activate_email' => 'activation', 'action_email_2fa_email' => 'email-2fa'] as $view => $type) {
                    $subject = $locale . ' ' . $type . ' <{username}>';
                    $token   = $type === 'magic-link' ? 'link' : 'code';
                    service('mailTemplates')->save($type, $locale, $subject, '<p>{username}: {' . $token . '}</p>');
                    $email = $this->createMock(QueuedEmail::class);
                    $email->expects($this->once())->method('setSubject')->with($locale . ' ' . $type . ' <<user>>');
                    Services::injectMock('email', $email);

                    $body = view(config('Auth')->views[$view], [
                        'user'      => (object) ['username' => '<user>'], 'token' => 'a&b', 'code' => '<123>',
                        'ipAddress' => '<ip>', 'userAgent' => '<agent>', 'date' => '<date>',
                    ]);
                    $this->assertStringContainsString('<title>' . esc($locale . ' ' . $type . ' <<user>>') . '</title>', $body);
                    $this->assertStringContainsString('&lt;user&gt;', $body);
                    $this->assertStringNotContainsString('<user>', $body);
                    if ($type === 'magic-link') {
                        $this->assertStringContainsString(url_to('verify-magic-link', $locale) . '?token=a%26b', $body);
                    } else {
                        $this->assertStringContainsString('&lt;123&gt;', $body);
                    }
                }
            }
        } finally {
            $request->setLocale($previous);
        }
    }

    public function testHtmlShieldTemplateCanBeSavedAndReset(): void
    {
        $this->loginAs('superadmin');
        $templates = service('mailTemplates');

        foreach (config('App')->supportedLocales as $locale) {
            $this->assertStringContainsString('<a href="{link}"', $templates->get('magic-link', $locale)['body']);
            $this->assertStringContainsString('<table role="presentation"', $templates->get('magic-link', $locale)['body']);
            $this->assertStringContainsString('<h1>{code}</h1>', $templates->get('activation', $locale)['body']);
            $this->assertStringContainsString('<h1>{code}</h1>', $templates->get('email-2fa', $locale)['body']);

            foreach (['magic-link', 'activation', 'email-2fa'] as $type) {
                $this->assertStringContainsString("\n", $templates->get($type, $locale)['body']);
            }
        }

        $link     = 'https://example.invalid/login?token=a%26b';
        $rendered = $templates->render('magic-link', 'en', ['link' => $link]);
        $this->assertStringContainsString('href="' . $link . '"', $rendered['body']);
        $escapedLink = $templates->render('magic-link', 'en', ['link' => 'https://example.invalid/?token=" onclick="evil']);
        $this->assertStringContainsString('&quot; onclick=&quot;', $escapedLink['body']);
        $this->assertStringNotContainsString('onclick="evil', $escapedLink['body']);

        $page = $this->get('/en/admin/mail/templates?type=magic-link');
        $page->assertSee('HTML message');
        $this->assertStringContainsString('id="template-preview"', $page->response()->getBody());
        $this->assertStringContainsString('sandbox=""', $page->response()->getBody());

        $html = '<p>Hello {username}</p><a href="{link}">Sign in</a>';
        $this->post('/en/admin/mail/templates/magic-link/en', [csrf_token() => csrf_hash(), 'subject' => 'Sign in', 'body' => $html])->assertRedirect();
        $this->assertSame($html, $templates->get('magic-link', 'en')['body']);

        $this->post('/en/admin/mail/templates/magic-link/en/reset', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertStringContainsString('<a href="{link}"', $templates->get('magic-link', 'en')['body']);
    }

    public function testShieldCodeMailViewsRenderRequiredCodes(): void
    {
        $this->loginAs('superadmin');

        foreach (['action_email_activate_email' => 'activation', 'action_email_2fa_email' => 'email-2fa'] as $view => $type) {
            service('settings')->set(MailTemplates::settingKey($type, 'en', 'body'), 'Code: {code} for {username}');
            $body = view(config('Auth')->views[$view], [
                'user' => (object) ['username' => '<user>'],
                'code' => '<123>', 'ipAddress' => '127.0.0.1', 'userAgent' => 'Browser', 'date' => 'Today',
            ]);
            $this->assertStringContainsString('Code: &lt;123&gt; for &lt;user&gt;', $body);
            $this->assertStringNotContainsString('<123>', $body);
        }
    }

    public function testEmailQueuePageFiltersLogsAndNeverRendersQueuePayload(): void
    {
        $this->loginAs('superadmin');
        $db = db_connect();
        $db->table('queue_jobs')->insert([
            'queue'    => 'email', 'payload' => 'Private body and smtp-secret', 'status' => 0,
            'attempts' => 1, 'created_at' => time(), 'available_at' => time(),
        ]);
        $jobId = $db->insertID();
        $db->table('email_delivery_logs')->insert([
            'job_id'         => $jobId, 'recipient' => 'target@example.com', 'subject' => '<Private subject>',
            'status'         => 'failed', 'attempts' => 1, 'created_at' => date('Y-m-d H:i:s'),
            'failure_reason' => 'SMTP server rejected message (code 550).',
        ]);
        $db->table('email_delivery_logs')->insert([
            'recipient' => 'other@example.com', 'subject' => 'Other', 'status' => 'sent',
            'attempts'  => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $db->table('email_delivery_logs')->insert([
            'recipient' => 'other-failed@example.com', 'subject' => 'Wrong recipient', 'status' => 'failed',
            'attempts'  => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $db->table('email_delivery_logs')->insert([
            'recipient' => 'target@example.com', 'subject' => 'Wrong status', 'status' => 'sent',
            'attempts'  => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $db->table('queue_jobs')->insert([
            'queue'    => 'other', 'payload' => 'Other queue secret', 'status' => 0,
            'attempts' => 0, 'created_at' => time(), 'available_at' => time(),
        ]);

        $logs = $this->get('/en/admin/mail/deliveries?status=failed&recipient=TARGET');
        $logs->assertOK();
        $logs->assertSee('SMTP server rejected message (code 550).');
        $this->assertStringContainsString('&lt;Private subject&gt;', $logs->response()->getBody());
        $this->assertStringNotContainsString('other@example.com', $logs->response()->getBody());
        $logs->assertDontSee('Wrong recipient');
        $logs->assertDontSee('Wrong status');
        $this->assertStringNotContainsString('Private body', $logs->response()->getBody());
        $this->assertStringContainsString('private, no-store', $logs->response()->getHeaderLine('Cache-Control'));
        $logs->assertSee('Total: 1 (1 - 1)');
        $this->assertStringContainsString('<option value="failed" selected>', $logs->response()->getBody());
        $this->assertStringContainsString('name="recipient" value="TARGET" maxlength="254"', $logs->response()->getBody());

        $queue = $this->get('/en/admin/mail/deliveries?view=queue');
        $queue->assertRedirectTo('/en/admin/queue-jobs');
        $this->get('/zh-Hans/admin/mail/deliveries')->assertSee('发送记录');
    }

    public function testEmailQueueAndAuditTimesUseViewerTimezone(): void
    {
        $this->loginAs('superadmin');
        $viewer           = auth()->user();
        $viewer->timezone = 'Asia/Shanghai';
        auth()->getProvider()->save($viewer);

        $db = db_connect();
        $db->table('queue_jobs')->insert([
            'queue'        => 'email', 'payload' => 'Private body', 'status' => 0, 'attempts' => 0,
            'created_at'   => strtotime('2026-01-01 00:00:00 UTC'),
            'available_at' => strtotime('2026-01-01 01:15:00 UTC'),
        ]);
        $db->table('email_delivery_logs')->insert([
            'job_id'       => $db->insertID(), 'recipient' => 'time@example.com', 'subject' => 'Timezone check',
            'status'       => 'sent', 'attempts' => 1, 'created_at' => '2026-01-01 00:00:00',
            'processed_at' => '2026-01-01 01:15:00',
        ]);

        $logs = $this->get('/en/admin/mail/deliveries');
        $logs->assertOK();
        $this->assertStringContainsString('<td class="text-nowrap">2026-01-01 08:00:00</td>', $logs->response()->getBody());
        $this->assertStringContainsString('<td class="text-nowrap">2026-01-01 09:15:00</td>', $logs->response()->getBody());

        $queue = $this->get('/en/admin/queue-jobs');
        $queue->assertOK();
        $this->assertStringContainsString('<td class="text-nowrap">2026-01-01 08:00:00</td>', $queue->response()->getBody());
        $this->assertStringContainsString('<td class="text-nowrap">2026-01-01 09:15:00</td>', $queue->response()->getBody());
    }

    public function testEmailAuditPaginationRetainsFilters(): void
    {
        $this->loginAs('superadmin');

        for ($index = 0; $index < 21; $index++) {
            db_connect()->table('email_delivery_logs')->insert([
                'recipient' => 'paging@example.com', 'subject' => 'Page ' . $index,
                'status'    => 'failed', 'attempts' => 1, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $first = $this->get('/en/admin/mail/deliveries?status=failed&recipient=paging');
        $first->assertOK();
        TableLayoutAssertions::assertTablesInCards($first->response()->getBody(), paginated: true);
        $document = new DOMDocument();
        $document->loadHTML($first->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $links = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " page-link ") and contains(@href, "page=2")]');
        $this->assertCount(1, $links);
        parse_str((string) parse_url($links->item(0)->getAttribute('href'), PHP_URL_QUERY), $parameters);
        $this->assertSame('2', $parameters['page']);
        $this->assertSame('failed', $parameters['status']);
        $this->assertSame('paging', $parameters['recipient']);
        $first->assertSee('Total: 21 (1 - 20)');

        $second = $this->get('/en/admin/mail/deliveries?status=failed&recipient=paging&page=2');
        $second->assertOK();
        TableLayoutAssertions::assertTablesInCards($second->response()->getBody(), paginated: true);
        $this->assertStringContainsString('Page 0', $second->response()->getBody());
        $this->assertStringNotContainsString('Page 20', $second->response()->getBody());
        $second->assertSee('Total: 21 (21 - 21)');
    }

    public function testEmailQueueEmptyStatesReuseUiCells(): void
    {
        $this->loginAs('superadmin');

        foreach (['en', 'zh-Hans', 'zh-Hant'] as $locale) {
            $empty = $this->get('/' . $locale . '/admin/mail/deliveries');
            $empty->assertOK();
            TableLayoutAssertions::assertTablesInCards($empty->response()->getBody(), paginated: true);
            $empty->assertSee(lang('Admin.mailNoRecords'), 'h3');
            $this->assertStringNotContainsString('class="empty-action"', $empty->response()->getBody());
            $this->assertStringNotContainsString('(1 - 0)', $empty->response()->getBody());

            $filtered = $this->get('/' . $locale . '/admin/mail/deliveries?status=failed&recipient=missing');
            $filtered->assertOK();
            $filtered->assertSee(lang('Admin.userClear'), 'a');
            $document = new DOMDocument();
            $document->loadHTML($filtered->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $this->assertSame(1, $xpath->query('//div[@class="empty-action"]/a[@href="/' . $locale . '/admin/mail/deliveries"]')->length);

            $queue = $this->get('/' . $locale . '/admin/queue-jobs');
            $queue->assertOK();
            TableLayoutAssertions::assertTablesInCards($queue->response()->getBody(), paginated: true);
            $queue->assertSee(lang('Admin.mailNoRecords'), 'h3');
            $this->assertStringNotContainsString('id="mail-recipient"', $queue->response()->getBody());
            $this->assertStringNotContainsString('class="empty-action"', $queue->response()->getBody());
        }
    }

    public function testSettingsAreSavedAndUsedByEmailService(): void
    {
        $this->loginAs('superadmin');

        $page = $this->get('/en/admin/settings/email');
        $page->assertOK();
        $page->assertSee('Email delivery');
        $this->assertStringContainsString('name="' . csrf_token() . '"', $page->response()->getBody());
        $this->assertStringNotContainsString('name="SMTPPass"', $page->response()->getBody());
        $this->assertStringContainsString('class="form-label required" for="email-fromEmail"', $page->response()->getBody());
        $this->assertStringContainsString('class="form-label required" for="email-SMTPHost"', $page->response()->getBody());
        $this->assertStringContainsString('class="form-label" for="email-SMTPUser"', $page->response()->getBody());
        $this->assertStringContainsString('action="/en/admin/settings/email/test"', $page->response()->getBody());
        $this->assertStringContainsString('name="test_email" type="email"', $page->response()->getBody());
        $this->assertStringContainsString('src="/static/js/form-submission.js"', $page->response()->getBody());
        $this->assertStringContainsString('class="d-none" data-submit-loading><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Sending test email', $page->response()->getBody());

        $this->post('/en/admin/settings/email', $this->validSettings())->assertRedirect();
        $this->assertSame('sender@example.com', service('settings')->get('Email.fromEmail'));
        $this->assertSame(587, service('settings')->get('Email.SMTPPort'));

        $email = service('email', null, false);
        $this->assertSame('smtp', $email->protocol);
        $this->assertSame('smtp.example.com', $email->SMTPHost);
        $this->assertSame('sender@example.com', $email->fromEmail);
        $this->assertSame(config('Email')->SMTPPass, $email->SMTPPass);
        $this->get('/zh-Hans/admin/settings/email')->assertSee('邮件发送');
        $this->get('/zh-Hant/admin/settings/email')->assertSee('郵件發送');
        $this->assertStringContainsString('data-submit-loading><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>正在发送测试邮件', $this->get('/zh-Hans/admin/settings/email')->response()->getBody());
    }

    public function testInvalidSettingsDoNotOverwriteSavedConfiguration(): void
    {
        $this->loginAs('superadmin');
        $this->post('/en/admin/settings/email', $this->validSettings())->assertRedirect();

        $invalid              = $this->validSettings();
        $invalid['fromEmail'] = 'invalid';
        $invalid['SMTPPort']  = '70000';
        $this->post('/en/admin/settings/email', $invalid)->assertRedirect();
        $this->assertSame('sender@example.com', service('settings')->get('Email.fromEmail'));
        $this->assertSame(587, service('settings')->get('Email.SMTPPort'));

        $invalid             = $this->validSettings();
        $invalid['SMTPHost'] = '';
        $this->post('/en/admin/settings/email', $invalid)->assertRedirect();
        $this->assertSame('smtp.example.com', service('settings')->get('Email.SMTPHost'));
    }

    public function testMailProtocolDoesNotRequireOrEraseSmtpSettings(): void
    {
        $this->loginAs('superadmin');
        service('settings')->set('Email.protocol', 'mail');
        $page = $this->get('/en/admin/settings/email');
        $this->assertStringContainsString('id="email-smtp-settings" class="d-none"', $page->response()->getBody());

        $this->post('/en/admin/settings/email', $this->validSettings())->assertRedirect();
        $page = $this->get('/en/admin/settings/email');
        $this->assertStringContainsString('id="email-smtp-settings">', $page->response()->getBody());

        $this->post('/en/admin/settings/email', [
            csrf_token() => csrf_hash(),
            'fromEmail'  => 'new@example.com',
            'fromName'   => 'Example',
            'protocol'   => 'mail',
        ])->assertRedirect();

        $this->assertSame('mail', service('settings')->get('Email.protocol'));
        $this->assertSame('new@example.com', service('settings')->get('Email.fromEmail'));
        $this->assertSame('smtp.example.com', service('settings')->get('Email.SMTPHost'));
        $this->assertSame(587, service('settings')->get('Email.SMTPPort'));
    }

    public function testSendingTestEmailRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);

        $this->post('/en/admin/settings/email/test', ['test_email' => 'recipient@example.com']);
    }

    public function testInvalidRecipientAndMissingSenderDoNotSend(): void
    {
        $this->loginAs('superadmin');
        service('settings')->set('Email.fromEmail', '');
        $email = $this->createMock(QueuedEmail::class);
        $email->expects($this->never())->method('send');
        $email->expects($this->never())->method('sendDirect');
        Services::injectMock('email', $email);

        $this->post('/en/admin/settings/email/test', [csrf_token() => csrf_hash(), 'test_email' => 'invalid'])->assertRedirect();
        $this->assertNotEmpty(session('test_email_errors.test_email'));

        $this->post('/en/admin/settings/email/test', [csrf_token() => csrf_hash(), 'test_email' => 'recipient@example.com'])->assertRedirect();
        $this->assertSame('danger', session('alert')['type']);
        $this->assertSame(lang('Admin.testEmailNotConfigured'), session('alert')['message']);
    }

    public function testSendingTestEmailReportsSuccessAndFailure(): void
    {
        $this->loginAs('superadmin');
        service('settings')->set('Email.fromEmail', 'sender@example.com');

        foreach ([true, false] as $sent) {
            $email           = $this->createMock(QueuedEmail::class);
            $email->SMTPPass = 'test-secret';
            $email->SMTPUser = 'test-user';
            $email->expects($this->once())->method('setTo')->with('recipient@example.com');
            $email->expects($this->once())->method('setSubject')->with(lang('Admin.testEmailSubject'));
            $email->expects($this->once())->method('setMessage')->with(lang('Admin.testEmailBody'));
            $email->expects($this->never())->method('send');
            $email->expects($this->once())->method('sendDirect')->willReturn($sent);
            $email->expects($sent ? $this->never() : $this->once())->method('printDebugger')->with([])
                ->willReturn('<pre>SMTP 535 authentication failed for test-user: test-secret</pre>');
            Services::injectMock('email', $email);

            $this->post('/en/admin/settings/email/test', [csrf_token() => csrf_hash(), 'test_email' => 'recipient@example.com'])->assertRedirect();
            $this->assertSame($sent ? 'success' : 'danger', session('alert')['type']);
            $this->assertSame(lang($sent ? 'Admin.testEmailSent' : 'Admin.testEmailFailed'), session('alert')['message']);
            if ($sent) {
                $this->assertArrayNotHasKey('detail', session('alert'));
            } else {
                $this->assertStringContainsString('SMTP 535 authentication failed', session('alert')['detail']);
                $this->assertStringNotContainsString('test-secret', session('alert')['detail']);
                $this->assertStringNotContainsString('test-user', session('alert')['detail']);
                $this->assertStringNotContainsString('<pre>', session('alert')['detail']);
            }
        }
    }

    private function loginAs(string $group): void
    {
        $user        = new AdminUser(['username' => 'mailsettings' . $group]);
        $user->email = 'mailsettings@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($group);
        auth()->login($user);
    }

    private function validSettings(): array
    {
        return [
            csrf_token() => csrf_hash(),
            'fromEmail'  => 'sender@example.com',
            'fromName'   => 'Example',
            'protocol'   => 'smtp',
            'SMTPHost'   => 'smtp.example.com',
            'SMTPUser'   => 'sender',
            'SMTPPort'   => '587',
            'SMTPCrypto' => 'tls',
        ];
    }
}
