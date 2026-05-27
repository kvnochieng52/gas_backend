<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceTamperEvent;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    // ── Critical devices (gas below threshold) ─────────────────────────────

    public function criticalDevices(Request $request)
    {
        $threshold = (float) SystemSetting::get('low_gas_threshold_pct', 20);
        $user      = $request->user();

        $query = Device::with(['customer:id,name,account_no,phone'])
            ->whereNotNull('customer_id')
            ->where(fn($q) =>
                $q->where('gas_level_pct', '<=', $threshold)
                  ->orWhereIn('status', ['LOW_GAS', 'FAULT'])
            );

        if ($user->hasRole('agent')) {
            $query->whereHas('customer', fn($q) => $q->where('agent_id', $user->id));
        }

        if ($request->search) {
            $query->where(fn($q) =>
                $q->where('serial_number', 'like', "%{$request->search}%")
                  ->orWhereHas('customer', fn($q2) =>
                      $q2->where('name', 'like', "%{$request->search}%")
                  )
            );
        }

        $perPage = min((int) $request->get('limit', 20), 100);
        $items   = $query->orderBy('gas_level_pct')->paginate($perPage);

        return response()->json([
            'data'        => $items->items(),
            'total'       => $items->total(),
            'page'        => $items->currentPage(),
            'limit'       => $perPage,
            'total_pages' => $items->lastPage(),
            'threshold'   => $threshold,
        ]);
    }

    public function recentCritical(Request $request)
    {
        $threshold = (float) SystemSetting::get('low_gas_threshold_pct', 20);
        $user      = $request->user();

        $query = Device::with(['customer:id,name,account_no'])
            ->whereNotNull('customer_id')
            ->where(fn($q) =>
                $q->where('gas_level_pct', '<=', $threshold)
                  ->orWhereIn('status', ['LOW_GAS', 'FAULT'])
            );

        if ($user->hasRole('agent')) {
            $query->whereHas('customer', fn($q) => $q->where('agent_id', $user->id));
        }

        return response()->json($query->orderBy('gas_level_pct')->limit(6)->get());
    }

    // ── Tampered devices ────────────────────────────────────────────────────

    public function tamperedDevices(Request $request)
    {
        $user  = $request->user();
        $query = Device::with(['customer:id,name,account_no,phone', 'latestTamperEvent'])
            ->where('is_tampered', true);

        if ($user->hasRole('agent')) {
            $query->whereHas('customer', fn($q) => $q->where('agent_id', $user->id));
        }

        if ($request->search) {
            $query->where(fn($q) =>
                $q->where('serial_number', 'like', "%{$request->search}%")
                  ->orWhereHas('customer', fn($q2) =>
                      $q2->where('name', 'like', "%{$request->search}%")
                  )
            );
        }

        $perPage = min((int) $request->get('limit', 20), 100);
        $items   = $query->orderByDesc('last_tampered_at')->paginate($perPage);

        return response()->json([
            'data'        => $items->items(),
            'total'       => $items->total(),
            'page'        => $items->currentPage(),
            'limit'       => $perPage,
            'total_pages' => $items->lastPage(),
        ]);
    }

    public function recentTamperEvents(Request $request)
    {
        $user  = $request->user();
        $query = DeviceTamperEvent::with([
                'device:id,serial_number,gas_level_pct,is_tampered',
                'device.customer:id,name,account_no',
            ])
            ->where('resolved', false);

        if ($user->hasRole('agent')) {
            $query->whereHas('device.customer', fn($q) => $q->where('agent_id', $user->id));
        }

        return response()->json($query->latest()->limit(6)->get());
    }

    // ── Flag / Resolve ──────────────────────────────────────────────────────

    public function flagTamper(Request $request, Device $device)
    {
        $data = $request->validate([
            'event_type'  => 'required|in:PHYSICAL_TAMPER,GAS_DROP,VALVE_MISMATCH,MANUAL_FLAG',
            'description' => 'nullable|string|max:500',
        ]);

        $event = DeviceTamperEvent::create([
            'device_id'    => $device->id,
            'customer_id'  => $device->customer_id,
            'event_type'   => $data['event_type'],
            'gas_level_pct'=> $device->gas_level_pct,
            'description'  => $data['description'] ?? null,
        ]);

        $device->update([
            'is_tampered'      => true,
            'last_tampered_at' => now(),
        ]);

        return response()->json([
            'message' => "Device {$device->serial_number} flagged as tampered.",
            'event'   => $event->load('device:id,serial_number'),
        ], 201);
    }

    public function resolveTamper(Request $request, Device $device)
    {
        DeviceTamperEvent::where('device_id', $device->id)
            ->where('resolved', false)
            ->update([
                'resolved'    => true,
                'resolved_at' => now(),
                'resolved_by' => $request->user()->id,
            ]);

        $device->update(['is_tampered' => false]);

        return response()->json(['message' => "Tamper resolved for {$device->serial_number}."]);
    }

    // ── Summary counts (for dashboard stats) ───────────────────────────────

    public function summary(Request $request)
    {
        $threshold = (float) SystemSetting::get('low_gas_threshold_pct', 20);
        $user      = $request->user();

        $deviceQ = Device::whereNotNull('customer_id');
        if ($user->hasRole('agent')) {
            $deviceQ->whereHas('customer', fn($q) => $q->where('agent_id', $user->id));
        }

        return response()->json([
            'critical_count' => (clone $deviceQ)->where(fn($q) =>
                $q->where('gas_level_pct', '<=', $threshold)
                  ->orWhereIn('status', ['LOW_GAS', 'FAULT'])
            )->count(),
            'tampered_count' => (clone $deviceQ)->where('is_tampered', true)->count(),
            'threshold'      => $threshold,
        ]);
    }
}
