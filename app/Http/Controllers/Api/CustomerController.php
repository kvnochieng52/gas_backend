<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Device;
use App\Services\MpesaService;
use App\Services\PaygService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private MpesaService $mpesa,
        private PaygService $payg,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Customer::with(['agent:id,name', 'devices'])
            ->withCount('devices');

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

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_no' => 'nullable|string|max:20|unique:customers,account_no',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'agent_id' => 'nullable|exists:users,id',
            'rate_plan_id' => 'nullable|exists:rate_plans,id',
            'device_serial_number' => 'nullable|string|exists:devices,serial_number',
        ]);

        if (! $request->user()->hasRole('admin')) {
            $data['agent_id'] = $request->user()->id;
        } elseif (empty($data['agent_id'])) {
            $data['agent_id'] = $request->user()->id;
        }

        if (empty($data['account_no'])) {
            $data['account_no'] = $this->generateAccountNo();
        }

        $deviceSerialNumber = $data['device_serial_number'] ?? null;
        unset($data['device_serial_number']);

        $customer = Customer::create($data);

        if ($deviceSerialNumber) {
            Device::where('serial_number', $deviceSerialNumber)
                ->whereNull('customer_id')
                ->update(['customer_id' => $customer->id]);
        }

        $customer->load(['agent:id,name', 'devices']);

        return response()->json($customer, 201);
    }

    public function show(Request $request, Customer $customer)
    {
        $this->authorizeCustomerAccess($request->user(), $customer);

        $customer->load(['agent:id,name', 'devices']);
        $customer->loadCount('devices');
        $customer->recent_transactions = $customer->transactions()
            ->latest()
            ->limit(5)
            ->get();

        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeCustomerAccess($request->user(), $customer);

        $data = $request->validate([
            'account_no' => 'nullable|string|max:20|unique:customers,account_no,' . $customer->id,
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:customers,phone,' . $customer->id,
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'agent_id' => 'nullable|exists:users,id',
            'rate_plan_id' => 'nullable|exists:rate_plans,id',
            'is_active' => 'sometimes|boolean',
            'device_serial_number' => 'nullable|string|exists:devices,serial_number',
        ]);

        if (! $request->user()->hasRole('admin')) {
            unset($data['agent_id']);
        }

        $deviceSerialNumber = $data['device_serial_number'] ?? null;
        unset($data['device_serial_number']);

        $customer->update($data);

        if ($deviceSerialNumber) {
            Device::where('serial_number', $deviceSerialNumber)
                ->whereNull('customer_id')
                ->update(['customer_id' => $customer->id]);
        }

        return response()->json($customer->fresh(['agent:id,name', 'devices']));
    }

    public function destroy(Request $request, Customer $customer)
    {
        $customer->update(['is_active' => false]);
        return response()->json(['message' => 'Customer deactivated successfully.']);
    }

    public function initiateTopUp(Request $request, Customer $customer)
    {
        $this->authorizeCustomerAccess($request->user(), $customer);

        $data = $request->validate([
            'amount' => 'required|numeric|min:50|max:150000',
            'phone' => 'required|string|max:20',
        ]);

        $transaction = $this->payg->createPendingTransaction(
            customer: $customer,
            amount: $data['amount'],
            agentId: $request->user()->id,
        );

        $checkoutRequestId = $this->mpesa->initiateSTKPush(
            phone: $data['phone'],
            amount: (int) $data['amount'],
            accountRef: $customer->account_no ?? 'GAS-' . $customer->id,
            transactionDesc: 'Gas credit top-up for ' . $customer->name,
        );

        $transaction->update(['mpesa_checkout_request_id' => $checkoutRequestId]);

        return response()->json([
            'message' => 'STK push sent. Awaiting payment.',
            'transaction_id' => $transaction->id,
            'checkout_request_id' => $checkoutRequestId,
        ]);
    }

    public function creditHistory(Request $request, Customer $customer)
    {
        $this->authorizeCustomerAccess($request->user(), $customer);

        $perPage = min((int) $request->get('limit', 20), 100);
        $ledger = $customer->creditLedger()
            ->latest('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $ledger->items(),
            'total' => $ledger->total(),
            'page' => $ledger->currentPage(),
            'limit' => $perPage,
            'total_pages' => $ledger->lastPage(),
        ]);
    }

    private function generateAccountNo(): string
    {
        $last = Customer::max('id') ?? 0;
        return 'ACC-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }

    private function authorizeCustomerAccess($user, Customer $customer): void
    {
        if ($user->hasRole('agent') && $customer->agent_id !== $user->id) {
            abort(403, 'Access denied.');
        }
    }
}
