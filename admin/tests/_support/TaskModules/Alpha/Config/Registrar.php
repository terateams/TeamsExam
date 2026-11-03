<?php

declare(strict_types=1);

namespace Tests\Support\TaskModules\Alpha\Config;

class Registrar
{
    public static mixed $commands = [
        [
            'name'     => 'alpha-daily',
            'command'  => 'example:alpha',
            'schedule' => '0 0 * * *',
        ],
    ];

    public static function Tasks(): array
    {
        return ['commands' => self::$commands];
    }
}
