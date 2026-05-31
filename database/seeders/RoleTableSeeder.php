<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // get all permissions data where name like users
        $user_permissions = Permission::where('name', 'like', '%users%')->get();

        // create or get role
        $user_group = Role::firstOrCreate([
            'name' => 'users-access',
            'guard_name' => 'web',
        ]);

        // assign permissions to access role
        $user_group->givePermissionTo($user_permissions);

        // get all permissions data where name like roles
        $role_permissions = Permission::where('name', 'like', '%roles%')->get();

        // create or get role
        $role_group = Role::firstOrCreate([
            'name' => 'roles-access',
            'guard_name' => 'web',
        ]);

        // assign permissions to role
        $role_group->givePermissionTo($role_permissions);

        //  get all permissions data where name like permissions
        $permission_permissions = Permission::where('name', 'like', '%permissions%')->get();

        // create or get role
        $permission_group = Role::firstOrCreate([
            'name' => 'permission-access',
            'guard_name' => 'web',
        ]);

        // assign permissions to role
        $permission_group->givePermissionTo($permission_permissions);

        // create or get role
        Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);

        // create or get company admin role
        $companyAdmin = Role::firstOrCreate([
            'name' => 'company-admin',
            'guard_name' => 'web',
        ]);
        $companyAdmin->givePermissionTo(['dashboard-access', 'company-admin-access']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
