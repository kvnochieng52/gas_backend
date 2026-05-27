<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $agentRole = Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@gasplatform.com'],
            [
                'name' => 'System Admin',
                'phone' => '0700000000',
                'password' => bcrypt('Admin@1234'),
                'is_active' => true,
            ]
        );
        $admin->assignRole($adminRole);

        $agent = User::firstOrCreate(
            ['email' => 'agent@gasplatform.com'],
            [
                'name' => 'Field Agent',
                'phone' => '0711111111',
                'password' => bcrypt('Agent@1234'),
                'is_active' => true,
            ]
        );
        $agent->assignRole($agentRole);

        $settings = [
            ['key' => 'gas_price_per_kg', 'value' => '200', 'description' => 'Gas price per kilogram in KES'],
            ['key' => 'low_gas_threshold_pct', 'value' => '20', 'description' => 'Gas level % that triggers LOW_GAS alert'],
            ['key' => 'min_credit_balance', 'value' => '100', 'description' => 'Minimum credit balance in KES to keep valve open'],
            ['key' => 'device_offline_minutes', 'value' => '5', 'description' => 'Minutes without MQTT message before device is OFFLINE'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }

        $this->call([
            PermissionSeeder::class,
            AdminUserSeeder::class,
            //SampleDataSeeder::class,
        ]);
    }
}
