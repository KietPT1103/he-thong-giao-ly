<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $roleId = DB::table($tables['roles'])
            ->where('guard_name', 'web')
            ->where('name', 'teacher')
            ->value('id');
        $permissionId = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->where('name', 'enroll-children')
            ->value('id');

        if (! $roleId || ! $permissionId) {
            return;
        }

        DB::table($tables['role_has_permissions'])->insertOrIgnore([
            $columns['permission_pivot_key'] ?? 'permission_id' => $permissionId,
            $columns['role_pivot_key'] ?? 'role_id' => $roleId,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $roleId = DB::table($tables['roles'])
            ->where('guard_name', 'web')
            ->where('name', 'teacher')
            ->value('id');
        $permissionId = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->where('name', 'enroll-children')
            ->value('id');

        DB::table($tables['role_has_permissions'])
            ->where($columns['role_pivot_key'] ?? 'role_id', $roleId)
            ->where($columns['permission_pivot_key'] ?? 'permission_id', $permissionId)
            ->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
