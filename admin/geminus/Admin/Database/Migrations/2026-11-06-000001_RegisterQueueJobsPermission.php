<?php

declare(strict_types=1);

namespace Geminus\Admin\Database\Migrations;

use CodeIgniter\Database\Migration;

class RegisterQueueJobsPermission extends Migration
{
    public function up(): void
    {
        $permissions = setting('AuthGroups.permissions');
        $matrix      = setting('AuthGroups.matrix');
        $permissions['queue-jobs.view'] ??= 'View queue jobs';
        if (! in_array('queue-jobs.*', $matrix['superadmin'] ?? [], true) && ! in_array('queue-jobs.view', $matrix['superadmin'] ?? [], true)) {
            $matrix['superadmin'][] = 'queue-jobs.*';
        }

        service('settings')->setMany(['AuthGroups.permissions' => $permissions, 'AuthGroups.matrix' => $matrix]);
    }

    public function down(): void
    {
    }
}
