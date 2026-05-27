<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerMobileController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'pin'   => 'required|string|min:4|max:6',
        ]);

        $phone = preg_replace('/[\s\-]/', '', $request->phone);

        $customer = Customer::where('phone', $phone)
            ->orWhere('phone', ltrim($phone, '+'))
            ->orWhere('phone', '0' . substr($phone, -9))
            ->first();

        if (! $customer || ! $customer->verifyPin($request->pin)) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid phone number or PIN.'],
            ]);
        }

        if (! $customer->is_active) {
            return response()->json(['message' => 'Your account has been deactivated. Contact your agent.'], 403);
        }

        // Revoke old mobile tokens then issue a fresh one
        $customer->tokens()->where('name', 'mobile')->delete();
        $token = $customer->createToken('mobile', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'token'    => $token,
            'customer' => $this->formatProfile($customer),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user('sanctum')->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function profile(Request $request)
    {
        $customer = $this->resolveCustomer($request);
        return response()->json(['data' => $this->formatProfile($customer)]);
    }

    public function transactions(Request $request)
    {
        $customer = $this->resolveCustomer($request);

        $txns = $customer->transactions()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($t) => [
                'id'               => $t->id,
                'amount'           => (float) $t->amount,
                'credit_added'     => (float) $t->credit_added,
                'payment_method'   => $t->payment_method,
                'mpesa_receipt_no' => $t->mpesa_receipt_no,
                'status'           => $t->status,
                'notes'            => $t->notes,
                'created_at'       => $t->created_at,
            ]);

        // Also include recent deductions from credit_ledger
        $deductions = \Illuminate\Support\Facades\DB::table('credit_ledger')
            ->where('customer_id', $customer->id)
            ->where('type', 'DEDUCTION')
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->map(fn($l) => [
                'id'               => 'L' . $l->id,
                'amount'           => (float) $l->amount,
                'credit_added'     => -(float) $l->amount,
                'payment_method'   => 'DEDUCTION',
                'mpesa_receipt_no' => null,
                'status'           => 'COMPLETED',
                'notes'            => $l->description,
                'created_at'       => $l->created_at,
                'is_deduction'     => true,
            ]);

        $all = $txns->concat($deductions)
            ->sortByDesc('created_at')
            ->values();

        return response()->json(['data' => $all]);
    }

    public function topup(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:50|max:70000',
            'phone'  => 'required|string',
        ]);

        $customer = $this->resolveCustomer($request);
        $phone    = preg_replace('/[\s\-]/', '', $request->phone);

        try {
            $mpesa = app(MpesaService::class);
            $result = $mpesa->initiateSTKPush(
                phone: $phone,
                amount: (int) $request->amount,
                accountReference: $customer->account_no ?? "GP-{$customer->id}",
                description: 'GasPay top-up',
                callbackUrl: config('app.url') . '/api/payments/mpesa/callback',
            );

            return response()->json([
                'success'              => true,
                'message'              => "STK Push sent to {$phone}. Enter your M-Pesa PIN.",
                'checkout_request_id'  => $result['CheckoutRequestID'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Top-up failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function setPin(Request $request)
    {
        $request->validate([
            'phone'       => 'required|string',
            'account_no'  => 'required|string',
            'pin'         => 'required|string|min:4|max:6|confirmed',
        ]);

        $customer = Customer::where('phone', $request->phone)
            ->where('account_no', $request->account_no)
            ->first();

        if (! $customer) {
            return response()->json(['message' => 'Customer not found. Check phone and account number.'], 404);
        }

        $customer->update(['pin' => $request->pin]);

        return response()->json(['message' => 'PIN set successfully. You can now log in.']);
    }

    // -------------------------------------------------------------------------
    private function resolveCustomer(Request $request): Customer
    {
        $tokenable = $request->user('sanctum');

        if ($tokenable instanceof Customer) {
            return $tokenable->load([
                'agent:id,name',
                'ratePlan:id,name,amount,unit',
                'devices' => fn($q) => $q->latest()->limit(1),
            ]);
        }

        abort(401, 'Unauthenticated.');
    }

    private function formatProfile(Customer $customer): array
    {
        $device = $customer->relationLoaded('devices')
            ? $customer->devices->first()
            : $customer->devices()->latest()->first();

        return [
            'id'             => $customer->id,
            'account_no'     => $customer->account_no,
            'name'           => $customer->name,
            'phone'          => $customer->phone,
            'email'          => $customer->email,
            'address'        => $customer->address,
            'credit_balance' => (float) $customer->credit_balance,
            'is_active'      => $customer->is_active,
            'agent'          => $customer->agent
                ? ['id' => $customer->agent->id, 'name' => $customer->agent->name]
                : null,
            'rate_plan'      => $customer->ratePlan
                ? [
                    'id'     => $customer->ratePlan->id,
                    'name'   => $customer->ratePlan->name,
                    'amount' => (float) $customer->ratePlan->amount,
                    'unit'   => (float) $customer->ratePlan->unit,
                ]
                : null,
            'devices'        => $device ? [[
                'id'               => $device->id,
                'serial_number'    => $device->serial_number,
                'gas_level_pct'    => (float) $device->gas_level_pct,
                'cylinder_size_kg' => (float) $device->cylinder_size_kg,
                'valve_open'       => (bool) $device->valve_open,
                'status'           => $device->status,
                'firmware_version' => $device->firmware_version,
            ]] : [],
        ];
    }
}
