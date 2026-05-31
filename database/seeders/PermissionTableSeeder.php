<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // dashboard permissions
            'dashboard-access',
            'company-admin-access',

            // users permissions
            'users-access',
            'users-data',
            'users-create',
            'users-update',
            'users-delete',

            // roles permissions
            'roles-access',
            'roles-data',
            'roles-create',
            'roles-update',
            'roles-delete',

            // permissions permissions
            'permissions-access',
            'permissions-data',
            'permissions-create',
            'permissions-update',
            'permissions-delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
