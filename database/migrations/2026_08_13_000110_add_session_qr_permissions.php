<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GRANTS = [
        'admin' => ['create-attendance-qr', 'check-in-attendance-qr'],
        'teacher' => ['create-attendance-qr'],
        'child' => ['check-in-attendance-qr'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $now = now();
        $names = collect(self::GRANTS)->flatten()->unique()->values();

        DB::table($tables['permissions'])->insertOrIgnore($names->map(fn (string $name) => [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->pluck('id', 'name');
        $roleIds = DB::table($tables['roles'])
            ->where('guard_name', 'web')
            ->whereIn('name', array_keys(self::GRANTS))
            ->pluck('id', 'name');
        $permissionPivot = $columns['permission_pivot_key'] ?? 'permission_id';
        $rolePivot = $columns['role_pivot_key'] ?? 'role_id';
        $rows = [];

        foreach (self::GRANTS as $role => $permissions) {
            foreach ($permissions as $permission) {
                if (isset($roleIds[$role], $permissionIds[$permission])) {
                    $rows[] = [
                        $permissionPivot => $permissionIds[$permission],
                        $rolePivot => $roleIds[$role],
                    ];
                }
            }
        }

        if ($rows !== []) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore($rows);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table(config('permission.table_names.permissions'))
            ->where('guard_name', 'web')
            ->whereIn('name', collect(self::GRANTS)->flatten()->unique())
            ->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
