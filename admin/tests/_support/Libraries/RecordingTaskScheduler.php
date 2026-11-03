<?php

declare(strict_types=1);

namespace Tests\Support\Libraries;

use CodeIgniter\Tasks\Scheduler;
use CodeIgniter\Tasks\Task;

class RecordingTaskScheduler extends Scheduler
{
    public array $executed = [];

    protected function createTask(string $type, $action): Task
    {
        $task = new class ($type, $action, $this) extends Task {
            public function __construct(string $type, mixed $action, private RecordingTaskScheduler $scheduler)
            {
                parent::__construct($type, $action);
            }

            public function run(): void
            {
                $this->scheduler->executed[] = [$this->name, $this->getAction()];
            }
        };
        $this->tasks[] = $task;

        return $task;
    }
}
