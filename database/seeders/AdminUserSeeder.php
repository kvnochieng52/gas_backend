<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@greenwell.com'],
            [
                'name'      => 'Admin',
                'phone'     => '0700000001',
                'password'  => bcrypt('password'),
                'is_active' => true,
            ]
        );

        $admin->assignRole($adminRole);
    }
}
