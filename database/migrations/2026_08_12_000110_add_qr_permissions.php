<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view-child-qr',
        'scan-attendance-qr',
        'rotate-child-qr',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $permissionsTable = $tableNames['permissions'];
        $rolesTable = $tableNames['roles'];
        $rolePermissionsTable = $tableNames['role_has_permissions'];
        $permissionPivot = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $rolePivot = $columnNames['role_pivot_key'] ?? 'role_id';
        $timestamp = now();

        DB::table($permissionsTable)->insertOrIgnore(array_map(
            static fn (string $name): array => [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            self::PERMISSIONS,
        ));

        $permissionIds = DB::table($permissionsTable)
            ->where('guard_name', 'web')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id', 'name');
        $roleIds = DB::table($rolesTable)
            ->where('guard_name', 'web')
            ->whereIn('name', ['admin', 'teacher', 'parent', 'child'])
            ->pluck('id', 'name');

        $grants = [
            'admin' => self::PERMISSIONS,
            'teacher' => ['scan-attendance-qr'],
            'parent' => ['view-child-qr'],
            'child' => ['view-child-qr'],
        ];
        $rows = [];

        foreach ($grants as $roleName => $permissions) {
            if (! isset($roleIds[$roleName])) {
                continue;
            }

            foreach ($permissions as $permissionName) {
                if (isset($permissionIds[$permissionName])) {
                    $rows[] = [
                        $permissionPivot => $permissionIds[$permissionName],
                        $rolePivot => $roleIds[$roleName],
                    ];
                }
            }
        }

        if ($rows !== []) {
            DB::table($rolePermissionsTable)->insertOrIgnore($rows);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table(config('permission.table_names.permissions'))
            ->where('guard_name', 'web')
            ->whereIn('name', self::PERMISSIONS)
            ->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
