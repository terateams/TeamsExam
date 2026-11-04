<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

use CodeIgniter\Config\BaseConfig;

class AdminMenu extends BaseConfig
{
    /**
     * @var list<array{permission: list<string>|string, label: string, icon: string, children: list<array{route: string, label: string, active: string}>}|array{permission: list<string>|string, label: string, icon: string, route: string, active: string}>
     */
    public array $items = [];
}
