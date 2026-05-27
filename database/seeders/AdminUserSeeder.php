<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::updateOrCreate(
            ['email' => 'admin@greenwell.com'],
            [
                'name'      => 'Admin',
                'phone'     => '0700000001',
                'password'  => 'Admin@1234',
                'is_active' => true,
            ]
        );

        $admin->syncRoles([$adminRole]);
    }
}
