<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // get admin role
        $role = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);

        // create new admin when it does not exist yet
        $user = User::firstOrCreate(
            ['email' => 'raf@dev.com'],
            [
                'name' => 'Rafi Taufiqurrahman',
                'password' => bcrypt('password'),
            ]
        );

        // assign role to user
        $user->assignRole($role);
    }
}
