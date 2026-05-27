<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);

        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            // Customers
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            // Devices
            'devices.view',
            'devices.create',
            'devices.edit',
            'devices.control',
            // Payments
            'payments.view',
            'payments.process',
            // Deposits
            'deposits.view',
            'deposits.process',
            'deposit_configs.manage',
            // Rate plans
            'rate_plans.view',
            'rate_plans.manage',
            // Reports / dashboard
            'reports.view',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Give admin role all permissions
        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->syncPermissions($permissions);
        }

        // Give agent role a sensible subset
        $agent = Role::where('name', 'agent')->where('guard_name', 'web')->first();
        if ($agent) {
            $agent->syncPermissions([
                'customers.view', 'customers.create', 'customers.edit',
                'devices.view', 'devices.control',
                'payments.view', 'payments.process',
                'deposits.view', 'deposits.process',
                'rate_plans.view',
                'reports.view',
            ]);
        }
    }
}
