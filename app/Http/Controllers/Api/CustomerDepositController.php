<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\DepositConfiguration;
use App\Services\MpesaService;
use Illuminate\Http\Request;

class CustomerDepositController extends Controller
{
    public function __construct(private MpesaService $mpesa) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = CustomerDeposit::with([
            'customer:id,name,phone,account_no',
            'depositConfig:id,name,amount',
            'collectedBy:id,name',
        ]);

        if ($user->hasRole('agent')) {
            $query->whereHas('customer', fn($q) => $q->where('agent_id', $user->id));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $perPage = min((int) $request->get('limit', 20), 100);
        $deposits = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $deposits->items(),
            'total' => $deposits->total(),
            'page' => $deposits->currentPage(),
            'limit' => $perPage,
            'total_pages' => $deposits->lastPage(),
        ]);
    }

    public function customerStatus(Request $request)
    {
        $user = $request->user();

        $query = Customer::with(['completedDeposit.depositConfig:id,name,amount'])
            ->withCount(['deposits as pending_deposit_count' => fn($q) => $q->where('status', 'PENDING')])
            ->where('is_active', true);

        if ($user->hasRole('agent')) {
            $query->where('agent_id', $user->id);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('account_no', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('deposit_status')) {
            if ($request->deposit_status === 'paid') {
                $query->whereHas('deposits', fn($q) => $q->where('status', 'COMPLETED'));
            } elseif ($request->deposit_status === 'unpaid') {
                $query->whereDoesntHave('deposits', fn($q) => $q->where('status', 'COMPLETED'));
            }
        }

        $perPage = min((int) $request->get('limit', 20), 100);
        $customers = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $customers->items(),
            'total' => $customers->total(),
            'page' => $customers->currentPage(),
            'limit' => $perPage,
            'total_pages' => $customers->lastPage(),
        ]);
    }

    public function initiateMpesa(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:100',
            'phone' => 'required|string|max:20',
        ]);

        $activeConfig = DepositConfiguration::getActive();

        $deposit = CustomerDeposit::create([
            'customer_id' => $customer->id,
            'deposit_config_id' => $activeConfig?->id,
            'amount_required' => $activeConfig?->amount ?? $data['amount'],
            'amount_paid' => $data['amount'],
            'payment_method' => 'MPESA',
            'status' => 'PENDING',
            'collected_by' => $request->user()->id,
        ]);

        $checkoutRequestId = $this->mpesa->initiateSTKPush(
            phone: $data['phone'],
            amount: (int) $data['amount'],
            accountRef: $customer->account_no ?? 'DEP-' . $customer->id,
            transactionDesc: 'Initial deposit for ' . $customer->name,
        );

        $deposit->update(['mpesa_checkout_request_id' => $checkoutRequestId]);

        return response()->json([
            'message' => 'STK push sent. Awaiting payment.',
            'deposit_id' => $deposit->id,
            'checkout_request_id' => $checkoutRequestId,
        ]);
    }

    public function recordCash(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $activeConfig = DepositConfiguration::getActive();

        $deposit = CustomerDeposit::create([
            'customer_id' => $customer->id,
            'deposit_config_id' => $activeConfig?->id,
            'amount_required' => $activeConfig?->amount ?? $data['amount'],
            'amount_paid' => $data['amount'],
            'payment_method' => 'CASH',
            'status' => 'COMPLETED',
            'notes' => $data['notes'] ?? null,
            'collected_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Cash deposit recorded successfully.',
            'deposit' => $deposit->load('depositConfig:id,name,amount'),
        ], 201);
    }

    public function mpesaCallback(Request $request)
    {
        $body = $request->input('Body.stkCallback');
        if (! $body) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $checkoutRequestId = $body['CheckoutRequestID'] ?? null;
        $resultCode = $body['ResultCode'] ?? 1;

        $deposit = CustomerDeposit::where('mpesa_checkout_request_id', $checkoutRequestId)->first();
        if (! $deposit) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        if ($resultCode === 0) {
            $metadata = collect($body['CallbackMetadata']['Item'] ?? [])->pluck('Value', 'Name');
            $deposit->update([
                'status' => 'COMPLETED',
                'mpesa_receipt_no' => $metadata->get('MpesaReceiptNumber'),
            ]);
        } else {
            $deposit->update(['status' => 'FAILED']);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function queryStatus(Request $request, string $checkoutRequestId)
    {
        $result = $this->mpesa->querySTKPush($checkoutRequestId);
        return response()->json($result);
    }
}
