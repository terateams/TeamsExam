<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

use CodeIgniter\Tasks\Config\Tasks as BaseTasks;
use CodeIgniter\Tasks\Scheduler;
use InvalidArgumentException;

class Tasks extends BaseTasks
{
    /**
     * @var list<array{name: string, command: string, schedule: string}>
     */
    public array $commands = [
        [
            'name'     => 'send-email',
            'command'  => 'queue:work email --stop-when-empty',
            'schedule' => '* * * * *',
        ],
    ];

    public function init(Scheduler $schedule): void
    {
        $this->validateCommands();

        foreach ($this->commands as $command) {
            $schedule->command($command['command'])->cron($command['schedule'])->named($command['name']);
        }
    }

    private function validateCommands(): void
    {
        if (! array_is_list($this->commands)) {
            throw new InvalidArgumentException('Tasks.commands must be a list of command declarations.');
        }

        $names = [];

        foreach ($this->commands as $index => $command) {
            $task = "Tasks.commands[{$index}]";

            if (! is_array($command)) {
                throw new InvalidArgumentException("{$task} must be a command declaration array.");
            }

            if (isset($command['name']) && is_string($command['name'])) {
                $task .= " ({$command['name']})";
            }

            foreach (['name', 'command', 'schedule'] as $field) {
                if (! isset($command[$field]) || ! is_string($command[$field]) || trim($command[$field]) === '') {
                    throw new InvalidArgumentException("{$task}.{$field} must be a non-empty string.");
                }
            }

            if (array_diff(array_keys($command), ['name', 'command', 'schedule']) !== []) {
                throw new InvalidArgumentException("{$task} contains unsupported fields; only name, command and schedule are allowed.");
            }

            if (isset($names[$command['name']])) {
                throw new InvalidArgumentException("{$task} has duplicate task name: {$command['name']}.");
            }

            $names[$command['name']] = true;
        }
    }
}
