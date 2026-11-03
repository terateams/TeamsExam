<?php

use CodeIgniter\Autoloader\Autoloader;
use CodeIgniter\Autoloader\FileLocator;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Config\Factories;
use CodeIgniter\Tasks\Scheduler;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use Geminus\Admin\Config\Tasks;
use Modules\ExamVouchers\Commands\RemindExpiry;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class ExamVoucherTasksTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BaseConfig::reset();
        Factories::reset('config');
        Services::resetSingle('locator');
    }

    protected function tearDown(): void
    {
        BaseConfig::reset();
        Factories::reset('config');
        Services::resetSingle('locator');
        parent::tearDown();
    }

    public function testApplicationRegistersEmailAndVoucherTasks(): void
    {
        $this->assertFileDoesNotExist(APPPATH . 'Config/Tasks.php');
        $this->assertFileDoesNotExist(ROOTPATH . 'modules/ExamVouchers/Config/Tasks.php');
        $scheduler = $this->assertRegisteredTasks();
        $commands  = (new Commands())->getCommands();

        foreach ($scheduler->getTasks() as $task) {
            if ($task->name === 'exam-voucher-expiry') {
                $this->assertArrayHasKey($task->getAction(), $commands);
                $this->assertSame(RemindExpiry::class, $commands[$task->getAction()]['class']);
            }
        }
    }

    #[DataProvider('provideModuleTasksDoNotDependOnDiscoveryOrder')]
    public function testModuleTasksDoNotDependOnDiscoveryOrder(array $order): void
    {
        $autoloader = new Autoloader();

        foreach ($order as $namespace => $path) {
            $autoloader->addNamespace($namespace, ROOTPATH . $path);
        }

        $autoloader->addNamespace('CodeIgniter\Tasks', ROOTPATH . 'vendor/codeigniter4/tasks/src');
        $autoloader->addNamespace('CodeIgniter', SYSTEMPATH);
        $autoloader->addNamespace('Config', APPPATH . 'Config');
        Services::injectMock('locator', new FileLocator($autoloader));

        $this->assertRegisteredTasks();
    }

    public static function provideModuleTasksDoNotDependOnDiscoveryOrder(): iterable
    {
        $modules = [
            'Geminus\Admin'        => 'geminus/Admin',
            'Modules\ExamVouchers' => 'modules/ExamVouchers',
        ];

        yield 'admin first' => [$modules];

        yield 'vouchers first' => [array_reverse($modules, true)];
    }

    private function assertRegisteredTasks(): Scheduler
    {
        $scheduler = new Scheduler();
        $config    = config('Tasks', false);
        $this->assertInstanceOf(Tasks::class, $config);
        $config->init($scheduler);

        $tasks = [];
        $this->assertCount(2, $scheduler->getTasks());

        foreach ($scheduler->getTasks() as $task) {
            $tasks[$task->name] = [$task->getType(), $task->action, $task->getExpression()];
        }
        ksort($tasks);

        $this->assertSame([
            'exam-voucher-expiry' => ['command', 'exam-vouchers:remind-expiry', '0 0 * * *'],
            'send-email'          => ['command', 'queue:work email --stop-when-empty', '* * * * *'],
        ], $tasks);

        foreach ($scheduler->getTasks() as $task) {
            $this->assertTrue($task->shouldRun('2026-11-03 00:00:00'));
            $this->assertSame($task->name === 'send-email', $task->shouldRun('2026-11-03 00:01:00'));
            $this->assertSame($task->name === 'send-email', $task->shouldRun('2026-11-03 12:00:00'));
        }

        return $scheduler;
    }
}
