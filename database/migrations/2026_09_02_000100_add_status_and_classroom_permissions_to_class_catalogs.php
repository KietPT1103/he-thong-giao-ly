<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const CLASSROOM_PERMISSIONS = [
        'view-classrooms',
        'create-classrooms',
        'update-classrooms',
        'delete-classrooms',
    ];

    public function up(): void
    {
        foreach (['academic_years', 'catechism_levels', 'classrooms'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->boolean('is_active')->default(true)->index();
            });
        }

        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $now = now();

        foreach (self::CLASSROOM_PERMISSIONS as $permission) {
            DB::table($tables['permissions'])->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $adminRoleId = DB::table($tables['roles'])
            ->where('guard_name', 'web')
            ->where('name', 'admin')
            ->value('id');

        if ($adminRoleId) {
            $permissionKey = $columns['permission_pivot_key'] ?? 'permission_id';
            $roleKey = $columns['role_pivot_key'] ?? 'role_id';
            $permissionIds = DB::table($tables['permissions'])
                ->where('guard_name', 'web')
                ->whereIn('name', self::CLASSROOM_PERMISSIONS)
                ->pluck('id');

            DB::table($tables['role_has_permissions'])->insertOrIgnore(
                $permissionIds->map(fn ($permissionId) => [
                    $permissionKey => $permissionId,
                    $roleKey => $adminRoleId,
                ])->all(),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))
            ->where('guard_name', 'web')
            ->whereIn('name', self::CLASSROOM_PERMISSIONS)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['academic_years', 'catechism_levels', 'classrooms'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('is_active');
            });
        }
    }
};
