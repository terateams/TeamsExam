<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Geminus\Admin\Config\OperationAudit;
use Geminus\Admin\Config\Registrar;
use Geminus\Admin\Libraries\AuditSubmission;

/**
 * @internal
 */
final class AuditSubmissionTest extends CIUnitTestCase
{
    private function definition(array $fields = ['title']): array
    {
        return [
            'controller' => 'Modules\\Example\\Controllers\\Items::update',
            'methods'    => ['POST'], 'action' => 'item.update',
            'object'     => ['type' => 'item', 'route_parameter' => 0], 'fields' => $fields,
        ];
    }

    public function testMatchingAndScalarCaptureDoNotTrustSubmittedIdentity(): void
    {
        $definition = $this->definition(['title', 'enabled', 'count', 'optional']);
        $collector  = new AuditSubmission([$definition]);
        $this->assertSame($definition, $collector->match('\\Modules\\Example\\Controllers\\Items', 'update', 'post'));
        $this->assertNull($collector->match('Modules\\Example\\Controllers\\Items', 'update', 'GET'));
        $this->assertNull($collector->match('Modules\\Other\\Controllers\\Items', 'update', 'POST'));
        $result = $collector->capture($definition, ['title' => '<script>unsafe</script>', 'enabled' => true, 'count' => 3, 'optional' => null, 'id' => 999, 'actor_id' => 999], [42]);
        $this->assertSame('42', $result['target_id']);
        $this->assertSame('item.update', $result['operation']);
        $this->assertSame(['title' => '<script>unsafe</script>', 'enabled' => true, 'count' => 3, 'optional' => null], json_decode($result['submission'], true)['fields']);
    }

    public function testSensitiveAndComplexValuesAreNotStored(): void
    {
        $definition = $this->definition(['password', 'clientSecret', 'csrf_token', 'api_key', 'verification_code', 'session_id', 'roles', 'title']);
        $collector  = new AuditSubmission([$definition]);
        $result     = $collector->capture($definition, array_fill_keys($definition['fields'], ['private-value']), []);
        $summary    = json_decode($result['submission'], true);
        $this->assertSame([], $summary['fields']);
        $this->assertSame(['roles' => 'complex_value', 'title' => 'complex_value'], $summary['omitted']);
        $this->assertStringNotContainsString('private-value', $result['submission']);
        $this->assertNull($result['target_id']);
    }

    public function testSummaryLimitsAndInvalidJsonAreObservable(): void
    {
        $fields     = array_map(static fn (int $number): string => 'field' . $number, range(1, 40));
        $definition = $this->definition($fields);
        $collector  = new AuditSubmission([$definition]);
        $summary    = $collector->capture($definition, array_fill_keys($fields, str_repeat('字', 400)), []);
        $decoded    = json_decode($summary['submission'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertLessThanOrEqual(8192, strlen($summary['submission']));
        $this->assertLessThanOrEqual(32, count($decoded['fields']));
        $this->assertSame(256, mb_strlen($decoded['fields']['field1']));
        $this->assertContains('field1', $decoded['truncated']);
        $this->assertTrue($decoded['limited']);
        $this->assertContains('size_limit', $decoded['omitted']);
        $this->assertSame([], array_intersect(array_keys($decoded['omitted']), $decoded['truncated']));
        $short = json_decode($collector->capture($definition, array_fill_keys($fields, 'short'), [])['submission'], true);
        $this->assertCount(32, $short['fields']);
        $this->assertArrayNotHasKey('field33', $short['fields']);
        $this->assertTrue($short['limited']);
        $invalid = json_decode($collector->capture($definition, null, [])['submission'], true);
        $this->assertSame('invalid_json', $invalid['input']);
        $this->assertSame([], $invalid['fields']);
    }

    public function testDuplicateDefinitionsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AuditSubmission([$this->definition(), $this->definition()]);
    }

    public function testInvalidDefinitionsAreRejected(): void
    {
        foreach ([
            ['methods' => ['GET']], ['methods'     => ['method' => 'POST']],
            ['controller' => 'invalid'], ['action' => 'invalid action'],
            ['object' => ['type' => 'item', 'route_parameter' => -1]],
            ['object' => ['type' => 'item', 'source' => 'request']],
            ['fields' => []], ['fields' => ['*']], ['fields' => ['file.path']], ['fields' => ['file.name.extra']], ['fields' => ['title', 'title']], ['default_success' => true],
        ] as $override) {
            try {
                new AuditSubmission([array_replace($this->definition(), $override)]);
                $this->fail('Invalid operation was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function testNullBytesAndInvalidUtf8DoNotInvalidateStorage(): void
    {
        $definition = $this->definition();
        $collector  = new AuditSubmission([$definition]);
        $result     = $collector->capture($definition, ['title' => "hello\0\xff"], ["42\0"]);
        $this->assertSame('42', $result['target_id']);
        $this->assertStringNotContainsString('\\u0000', $result['submission']);
        $this->assertIsArray(json_decode($result['submission'], true, flags: JSON_THROW_ON_ERROR));
    }

    public function testNonFiniteValuesAndSensitiveScalarsDoNotLoseOperation(): void
    {
        $definition     = $this->definition(['title', 'count', 'SMTPPass', 'accessKey', 'authCode', 'otp', 'authorization', 'cookie', 'passcode']);
        $collector      = new AuditSubmission([$definition]);
        $input          = array_fill_keys($definition['fields'], 'private-secret');
        $input['title'] = 'Safe title';
        $input['count'] = json_decode('{"count":1e999}', true)['count'];
        $result         = $collector->capture($definition, $input, [42]);
        $summary        = json_decode($result['submission'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['title' => 'Safe title'], $summary['fields']);
        $this->assertSame(['count' => 'invalid_value'], $summary['omitted']);
        $this->assertSame('item.update', $result['operation']);
        $this->assertSame('42', $result['target_id']);
        $this->assertStringNotContainsString('private-secret', $result['submission']);
    }

    public function testAdminOperationsRequireNonEmptySafeWhitelists(): void
    {
        $expected = [
            'Profile::language'              => ['language'],
            'Profile::update'                => ['language', 'timezone'],
            'EmailSettings::update'          => ['protocol', 'SMTPPort', 'SMTPCrypto'],
            'Users::update'                  => ['role', 'status'],
            'RoleSettings::createRole'       => ['name', 'title', 'description'],
            'RoleSettings::updateRole'       => ['title', 'description'],
            'RoleSettings::createPermission' => ['name', 'description'],
            'RoleSettings::updatePermission' => ['description'],
            'EmailTemplates::update'         => ['subject'],
            'MicrosoftSettings::update'      => ['enabled'],
            'MicrosoftSettings::approve'     => ['user_id'],
            'Profile::createToken'           => ['name', 'expires'],
        ];
        $definitions = Registrar::OperationAudit()['operations'];
        $collector   = new AuditSubmission($definitions);
        $actual      = [];

        foreach ($definitions as $definition) {
            $handler          = substr($definition['controller'], strlen('Geminus\\Admin\\Controllers\\'));
            $actual[$handler] = $definition['fields'];
            $this->assertNotEmpty($definition['fields']);
            $expectedFields = array_fill_keys($definition['fields'], 'submitted-value');
            $input          = $expectedFields;
            $input += ['password' => 'private-secret', 'email' => 'private@example.com', 'permissions' => ['users.edit'], 'body' => 'private-body', 'id' => 999];
            $captured = $collector->capture($definition, $input, [42]);
            $this->assertSame($expectedFields, json_decode($captured['submission'], true)['fields']);
            $this->assertSame(isset($definition['object']['route_parameter']) ? '42' : null, $captured['target_id']);
            $this->assertStringNotContainsString('private', $captured['submission']);
        }
        $this->assertSame($expected, $actual);

        foreach (['Users::store', 'Users::import', 'Users::invite', 'RoleSettings::permissions', 'EmailSettings::sendTest', 'EmailTemplates::reset', 'MicrosoftSettings::reject', 'MicrosoftSettings::revoke', 'MicrosoftLogin::connect', 'Notifications::open', 'Profile::avatar', 'Profile::removeAvatar', 'Profile::password', 'Profile::revokeToken'] as $handler) {
            [$controller, $method] = explode('::', $handler);
            $this->assertNull($collector->match('Geminus\\Admin\\Controllers\\' . $controller, $method, 'POST'));
        }
    }

    public function testRegisteredOperationsPreserveSafeFields(): void
    {
        $definitions = array_merge(
            Registrar::OperationAudit()['operations'],
            Modules\Announcements\Config\Registrar::OperationAudit()['operations'],
        );
        $collector = new AuditSubmission($definitions);

        foreach ($definitions as $definition) {
            $this->assertNotEmpty($definition['fields']);
            $input    = array_fill_keys($definition['fields'], 'safe-value');
            $captured = $collector->capture($definition, $input, [42]);
            $this->assertSame($input, json_decode($captured['submission'], true)['fields']);
        }
    }

    public function testAuditConfigPreservesOperationsFromAllRegistrars(): void
    {
        $config = new OperationAudit();

        $registrations = [
            Registrar::OperationAudit(),
            Modules\Announcements\Config\Registrar::OperationAudit(),
            Modules\ExamVouchers\Config\Registrar::OperationAudit(),
        ];
        $this->assertCount(20, $config->operations);

        foreach ($registrations as $registration) {
            foreach ($registration['operations'] as $definition) {
                $this->assertContains($definition, $config->operations);
            }
        }
    }

    public function testAuditConfigRejectsInvalidRegisteredOperations(): void
    {
        new OperationAudit();
        $registrars = OperationAudit::$registrars;
        $registrar  = new class () {
            public static array $operations = [];

            public static function OperationAudit(): array
            {
                return ['operations' => self::$operations];
            }
        };

        try {
            OperationAudit::$registrars[] = $registrar;

            foreach ([
                ['invalid' => $this->definition()],
                [array_replace($this->definition(), ['methods' => ['GET']])],
                [array_replace($this->definition(), ['fields' => []])],
                [$this->definition(), $this->definition()],
            ] as $definitions) {
                $registrar::$operations = $definitions;

                try {
                    new OperationAudit();
                    $this->fail('Invalid registered operations were accepted.');
                } catch (InvalidArgumentException $exception) {
                    $this->assertNotEmpty($exception->getMessage());
                }
            }
        } finally {
            OperationAudit::$registrars = $registrars;
        }
    }
}
