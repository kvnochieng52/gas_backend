<?php

namespace App\Services;

use App\Events\CreditUpdated;
use App\Models\CreditLedger;
use App\Models\Customer;
use App\Models\Device;
use App\Models\SystemSetting;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class PaygService
{
    public function createPendingTransaction(Customer $customer, float $amount, int $agentId): Transaction
    {
        return Transaction::create([
            'customer_id' => $customer->id,
            'agent_id' => $agentId,
            'amount' => $amount,
            'credit_added' => $amount,
            'payment_method' => 'MPESA',
            'status' => 'PENDING',
        ]);
    }

    public function addCredit(Customer $customer, float $amount, int $transactionId): void
    {
        DB::transaction(function () use ($customer, $amount, $transactionId) {
            $balanceBefore = (float) $customer->credit_balance;
            $balanceAfter = $balanceBefore + $amount;

            $customer->increment('credit_balance', $amount);

            CreditLedger::create([
                'customer_id' => $customer->id,
                'transaction_id' => $transactionId,
                'type' => 'TOP_UP',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => "Top-up via M-Pesa: KES " . number_format($amount, 2),
                'created_at' => now(),
            ]);

            $minBalance = (float) SystemSetting::get('min_credit_balance', 100);
            if ($balanceBefore < $minBalance && $balanceAfter >= $minBalance) {
                $this->openCustomerValves($customer);
            }

            broadcast(new CreditUpdated($customer->fresh(), $amount, 'TOP_UP'));
        });
    }

    public function deductCredit(Device $device, float $weightUsedKg): void
    {
        $customer = $device->customer;
        if (! $customer) return;

        $pricePerKg = (float) SystemSetting::get('gas_price_per_kg', 200);
        $cost = $weightUsedKg * $pricePerKg;

        DB::transaction(function () use ($customer, $device, $cost) {
            $balanceBefore = (float) $customer->credit_balance;
            $balanceAfter = max(0, $balanceBefore - $cost);

            $customer->decrement('credit_balance', $cost);

            CreditLedger::create([
                'customer_id' => $customer->id,
                'device_id' => $device->id,
                'type' => 'DEDUCTION',
                'amount' => $cost,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => "Gas usage deduction",
                'created_at' => now(),
            ]);

            $minBalance = (float) SystemSetting::get('min_credit_balance', 100);
            if ($balanceAfter < $minBalance && $balanceBefore >= $minBalance) {
                $this->closeDeviceValve($device);
                broadcast(new CreditUpdated($customer->fresh(), -$cost, 'DEDUCTION'));
            }
        });
    }

    private function openCustomerValves(Customer $customer): void
    {
        $devices = $customer->devices()->where('valve_open', false)->get();
        foreach ($devices as $device) {
            app(MqttService::class)->publishValveCommand($device->mqtt_topic, true);
            $device->update(['valve_open' => true]);
        }
    }

    private function closeDeviceValve(Device $device): void
    {
        app(MqttService::class)->publishValveCommand($device->mqtt_topic, false);
        $device->update(['valve_open' => false]);
    }
}
