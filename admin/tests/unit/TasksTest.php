<?php

declare(strict_types=1);

use CodeIgniter\Autoloader\Autoloader;
use CodeIgniter\Autoloader\FileLocator;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Config\Factories;
use CodeIgniter\Settings\Config\Settings as SettingsConfig;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Tasks\Scheduler;
use CodeIgniter\Tasks\Task;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Config\Services;
use Geminus\Admin\Config\Tasks;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Libraries\RecordingTaskScheduler;
use Tests\Support\TaskModules\Alpha\Config\Registrar as AlphaRegistrar;

/**
 * @internal
 */
final class TasksTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    private array $alphaCommands;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alphaCommands = AlphaRegistrar::$commands;
    }

    protected function tearDown(): void
    {
        AlphaRegistrar::$commands = $this->alphaCommands;
        BaseConfig::reset();
        Factories::reset('config');
        Services::resetSingle('locator');
        Services::resetSingle('scheduler');
        Services::resetSingle('settings');
        Services::resetSingle('cronExpression');
        Services::resetSingle('commands');
        parent::tearDown();
    }

    public function testDefaultEmailTaskAndExistingOptions(): void
    {
        $config    = $this->discover([]);
        $scheduler = new Scheduler();
        $config->init($scheduler);

        $this->assertSame([
            ['send-email', 'command', 'queue:work email --stop-when-empty', '* * * * *'],
        ], $this->declarations($scheduler));
        $this->assertFalse($config->logPerformance);
        $this->assertSame(10, $config->maxLogsPerTask);
        $this->assertTrue($scheduler->getTasks()[0]->shouldRun('2026-11-03 12:34:00'));
        $this->assertTrue($scheduler->getTasks()[0]->shouldRun('2026-11-03 12:35:00'));
    }

    #[DataProvider('provideModulesAggregateThroughNativeDiscovery')]
    public function testModulesAggregateThroughNativeDiscovery(array $order): void
    {
        $scheduler = new Scheduler();
        $this->discover($order)->init($scheduler);

        $actual = $this->declarations($scheduler);
        usort($actual, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $this->assertSame([
            ['alpha-daily', 'command', 'example:alpha', '0 0 * * *'],
            ['beta-frequent', 'command', 'example:beta --dry-run', '*/5 * * * *'],
            ['send-email', 'command', 'queue:work email --stop-when-empty', '* * * * *'],
        ], $actual);
    }

    public static function provideModulesAggregateThroughNativeDiscovery(): iterable
    {
        yield 'admin alpha beta' => [['Admin', 'Alpha', 'Beta']];

        yield 'admin beta alpha' => [['Admin', 'Beta', 'Alpha']];

        yield 'alpha admin beta' => [['Alpha', 'Admin', 'Beta']];

        yield 'beta admin alpha' => [['Beta', 'Admin', 'Alpha']];

        yield 'alpha beta admin' => [['Alpha', 'Beta', 'Admin']];

        yield 'beta alpha admin' => [['Beta', 'Alpha', 'Admin']];
    }

    public function testNonArrayCommandsFailDuringNativeRegistrarMerge(): void
    {
        AlphaRegistrar::$commands = 'invalid commands';
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Tasks::$commands');
        $this->discover(['Alpha']);
    }

    #[DataProvider('provideInvalidDeclarationsFailBeforeRegisteringAnyTask')]
    public function testInvalidDeclarationsFailBeforeRegisteringAnyTask(array $commands, string $message): void
    {
        AlphaRegistrar::$commands = $commands;
        $config                   = $this->discover(['Alpha', 'Beta']);
        $scheduler                = new Scheduler();

        try {
            $config->init($scheduler);
            $this->fail('Invalid task declaration was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
            $this->assertSame([], $scheduler->getTasks());
        }
    }

    public static function provideInvalidDeclarationsFailBeforeRegisteringAnyTask(): iterable
    {
        $valid = ['name' => 'invalid-job', 'command' => 'example:invalid', 'schedule' => '0 0 * * *'];

        yield 'not a list' => [['job' => $valid], 'Tasks.commands must be a list'];

        yield 'not a declaration' => [['bad'], 'Tasks.commands[1]'];

        foreach (['name', 'command', 'schedule'] as $field) {
            yield "missing {$field}" => [[array_diff_key($valid, [$field => true])], ".{$field}"];

            foreach ([null, false, 42, [], '', '   '] as $index => $value) {
                yield "{$field} invalid type or blank {$index}" => [[array_replace($valid, [$field => $value])], ".{$field}"];
            }
        }

        yield 'unknown task type' => [[$valid + ['shell' => 'echo unsafe']], 'unsupported fields'];

        yield 'conflict with email default' => [[array_replace($valid, ['name' => 'send-email'])], 'duplicate task name: send-email'];

        yield 'conflict across modules' => [[array_replace($valid, ['name' => 'beta-frequent'])], 'duplicate task name: beta-frequent'];

        yield 'conflict within module' => [[$valid, $valid], 'duplicate task name: invalid-job'];
    }

    #[DataProvider('provideScheduleIsPassedUnchangedToNativeTasks')]
    public function testScheduleIsPassedUnchangedToNativeTasks(string $schedule): void
    {
        AlphaRegistrar::$commands = [
            ['name' => 'module-schedule', 'command' => 'example:run', 'schedule' => $schedule],
        ];
        $scheduler = new Scheduler();
        $this->discover(['Alpha'])->init($scheduler);

        $this->assertSame([
            ['send-email', 'command', 'queue:work email --stop-when-empty', '* * * * *'],
            ['module-schedule', 'command', 'example:run', $schedule],
        ], $this->declarations($scheduler));
    }

    public static function provideScheduleIsPassedUnchangedToNativeTasks(): iterable
    {
        yield 'documented daily schedule' => ['0 0 * * *'];

        yield 'no project-specific syntax gate' => ['@daily'];

        yield 'no range validation' => ['60 * * * *'];

        yield 'no syntax validation' => ['not cron'];
    }

    public function testTasksListUsesAggregateWithoutExecutingActions(): void
    {
        $this->discover(['Beta', 'Alpha']);
        $scheduler = new RecordingTaskScheduler();
        Services::injectMock('scheduler', $scheduler);
        $this->useSettingsWithoutStorage();

        $this->resetStreamFilterBuffer();
        service('commands')->run('tasks:list', []);
        $output = $this->getStreamFilterBuffer();

        foreach (['send-email', 'alpha-daily', 'beta-frequent', '* * * * *', '0 0 * * *', '*/5 * * * *'] as $value) {
            $this->assertStringContainsString($value, $output);
        }

        $this->assertCount(3, $scheduler->getTasks());
        $this->assertSame([], $scheduler->executed);
    }

    public function testTasksRunUsesAggregateWithoutExecutingProductionActions(): void
    {
        $this->discover(['Alpha', 'Beta']);
        $scheduler = new RecordingTaskScheduler();
        Services::injectMock('scheduler', $scheduler);
        service('cronExpression')->testTime('2026-11-03 00:00:00');
        $this->useSettingsWithoutStorage();

        $result = service('commands')->run('tasks:run', []);

        $this->assertSame(EXIT_SUCCESS, $result);
        $this->assertSame([
            ['send-email', 'queue:work email --stop-when-empty'],
            ['alpha-daily', 'example:alpha'],
            ['beta-frequent', 'example:beta --dry-run'],
        ], $scheduler->executed);
    }

    private function discover(array $order): Tasks
    {
        BaseConfig::reset();
        Factories::reset('config');
        $autoloader = new Autoloader();

        foreach (in_array('Admin', $order, true) ? $order : ['Admin', ...$order] as $module) {
            if ($module === 'Admin') {
                $autoloader->addNamespace('Geminus\Admin', ROOTPATH . 'geminus/Admin');
            } else {
                $autoloader->addNamespace("Tests\\Support\\TaskModules\\{$module}", SUPPORTPATH . "TaskModules/{$module}");
            }
        }

        $autoloader->addNamespace('CodeIgniter\Tasks', ROOTPATH . 'vendor/codeigniter4/tasks/src');
        $autoloader->addNamespace('CodeIgniter\Settings', ROOTPATH . 'vendor/codeigniter4/settings/src');
        $autoloader->addNamespace('CodeIgniter', SYSTEMPATH);
        $autoloader->addNamespace('Config', APPPATH . 'Config');
        Services::injectMock('locator', new FileLocator($autoloader));

        $config = config('Tasks');
        $this->assertInstanceOf(Tasks::class, $config);

        return $config;
    }

    private function declarations(Scheduler $scheduler): array
    {
        return array_map(static fn (Task $task): array => [$task->name, $task->getType(), $task->getAction(), $task->getExpression()], $scheduler->getTasks());
    }

    private function useSettingsWithoutStorage(): void
    {
        $config           = new SettingsConfig();
        $config->handlers = [];
        Services::injectMock('settings', new Settings($config));
    }
}
