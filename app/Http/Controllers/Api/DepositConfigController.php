<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DepositConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepositConfigController extends Controller
{
    public function index()
    {
        $configs = DepositConfiguration::with('createdBy:id,name')
            ->withCount('customerDeposits')
            ->latest()
            ->get();

        return response()->json($configs);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $data['created_by'] = $request->user()->id;

        $config = DB::transaction(function () use ($data) {
            if (! empty($data['is_active'])) {
                DepositConfiguration::where('is_active', true)->update(['is_active' => false]);
            }
            return DepositConfiguration::create($data);
        });

        return response()->json($config->load('createdBy:id,name'), 201);
    }

    public function update(Request $request, DepositConfiguration $depositConfiguration)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:100',
            'description' => 'nullable|string|max:500',
        ]);

        $depositConfiguration->update($data);

        return response()->json($depositConfiguration->fresh('createdBy:id,name'));
    }

    public function setActive(DepositConfiguration $depositConfiguration)
    {
        DB::transaction(function () use ($depositConfiguration) {
            DepositConfiguration::where('is_active', true)->update(['is_active' => false]);
            $depositConfiguration->update(['is_active' => true]);
        });

        return response()->json([
            'message' => "\"{$depositConfiguration->name}\" is now the active deposit.",
            'config' => $depositConfiguration->fresh(),
        ]);
    }

    public function destroy(DepositConfiguration $depositConfiguration)
    {
        if ($depositConfiguration->customerDeposits()->exists()) {
            return response()->json(['message' => 'Cannot delete a configuration that has been used.'], 422);
        }

        $depositConfiguration->delete();
        return response()->json(['message' => 'Deposit configuration deleted.']);
    }
}
