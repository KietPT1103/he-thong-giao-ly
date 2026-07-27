<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = ['manage-system-settings', 'view-activity-logs', 'manage-users', 'manage-roles', 'manage-permissions', 'view-academic-years', 'create-academic-years', 'update-academic-years', 'delete-academic-years', 'view-levels', 'create-levels', 'update-levels', 'delete-levels', 'view-classes', 'create-classes', 'update-classes', 'delete-classes', 'assign-teachers', 'enroll-children', 'view-children', 'create-children', 'update-children', 'delete-children', 'view-parents', 'create-parents', 'update-parents', 'link-parent-child', 'view-attendance', 'create-attendance-session', 'take-attendance', 'update-attendance', 'view-attendance-reports', 'create-leave-request', 'view-leave-requests', 'approve-leave-request', 'reject-leave-request', 'view-notifications', 'send-notifications', 'manage-announcements'];
        foreach ($permissions as $name) Permission::findOrCreate($name, 'web');
        $all = Permission::all();
        foreach (['admin', 'teacher', 'parent', 'child'] as $name) Role::findOrCreate($name, 'web');
        Role::findByName('admin')->syncPermissions($all);
        Role::findByName('teacher')->syncPermissions(['view-classes', 'view-children', 'view-attendance', 'create-attendance-session', 'take-attendance', 'update-attendance', 'view-leave-requests', 'approve-leave-request', 'reject-leave-request', 'view-notifications', 'send-notifications']);
        Role::findByName('parent')->syncPermissions(['create-leave-request', 'view-leave-requests', 'view-notifications']);
        Role::findByName('child')->syncPermissions(['view-notifications']);
    }
}
