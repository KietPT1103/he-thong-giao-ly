<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view-assignments', 'create-assignments', 'update-assignments',
        'archive-assignments', 'grade-assignments', 'submit-assignments',
        'view-assignment-reports',
    ];

    private const GRANTS = [
        'admin' => self::PERMISSIONS,
        'teacher' => [
            'view-assignments', 'create-assignments', 'update-assignments',
            'archive-assignments', 'grade-assignments', 'view-assignment-reports',
        ],
        'child' => ['view-assignments', 'submit-assignments'],
    ];

    public function up(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $now = now();
        foreach (self::PERMISSIONS as $permission) {
            DB::table($tables['permissions'])->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)
            ->pluck('id', 'name');
        $roleIds = DB::table($tables['roles'])
            ->where('guard_name', 'web')->whereIn('name', array_keys(self::GRANTS))
            ->pluck('id', 'name');
        $permissionKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $roleKey = $columns['role_pivot_key'] ?? 'role_id';
        $rows = [];
        foreach (self::GRANTS as $role => $permissions) {
            foreach ($permissions as $permission) {
                if (isset($roleIds[$role], $permissionIds[$permission])) {
                    $rows[] = [$permissionKey => $permissionIds[$permission], $roleKey => $roleIds[$role]];
                }
            }
        }
        if ($rows !== []) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))
            ->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->delete();
    }
};
