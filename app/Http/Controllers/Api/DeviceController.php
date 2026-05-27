<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceReading;
use App\Services\MqttService;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(private MqttService $mqtt) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Device::with(['customer:id,name,phone,agent_id']);

        if ($user->hasRole('agent')) {
            $query->whereHas('customer', fn($q) => $q->where('agent_id', $user->id));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->search) {
            $query->where('serial_number', 'like', "%{$request->search}%");
        }

        $perPage = min((int) $request->get('limit', 20), 100);
        $devices = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $devices->items(),
            'total' => $devices->total(),
            'page' => $devices->currentPage(),
            'limit' => $perPage,
            'total_pages' => $devices->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'serial_number' => 'required|string|max:100|unique:devices,serial_number',
            'imei' => 'nullable|string|max:20',
            'customer_id' => 'nullable|exists:customers,id',
            'cylinder_size_kg' => 'required|numeric|min:1|max:1000',
            'mqtt_topic' => 'required|string|max:255|unique:devices,mqtt_topic',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'firmware_version' => 'nullable|string|max:50',
        ]);

        $device = Device::create($data);
        $device->load('customer:id,name,phone');

        return response()->json($device, 201);
    }

    public function show(Request $request, Device $device)
    {
        $this->authorizeDeviceAccess($request->user(), $device);

        $device->load(['customer:id,name,phone,credit_balance']);
        $device->recent_readings = $device->readings()
            ->where('created_at', '>=', now()->subHours(24))
            ->latest('created_at')
            ->limit(48)
            ->get();

        return response()->json($device);
    }

    public function update(Request $request, Device $device)
    {
        $this->authorizeDeviceAccess($request->user(), $device);

        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'cylinder_size_kg' => 'sometimes|numeric|min:1|max:1000',
            'mqtt_topic' => 'sometimes|string|max:255|unique:devices,mqtt_topic,' . $device->id,
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'firmware_version' => 'nullable|string|max:50',
        ]);

        $device->update($data);

        return response()->json($device->fresh('customer:id,name,phone'));
    }

    public function destroy(Request $request, Device $device)
    {
        if ($device->customer_id) {
            return response()->json(['message' => 'Unassign device from customer before deleting.'], 422);
        }
        $device->delete();
        return response()->json(['message' => 'Device deleted.']);
    }

    public function controlValve(Request $request, Device $device)
    {
        $this->authorizeDeviceAccess($request->user(), $device);

        $data = $request->validate([
            'open' => 'required|boolean',
        ]);

        if ($data['open'] && $device->customer && $device->customer->credit_balance < (float) \App\Models\SystemSetting::get('min_credit_balance', 100)) {
            return response()->json(['message' => 'Insufficient credit to open valve.'], 422);
        }

        $this->mqtt->publishValveCommand($device->mqtt_topic, $data['open']);
        $device->update(['valve_open' => $data['open']]);

        broadcast(new \App\Events\ValveChanged($device, $data['open']))->toOthers();

        return response()->json([
            'message' => 'Valve ' . ($data['open'] ? 'opened' : 'closed') . ' successfully.',
            'valve_open' => $data['open'],
        ]);
    }

    public function readings(Request $request, Device $device)
    {
        $this->authorizeDeviceAccess($request->user(), $device);

        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $query = $device->readings()->latest('created_at');

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $perPage = min((int) $request->get('limit', 50), 500);
        $readings = $query->paginate($perPage);

        return response()->json([
            'data' => $readings->items(),
            'total' => $readings->total(),
            'page' => $readings->currentPage(),
            'limit' => $perPage,
            'total_pages' => $readings->lastPage(),
        ]);
    }

    private function authorizeDeviceAccess($user, Device $device): void
    {
        if ($user->hasRole('agent')) {
            if (! $device->customer || $device->customer->agent_id !== $user->id) {
                abort(403, 'Access denied.');
            }
        }
    }
}
