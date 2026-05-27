<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\MpesaService;
use App\Services\PaygService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private MpesaService $mpesa,
        private PaygService $payg,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Transaction::with(['customer:id,name,phone', 'agent:id,name']);

        if ($user->hasRole('agent')) {
            $query->where('agent_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $perPage = min((int) $request->get('limit', 20), 100);
        $transactions = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $transactions->items(),
            'total' => $transactions->total(),
            'page' => $transactions->currentPage(),
            'limit' => $perPage,
            'total_pages' => $transactions->lastPage(),
        ]);
    }

    public function show(Request $request, Transaction $transaction)
    {
        if ($request->user()->hasRole('agent') && $transaction->agent_id !== $request->user()->id) {
            abort(403);
        }

        $transaction->load(['customer:id,name,phone', 'agent:id,name']);
        return response()->json($transaction);
    }

    public function mpesaCallback(Request $request)
    {
        $data = $request->all();
        Log::info('M-Pesa callback received', $data);

        $body = $data['Body']['stkCallback'] ?? null;

        if (! $body) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $checkoutRequestId = $body['CheckoutRequestID'] ?? null;
        $resultCode = $body['ResultCode'] ?? 1;

        $transaction = Transaction::where('mpesa_checkout_request_id', $checkoutRequestId)->first();

        if (! $transaction) {
            Log::warning('M-Pesa callback: transaction not found', ['checkout_request_id' => $checkoutRequestId]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        if ($resultCode === 0) {
            $metadata = collect($body['CallbackMetadata']['Item'] ?? [])
                ->pluck('Value', 'Name');

            $transaction->update([
                'status' => 'COMPLETED',
                'mpesa_receipt_no' => $metadata->get('MpesaReceiptNumber'),
            ]);

            $this->payg->addCredit($transaction->customer, $transaction->amount, $transaction->id);
        } else {
            $transaction->update(['status' => 'FAILED']);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function queryStatus(Request $request, string $checkoutRequestId)
    {
        $result = $this->mpesa->querySTKPush($checkoutRequestId);
        return response()->json($result);
    }
}
