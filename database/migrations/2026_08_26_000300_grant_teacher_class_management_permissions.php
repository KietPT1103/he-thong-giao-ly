<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    private const PERMISSIONS = ['create-classes', 'update-classes', 'delete-classes'];

    public function up(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $roleId = DB::table($tables['roles'])
            ->where('guard_name', 'web')
            ->where('name', 'teacher')
            ->value('id');
        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');
        $permissionPivot = $columns['permission_pivot_key'] ?? 'permission_id';
        $rolePivot = $columns['role_pivot_key'] ?? 'role_id';

        DB::table($tables['role_has_permissions'])->insertOrIgnore(
            $permissionIds->map(fn ($permissionId) => [
                $permissionPivot => $permissionId,
                $rolePivot => $roleId,
            ])->all(),
        );
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $roleId = DB::table($tables['roles'])
            ->where('guard_name', 'web')
            ->where('name', 'teacher')
            ->value('id');
        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        DB::table($tables['role_has_permissions'])
            ->where($columns['role_pivot_key'] ?? 'role_id', $roleId)
            ->whereIn($columns['permission_pivot_key'] ?? 'permission_id', $permissionIds)
            ->delete();
    }
};
