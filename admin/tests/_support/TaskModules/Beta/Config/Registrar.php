<?php

declare(strict_types=1);

namespace Tests\Support\TaskModules\Beta\Config;

class Registrar
{
    public static function Tasks(): array
    {
        return ['commands' => [
            [
                'name'     => 'beta-frequent',
                'command'  => 'example:beta --dry-run',
                'schedule' => '*/5 * * * *',
            ],
        ]];
    }
}
