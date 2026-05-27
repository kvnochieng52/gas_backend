<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CustomerMobileController extends Controller
{
    // ── Auth ─────────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'pin'   => 'required|string|min:4|max:6',
        ]);

        $raw   = preg_replace('/[\s\-]/', '', $request->phone);
        $local = '0' . substr($raw, -9); // e.g. 0701111001

        $customer = Customer::where('phone', $raw)
            ->orWhere('phone', $local)
            ->orWhere('phone', ltrim($raw, '+'))
            ->first();

        if (! $customer || ! $customer->verifyPin($request->pin)) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid phone number or PIN.'],
            ]);
        }

        if (! $customer->is_active) {
            return response()->json(['message' => 'Account deactivated. Contact your agent.'], 403);
        }

        $customer->tokens()->where('name', 'mobile')->delete();
        $token = $customer->createToken('mobile', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'token'    => $token,
            'customer' => $this->formatProfile($customer->load([
                'agent:id,name',
                'ratePlan:id,name,amount,unit',
                'devices' => fn($q) => $q->latest()->limit(1),
            ])),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user('sanctum')->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function setPin(Request $request)
    {
        $request->validate([
            'phone'      => 'required|string',
            'account_no' => 'required|string',
            'pin'        => 'required|string|min:4|max:6|confirmed',
        ]);

        $customer = Customer::where('phone', $request->phone)
            ->where('account_no', $request->account_no)
            ->first();

        if (! $customer) {
            return response()->json(['message' => 'No customer found. Check phone and account number.'], 404);
        }

        $customer->update(['pin' => $request->pin]);
        return response()->json(['message' => 'PIN set. You can now log in.']);
    }

    // ── Profile ───────────────────────────────────────────────────────────────

    public function profile(Request $request)
    {
        $customer = $this->resolveCustomer($request);
        return response()->json(['data' => $this->formatProfile($customer)]);
    }

    // ── Transactions ──────────────────────────────────────────────────────────

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

        $deductions = DB::table('credit_ledger')
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

    // ── Top-up (M-Pesa STK Push) ──────────────────────────────────────────────

    public function topup(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:150000',
            'phone'  => 'required|string|max:20',
        ]);

        $customer = $this->resolveCustomer($request);
        $amount   = (int) ceil($data['amount']); // M-Pesa requires whole KES

        // 1. Create a PENDING transaction so the callback can look it up
        $transaction = Transaction::create([
            'customer_id'    => $customer->id,
            'agent_id'       => null,
            'amount'         => $amount,
            'credit_added'   => $amount,
            'payment_method' => 'MPESA',
            'status'         => 'PENDING',
            'notes'          => 'Self-service via mobile app',
        ]);

        // 2. Initiate STK Push
        try {
            $mpesa             = app(MpesaService::class);
            $checkoutRequestId = $mpesa->initiateSTKPush(
                phone:           $data['phone'],
                amount:          $amount,
                accountRef:      $customer->account_no ?? ('GP-' . $customer->id),
                transactionDesc: 'GasPay top-up',
            );

            $transaction->update([
                'mpesa_checkout_request_id' => $checkoutRequestId,
            ]);

            return response()->json([
                'success'             => true,
                'message'             => 'STK Push sent. Enter your M-Pesa PIN on your phone.',
                'transaction_id'      => $transaction->id,
                'checkout_request_id' => $checkoutRequestId,
            ]);
        } catch (\Throwable $e) {
            $transaction->update(['status' => 'FAILED']);
            Log::error('Mobile top-up STK push failed', [
                'customer_id' => $customer->id,
                'amount'      => $amount,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not initiate payment: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function topupStatus(Request $request, string $checkoutRequestId)
    {
        $customer    = $this->resolveCustomer($request);
        $transaction = Transaction::where('mpesa_checkout_request_id', $checkoutRequestId)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $transaction) {
            return response()->json(['status' => 'NOT_FOUND'], 404);
        }

        if ($transaction->status === 'PENDING') {
            // Optionally query Daraja directly for fresh status
            try {
                $mpesa  = app(MpesaService::class);
                $result = $mpesa->querySTKPush($checkoutRequestId);
                $code   = $result['ResultCode'] ?? null;

                if ($code === 0 || $code === '0') {
                    // Payment was received but callback may have been missed — handle it here
                    if ($transaction->status === 'PENDING') {
                        $transaction->update(['status' => 'COMPLETED']);
                        app(\App\Services\PaygService::class)->addCredit(
                            $customer->fresh(),
                            (float) $transaction->amount,
                            $transaction->id,
                        );
                    }
                } elseif ($code !== null && $code !== 0) {
                    $transaction->update(['status' => 'FAILED']);
                }
            } catch (\Throwable $e) {
                // Query failed — return current DB status
                Log::warning('STK query failed', ['error' => $e->getMessage()]);
            }
        }

        $transaction->refresh();

        return response()->json([
            'status'           => $transaction->status,
            'amount'           => (float) $transaction->amount,
            'mpesa_receipt_no' => $transaction->mpesa_receipt_no,
            'credit_balance'   => (float) $customer->fresh()->credit_balance,
        ]);
    }

    // ── Internals ─────────────────────────────────────────────────────────────

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
