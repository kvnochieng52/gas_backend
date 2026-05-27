<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Device;
use App\Models\DepositConfiguration;
use App\Models\RatePlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    private array $agents = [];
    private array $customers = [];
    private array $devices = [];
    private array $ratePlans = [];
    private array $depositConfigs = [];

    public function run(): void
    {
        $this->seedRatePlans();
        $this->seedDepositConfigurations();
        $this->seedAgents();
        $this->seedCustomers();
        $this->seedDevices();
        $this->seedTransactionsAndLedger();
        $this->seedDeviceReadings();
        $this->seedCustomerDeposits();
        $this->seedDeviceTamperEvents();
    }

    // -------------------------------------------------------------------------
    private function seedRatePlans(): void
    {
        $adminId = User::where('email', 'admin@gasplatform.com')->value('id');

        $plans = [
            [
                'name'        => 'Economy',
                'amount'      => 50.00,
                'unit'        => 0.50000000,
                'description' => 'Budget plan: KES 50 for every 0.5 kg (KES 100/kg)',
                'is_active'   => false,
                'created_by'  => $adminId,
            ],
            [
                'name'        => 'Standard',
                'amount'      => 100.00,
                'unit'        => 1.00000000,
                'description' => 'Standard plan: KES 100 per 1 kg',
                'is_active'   => true,
                'created_by'  => $adminId,
            ],
            [
                'name'        => 'Premium',
                'amount'      => 200.00,
                'unit'        => 2.50000000,
                'description' => 'Premium bulk plan: KES 200 for 2.5 kg (KES 80/kg)',
                'is_active'   => true,
                'created_by'  => $adminId,
            ],
        ];

        foreach ($plans as $plan) {
            $rp = RatePlan::firstOrCreate(['name' => $plan['name']], $plan);
            $this->ratePlans[$plan['name']] = $rp->id;
        }
    }

    // -------------------------------------------------------------------------
    private function seedDepositConfigurations(): void
    {
        $adminId = User::where('email', 'admin@gasplatform.com')->value('id');

        $configs = [
            [
                'name'        => 'Small Cylinder (6 kg)',
                'amount'      => 2000.00,
                'description' => 'Refundable deposit for 6 kg cylinder',
                'is_active'   => false,
                'created_by'  => $adminId,
            ],
            [
                'name'        => 'Medium Cylinder (13 kg)',
                'amount'      => 4000.00,
                'description' => 'Refundable deposit for 13 kg cylinder',
                'is_active'   => true,
                'created_by'  => $adminId,
            ],
            [
                'name'        => 'Large Cylinder (22.5 kg)',
                'amount'      => 6500.00,
                'description' => 'Refundable deposit for 22.5 kg commercial cylinder',
                'is_active'   => true,
                'created_by'  => $adminId,
            ],
        ];

        foreach ($configs as $config) {
            $dc = DepositConfiguration::firstOrCreate(['name' => $config['name']], $config);
            $this->depositConfigs[$config['name']] = $dc->id;
        }
    }

    // -------------------------------------------------------------------------
    private function seedAgents(): void
    {
        $agentData = [
            [
                'name'      => 'Jane Muthoni',
                'email'     => 'jane.muthoni@gasplatform.com',
                'phone'     => '0722334455',
                'password'  => Hash::make('Agent@1234'),
                'is_active' => true,
            ],
            [
                'name'      => 'Patrick Otieno',
                'email'     => 'patrick.otieno@gasplatform.com',
                'phone'     => '0733445566',
                'password'  => Hash::make('Agent@1234'),
                'is_active' => true,
            ],
        ];

        foreach ($agentData as $data) {
            $agent = User::firstOrCreate(['email' => $data['email']], $data);
            if (!$agent->hasRole('agent')) {
                $agent->assignRole('agent');
            }
        }

        // Collect all agent IDs (including the seeded default)
        $this->agents = User::whereHas('roles', fn($q) => $q->where('name', 'agent'))
            ->pluck('id')
            ->toArray();
    }

    // -------------------------------------------------------------------------
    private function seedCustomers(): void
    {
        $kenyans = [
            ['name' => 'Alice Wanjiru Kamau',   'phone' => '0701111001', 'address' => 'Nairobi, Westlands'],
            ['name' => 'Brian Kipchoge Rono',    'phone' => '0701111002', 'address' => 'Eldoret, Uganda Road'],
            ['name' => 'Caroline Achieng Ouma',  'phone' => '0701111003', 'address' => 'Kisumu, Milimani'],
            ['name' => 'David Muriuki Njoroge',  'phone' => '0701111004', 'address' => 'Nairobi, Kasarani'],
            ['name' => 'Esther Nekesa Wafula',   'phone' => '0701111005', 'address' => 'Kakamega, Town'],
            ['name' => 'Francis Mwangi Gicheru', 'phone' => '0701111006', 'address' => 'Nairobi, Embakasi'],
            ['name' => 'Grace Atieno Owino',     'phone' => '0701111007', 'address' => 'Kisumu, Kondele'],
            ['name' => 'Henry Njuguna Waweru',   'phone' => '0701111008', 'address' => 'Nairobi, Langata'],
            ['name' => 'Irene Wambui Githinji',  'phone' => '0701111009', 'address' => 'Thika, Makongeni'],
            ['name' => 'James Odhiambo Aloo',    'phone' => '0701111010', 'address' => 'Nairobi, Eastleigh'],
            ['name' => 'Kathleen Chebet Kirui',  'phone' => '0701111011', 'address' => 'Kericho, Tea Zone'],
            ['name' => 'Leonard Maina Karanja',  'phone' => '0701111012', 'address' => 'Nairobi, Roysambu'],
            ['name' => 'Mary Njeri Githui',      'phone' => '0701111013', 'address' => 'Kiambu, Ruiru'],
            ['name' => 'Nelson Omondi Siaya',    'phone' => '0701111014', 'address' => 'Siaya, Township'],
            ['name' => 'Olive Cherop Sang',      'phone' => '0701111015', 'address' => 'Nandi, Kapsabet'],
            ['name' => 'Peter Kamau Ndungu',     'phone' => '0701111016', 'address' => 'Nairobi, Karen'],
            ['name' => 'Rachel Wanjiku Mwai',    'phone' => '0701111017', 'address' => 'Muranga, Town'],
            ['name' => 'Samuel Korir Bett',      'phone' => '0701111018', 'address' => 'Nakuru, Milimani'],
        ];

        $agentCount    = count($this->agents);
        $standardPlan  = $this->ratePlans['Standard'];
        $premiumPlan   = $this->ratePlans['Premium'];

        foreach ($kenyans as $i => $data) {
            $accountNo  = sprintf('GP-%03d', $i + 1);
            $agentId    = $this->agents[$i % $agentCount];
            $ratePlanId = ($i % 3 === 2) ? $premiumPlan : $standardPlan;
            $balance    = [0, 50, 150, 300, 500, 800, 1200, 200, 75, 400,
                           0, 600, 250, 900, 100, 350, 700, 450][$i] ?? 200;

            $customer = Customer::firstOrCreate(
                ['phone' => $data['phone']],
                [
                    'account_no'   => $accountNo,
                    'name'         => $data['name'],
                    'address'      => $data['address'],
                    'agent_id'     => $agentId,
                    'rate_plan_id' => $ratePlanId,
                    'credit_balance' => $balance,
                    'is_active'    => true,
                    'pin'          => '1234',
                ]
            );

            $this->customers[] = $customer->id;
        }
    }

    // -------------------------------------------------------------------------
    private function seedDevices(): void
    {
        $deviceData = [
            // serial, imei, customer_index, gas_pct, size_kg, valve_open, status, lat, lng
            ['GP-DEV-001', '353456789012341', 0,  82.5, 13.0, true,  'ONLINE',  -1.2864, 36.8172],
            ['GP-DEV-002', '353456789012342', 1,  67.0, 13.0, true,  'ONLINE',  0.5143,  35.2698],
            ['GP-DEV-003', '353456789012343', 2,  45.3, 6.0,  true,  'ONLINE',  -0.1022, 34.7617],
            ['GP-DEV-004', '353456789012344', 3,  15.2, 13.0, false, 'LOW_GAS', -1.3033, 36.7073],
            ['GP-DEV-005', '353456789012345', 4,  91.8, 22.5, true,  'ONLINE',  0.2827,  34.7519],
            ['GP-DEV-006', '353456789012346', 5,   8.7, 13.0, false, 'LOW_GAS', -1.2921, 36.8219],
            ['GP-DEV-007', '353456789012347', 6,  55.0, 6.0,  true,  'ONLINE',  -0.0917, 34.7680],
            ['GP-DEV-008', '353456789012348', 7,  34.1, 13.0, true,  'ONLINE',  -1.3100, 36.8050],
            ['GP-DEV-009', '353456789012349', 8,  18.9, 22.5, false, 'LOW_GAS', -1.0556, 37.0660],
            ['GP-DEV-010', '353456789012350', 9,  73.4, 13.0, true,  'ONLINE',  -1.2864, 36.8172],
            ['GP-DEV-011', '353456789012351', 10,  5.1, 6.0,  false, 'FAULT',   -0.0917, 34.7680],
            ['GP-DEV-012', '353456789012352', 11, 88.0, 22.5, true,  'ONLINE',  -1.2921, 36.8219],
            ['GP-DEV-013', '353456789012353', 12, 12.3, 13.0, false, 'LOW_GAS', -1.3033, 36.7073],
            ['GP-DEV-014', null,              null,0.0, 13.0, false, 'OFFLINE', null,    null],
        ];

        foreach ($deviceData as [$serial, $imei, $custIdx, $gasPct, $size, $valveOpen, $status, $lat, $lng]) {
            $customerId = $custIdx !== null ? $this->customers[$custIdx] : null;
            $lastSeen   = $status !== 'OFFLINE'
                ? now()->subMinutes(rand(1, 60))
                : now()->subHours(rand(6, 72));

            $device = Device::firstOrCreate(
                ['serial_number' => $serial],
                [
                    'imei'             => $imei,
                    'customer_id'      => $customerId,
                    'gas_level_pct'    => $gasPct,
                    'cylinder_size_kg' => $size,
                    'valve_open'       => $valveOpen,
                    'last_seen'        => $lastSeen,
                    'mqtt_topic'       => 'devices/' . strtolower(str_replace('-', '/', $serial)),
                    'latitude'         => $lat,
                    'longitude'        => $lng,
                    'status'           => $status,
                    'firmware_version' => 'v2.' . rand(1, 4) . '.' . rand(0, 9),
                    'is_tampered'      => false,
                ]
            );

            $this->devices[] = $device->id;
        }
    }

    // -------------------------------------------------------------------------
    private function seedTransactionsAndLedger(): void
    {
        // We'll directly manipulate credit balance tracking per customer
        $balanceMap = DB::table('customers')
            ->whereIn('id', $this->customers)
            ->pluck('credit_balance', 'id')
            ->toArray();

        // Reset all balances to 0 for seeding purposes — ledger will build them back up
        DB::table('customers')->whereIn('id', $this->customers)->update(['credit_balance' => 0]);
        foreach ($this->customers as $cid) {
            $balanceMap[$cid] = 0.0;
        }

        $mpesaReceipts = [
            'LGK100234', 'QMN839201', 'PLK293847', 'ZXC847291', 'TYU928374',
            'WER102938', 'IOP374829', 'ASD908172', 'FGH293018', 'JKL019283',
            'BNM928374', 'CVB018273', 'XZA827364', 'SDF726354', 'GHJ635243',
            'KLM524132', 'POI413021', 'UYT301921', 'RET190812', 'EWQ079703',
            'QWE968594', 'AZS857483', 'XDC746372', 'WSX635261', 'EDC524150',
        ];

        $agentIds   = $this->agents;
        $customerIds = $this->customers;
        $receiptIdx = 0;
        $txns       = [];

        // Generate 40 transactions spread over 90 days
        for ($i = 0; $i < 40; $i++) {
            $daysAgo    = rand(1, 90);
            $createdAt  = now()->subDays($daysAgo)->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            $customerId = $customerIds[array_rand($customerIds)];
            $agentId    = $agentIds[array_rand($agentIds)];
            $method     = ($i % 3 === 0) ? 'CASH' : 'MPESA';
            $amount     = [100, 200, 300, 500, 800, 1000, 1500, 2000][rand(0, 7)];
            $creditAdded = $amount; // 1:1 for simplicity

            $receiptNo = null;
            if ($method === 'MPESA' && $receiptIdx < count($mpesaReceipts)) {
                $receiptNo = $mpesaReceipts[$receiptIdx++];
            }

            $txnId = DB::table('transactions')->insertGetId([
                'customer_id'    => $customerId,
                'agent_id'       => $agentId,
                'amount'         => $amount,
                'credit_added'   => $creditAdded,
                'payment_method' => $method,
                'mpesa_receipt_no' => $receiptNo,
                'status'         => 'COMPLETED',
                'created_at'     => $createdAt,
                'updated_at'     => $createdAt,
            ]);

            $txns[] = ['id' => $txnId, 'customer_id' => $customerId, 'amount' => $creditAdded, 'at' => $createdAt];

            // TOP_UP ledger entry
            $before = $balanceMap[$customerId] ?? 0.0;
            $after  = $before + $creditAdded;
            DB::table('credit_ledger')->insert([
                'customer_id'    => $customerId,
                'transaction_id' => $txnId,
                'type'           => 'TOP_UP',
                'amount'         => $creditAdded,
                'balance_before' => $before,
                'balance_after'  => $after,
                'description'    => "Top-up via {$method}" . ($receiptNo ? " ({$receiptNo})" : ''),
                'created_at'     => $createdAt,
            ]);
            $balanceMap[$customerId] = $after;
        }

        // Generate periodic DEDUCTION entries (gas usage) for customers with devices
        $deviceCustomerIds = DB::table('devices')
            ->whereNotNull('customer_id')
            ->whereIn('customer_id', $customerIds)
            ->pluck('customer_id', 'id')
            ->toArray(); // device_id => customer_id

        foreach ($deviceCustomerIds as $deviceId => $customerId) {
            $deductionCount = rand(3, 8);
            for ($d = 0; $d < $deductionCount; $d++) {
                $daysAgo   = rand(1, 80);
                $createdAt = now()->subDays($daysAgo)->subHours(rand(0, 23));
                $deduction = round(rand(5, 80) + rand(0, 99) / 100, 2);

                $before = $balanceMap[$customerId] ?? 0.0;
                if ($before <= 0) continue;
                $actual = min($deduction, $before);
                $after  = max(0, $before - $actual);

                DB::table('credit_ledger')->insert([
                    'customer_id'    => $customerId,
                    'device_id'      => $deviceId,
                    'type'           => 'DEDUCTION',
                    'amount'         => $actual,
                    'balance_before' => $before,
                    'balance_after'  => $after,
                    'description'    => 'Gas consumption deduction',
                    'created_at'     => $createdAt,
                ]);
                $balanceMap[$customerId] = $after;
            }
        }

        // Sync final credit balances to customers table
        foreach ($balanceMap as $cid => $balance) {
            DB::table('customers')->where('id', $cid)->update(['credit_balance' => max(0, round($balance, 2))]);
        }
    }

    // -------------------------------------------------------------------------
    private function seedDeviceReadings(): void
    {
        $deviceIds = $this->devices;

        foreach ($deviceIds as $deviceId) {
            $device = DB::table('devices')->where('id', $deviceId)->first();
            if (!$device) continue;

            $currentLevel = (float) $device->gas_level_pct;
            $size         = (float) $device->cylinder_size_kg;

            // 20 readings over the last 7 days, each ~8 hours apart
            $readings = [];
            for ($i = 20; $i >= 1; $i--) {
                $hoursAgo = $i * 8 + rand(-2, 2);
                $drift    = rand(1, 5) / 10; // gas drop per reading
                $pct      = min(100, max(0, $currentLevel + ($i * $drift)));

                $readings[] = [
                    'device_id'       => $deviceId,
                    'gas_level_pct'   => round($pct, 2),
                    'weight_kg'       => round($size * $pct / 100, 3),
                    'temperature'     => round(20 + rand(0, 10) + rand(0, 99) / 100, 1),
                    'battery_voltage' => round(3.5 + rand(0, 7) / 10, 2),
                    'rssi'            => -1 * rand(50, 90),
                    'created_at'      => now()->subHours($hoursAgo),
                ];
            }

            DB::table('device_readings')->insert($readings);
        }
    }

    // -------------------------------------------------------------------------
    private function seedCustomerDeposits(): void
    {
        $mediumConfigId = $this->depositConfigs['Medium Cylinder (13 kg)']   ?? null;
        $largeConfigId  = $this->depositConfigs['Large Cylinder (22.5 kg)']  ?? null;
        $agentId        = $this->agents[0];

        $deposits = [
            // customer_index, config, amount_required, amount_paid, method, receipt, status
            [0,  $mediumConfigId, 4000, 4000, 'MPESA',  'DEP100001', 'COMPLETED'],
            [1,  $mediumConfigId, 4000, 4000, 'CASH',   null,        'COMPLETED'],
            [2,  $mediumConfigId, 4000, 4000, 'MPESA',  'DEP100002', 'COMPLETED'],
            [3,  $mediumConfigId, 4000, 0,    'MPESA',  null,        'PENDING'],
            [4,  $largeConfigId,  6500, 6500, 'MPESA',  'DEP100003', 'COMPLETED'],
            [5,  $mediumConfigId, 4000, 4000, 'CASH',   null,        'COMPLETED'],
            [6,  $mediumConfigId, 4000, 4000, 'MPESA',  'DEP100004', 'COMPLETED'],
            [7,  $largeConfigId,  6500, 0,    'MPESA',  null,        'PENDING'],
            [8,  $mediumConfigId, 4000, 4000, 'MPESA',  'DEP100005', 'COMPLETED'],
            [9,  $largeConfigId,  6500, 6500, 'CASH',   null,        'COMPLETED'],
            [10, $mediumConfigId, 4000, 0,    'MPESA',  null,        'PENDING'],
            [11, $largeConfigId,  6500, 6500, 'MPESA',  'DEP100006', 'COMPLETED'],
            [12, $mediumConfigId, 4000, 4000, 'MPESA',  'DEP100007', 'COMPLETED'],
            [13, $mediumConfigId, 4000, 0,    'CASH',   null,        'PENDING'],
        ];

        foreach ($deposits as [$custIdx, $configId, $amtReq, $amtPaid, $method, $receipt, $status]) {
            if (!isset($this->customers[$custIdx])) continue;
            $customerId = $this->customers[$custIdx];

            $exists = DB::table('customer_deposits')
                ->where('customer_id', $customerId)
                ->where('deposit_config_id', $configId)
                ->exists();

            if (!$exists) {
                DB::table('customer_deposits')->insert([
                    'customer_id'       => $customerId,
                    'deposit_config_id' => $configId,
                    'amount_required'   => $amtReq,
                    'amount_paid'       => $amtPaid,
                    'payment_method'    => $method,
                    'mpesa_receipt_no'  => $receipt,
                    'status'            => $status,
                    'collected_by'      => $status === 'COMPLETED' ? $agentId : null,
                    'created_at'        => now()->subDays(rand(5, 60)),
                    'updated_at'        => now()->subDays(rand(1, 4)),
                ]);
            }
        }
    }

    // -------------------------------------------------------------------------
    private function seedDeviceTamperEvents(): void
    {
        // Flag 3 devices as tampered and seed corresponding events
        $tamperedDeviceData = [
            // device_index, event_type, gas_pct, description, resolved
            [5,  'GAS_DROP',       8.7,  'Sudden gas level drop from 45% to 8% in 2 hours', false],
            [10, 'PHYSICAL_TAMPER', 5.1, 'Accelerometer detected device movement/opening', false],
            [12, 'VALVE_MISMATCH', 12.3, 'Valve command sent but feedback signal mismatch', false],
            [3,  'GAS_DROP',       15.2, 'Unexplained gas drop; customer reported no usage', true],
        ];

        $adminId = User::where('email', 'admin@gasplatform.com')->value('id');

        foreach ($tamperedDeviceData as [$devIdx, $eventType, $gasPct, $desc, $resolved]) {
            if (!isset($this->devices[$devIdx])) continue;

            $deviceId   = $this->devices[$devIdx];
            $device     = DB::table('devices')->where('id', $deviceId)->first();
            $customerId = $device->customer_id ?? null;

            $createdAt  = now()->subHours(rand(2, 72));
            $resolvedAt = $resolved ? $createdAt->copy()->addHours(rand(1, 24)) : null;

            DB::table('device_tamper_events')->insertOrIgnore([
                'device_id'     => $deviceId,
                'customer_id'   => $customerId,
                'event_type'    => $eventType,
                'gas_level_pct' => $gasPct,
                'description'   => $desc,
                'resolved'      => $resolved,
                'resolved_at'   => $resolvedAt,
                'resolved_by'   => $resolved ? $adminId : null,
                'created_at'    => $createdAt,
                'updated_at'    => $resolvedAt ?? $createdAt,
            ]);

            if (!$resolved) {
                DB::table('devices')->where('id', $deviceId)->update([
                    'is_tampered'      => true,
                    'last_tampered_at' => $createdAt,
                ]);
            }
        }
    }
}
