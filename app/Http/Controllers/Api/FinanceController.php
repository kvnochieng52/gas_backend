<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function stats(Request $request)
    {
        $user  = $request->user();
        $base  = Transaction::where('status', 'COMPLETED');
        if ($user->hasRole('agent')) $base->where('agent_id', $user->id);

        $month = (clone $base)->where('created_at', '>=', now()->startOfMonth());

        return response()->json([
            'total_revenue'      => (clone $base)->sum('amount'),
            'this_month'         => (clone $month)->sum('amount'),
            'transaction_count'  => (clone $base)->count(),
            'this_month_count'   => (clone $month)->count(),
            'avg_top_up'         => round((clone $base)->avg('amount') ?? 0),
        ]);
    }

    public function revenueByDay(Request $request)
    {
        $days  = (int) $request->get('days', 30);
        $days  = in_array($days, [7, 14, 30, 90]) ? $days : 30;
        $user  = $request->user();

        $query = Transaction::where('status', 'COMPLETED')
            ->where('created_at', '>=', now()->subDays($days));
        if ($user->hasRole('agent')) $query->where('agent_id', $user->id);

        $data = $query
            ->groupBy(DB::raw('DATE(created_at)'))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as amount'),
                DB::raw('COUNT(*) as count')
            )
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    public function transactions(Request $request)
    {
        $user  = $request->user();
        $query = Transaction::with(['customer:id,name,account_no,phone', 'agent:id,name'])
            ->where('status', 'COMPLETED');
        if ($user->hasRole('agent')) $query->where('agent_id', $user->id);

        if ($request->search) {
            $query->whereHas('customer', fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('account_no', 'like', "%{$request->search}%")
            )->orWhere('mpesa_receipt_no', 'like', "%{$request->search}%");
        }

        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }

        $perPage  = min((int) $request->get('limit', 20), 100);
        $items    = $query->latest()->paginate($perPage);

        return response()->json([
            'data'        => $items->items(),
            'total'       => $items->total(),
            'page'        => $items->currentPage(),
            'limit'       => $perPage,
            'total_pages' => $items->lastPage(),
        ]);
    }

    public function exportCsv(Request $request)
    {
        $user  = $request->user();
        $query = Transaction::with(['customer:id,name,account_no,phone'])
            ->where('status', 'COMPLETED');
        if ($user->hasRole('agent')) $query->where('agent_id', $user->id);

        if ($request->search) {
            $query->whereHas('customer', fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('account_no', 'like', "%{$request->search}%")
            );
        }

        $transactions = $query->latest()->limit(10000)->get();
        $filename     = 'transactions_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Customer', 'Account No', 'Phone', 'Amount (KES)', 'Receipt No', 'Method', 'Status']);
            foreach ($transactions as $tx) {
                fputcsv($handle, [
                    $tx->created_at->format('Y-m-d H:i'),
                    $tx->customer?->name ?? '—',
                    $tx->customer?->account_no ?? '—',
                    $tx->customer?->phone ?? '—',
                    $tx->amount,
                    $tx->mpesa_receipt_no ?? '—',
                    $tx->payment_method,
                    $tx->status,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
