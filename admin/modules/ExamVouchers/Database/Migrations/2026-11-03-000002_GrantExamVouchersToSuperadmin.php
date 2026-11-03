<?php

declare(strict_types=1);

namespace Modules\ExamVouchers\Database\Migrations;

use CodeIgniter\Database\Migration;
use Geminus\Admin\Libraries\SuperadminGrants;

class GrantExamVouchersToSuperadmin extends Migration
{
    public function up(): void
    {
        $permissions = setting('AuthGroups.permissions');
        $permissions['exam-vouchers.manage'] ??= 'Can manage exam vouchers';
        $matrix               = setting('AuthGroups.matrix');
        $matrix['superadmin'] = SuperadminGrants::withPermissions($matrix['superadmin'] ?? [], ['exam-vouchers.manage']);
        service('settings')->setMany(['AuthGroups.permissions' => $permissions, 'AuthGroups.matrix' => $matrix]);
    }

    public function down(): void
    {
    }
}
