<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $this->grantIfRoleExists('admin', self::PERMISSIONS);
        $this->grantIfRoleExists('teacher', ['scan-attendance-qr']);
        $this->grantIfRoleExists('parent', ['view-child-qr']);
        $this->grantIfRoleExists('child', ['view-child-qr']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::PERMISSIONS)
            ->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grantIfRoleExists(string $roleName, array $permissions): void
    {
        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->first();

        $role?->givePermissionTo($permissions);
    }
};
