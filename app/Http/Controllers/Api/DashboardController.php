<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Device;
use App\Models\SystemSetting;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();
        $isAgent = $user->hasRole('agent');
        $threshold = (float) SystemSetting::get('low_gas_threshold_pct', 20);

        $customerQuery = Customer::query();
        if ($isAgent) $customerQuery->where('agent_id', $user->id);

        $deviceQuery = Device::query();
        if ($isAgent) $deviceQuery->whereHas('customer', fn($q) => $q->where('agent_id', $user->id));

        $revenueQuery = Transaction::where('status', 'COMPLETED')
            ->where('created_at', '>=', now()->startOfMonth());
        if ($isAgent) $revenueQuery->where('agent_id', $user->id);

        $criticalQuery = (clone $deviceQuery)->whereNotNull('customer_id')
            ->where(fn($q) => $q->where('gas_level_pct', '<=', $threshold)->orWhereIn('status', ['LOW_GAS', 'FAULT']));

        return response()->json([
            'total_customers'  => $customerQuery->where('is_active', true)->count(),
            'active_devices'   => (clone $deviceQuery)->whereNotNull('customer_id')->count(),
            'total_revenue'    => $revenueQuery->sum('amount'),
            'low_gas_alerts'   => (clone $deviceQuery)->where('status', 'LOW_GAS')->count(),
            'online_devices'   => (clone $deviceQuery)->where('status', 'ONLINE')->count(),
            'offline_devices'  => (clone $deviceQuery)->where('status', 'OFFLINE')->count(),
            'fault_devices'    => (clone $deviceQuery)->where('status', 'FAULT')->count(),
            'critical_devices' => $criticalQuery->count(),
            'tampered_devices' => (clone $deviceQuery)->where('is_tampered', true)->count(),
        ]);
    }

    public function recentTransactions(Request $request)
    {
        $user = $request->user();
        $query = Transaction::with(['customer:id,name,phone'])
            ->where('status', 'COMPLETED');

        if ($user->hasRole('agent')) {
            $query->where('agent_id', $user->id);
        }

        return response()->json($query->latest()->limit(10)->get());
    }

    public function gasDistribution(Request $request)
    {
        $user = $request->user();
        $query = Device::whereNotNull('customer_id');

        if ($user->hasRole('agent')) {
            $query->whereHas('customer', fn($q) => $q->where('agent_id', $user->id));
        }

        $devices = $query->get(['gas_level_pct']);

        $distribution = [
            ['range' => '0-25%', 'label' => 'Critical', 'count' => 0],
            ['range' => '25-50%', 'label' => 'Low', 'count' => 0],
            ['range' => '50-75%', 'label' => 'Medium', 'count' => 0],
            ['range' => '75-100%', 'label' => 'Good', 'count' => 0],
        ];

        foreach ($devices as $device) {
            $level = (float) $device->gas_level_pct;
            if ($level < 25) $distribution[0]['count']++;
            elseif ($level < 50) $distribution[1]['count']++;
            elseif ($level < 75) $distribution[2]['count']++;
            else $distribution[3]['count']++;
        }

        return response()->json($distribution);
    }

    public function revenueChart(Request $request)
    {
        $user = $request->user();
        $query = Transaction::where('status', 'COMPLETED')
            ->where('created_at', '>=', now()->subDays(30));

        if ($user->hasRole('agent')) {
            $query->where('agent_id', $user->id);
        }

        $data = $query->groupBy(DB::raw('DATE(created_at)'))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as amount'))
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    public function deviceStatus(Request $request)
    {
        $user = $request->user();
        $query = Device::query();

        if ($user->hasRole('agent')) {
            $query->whereHas('customer', fn($q) => $q->where('agent_id', $user->id));
        }

        $counts = $query->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            ['status' => 'ONLINE', 'count' => $counts->get('ONLINE', 0)],
            ['status' => 'OFFLINE', 'count' => $counts->get('OFFLINE', 0)],
            ['status' => 'LOW_GAS', 'count' => $counts->get('LOW_GAS', 0)],
            ['status' => 'FAULT', 'count' => $counts->get('FAULT', 0)],
        ]);
    }
}
