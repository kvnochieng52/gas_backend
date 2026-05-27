<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RatePlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatePlanController extends Controller
{
    public function index()
    {
        $plans = RatePlan::with('createdBy:id,name')
            ->withCount('customers')
            ->latest()
            ->get();

        return response()->json($plans);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'amount'      => 'required|numeric|min:0.01',
            'unit'        => 'required|numeric|min:0.00000001',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);

        $data['created_by'] = $request->user()->id;

        $plan = DB::transaction(function () use ($data) {
            if (! empty($data['is_active'])) {
                RatePlan::where('is_active', true)->update(['is_active' => false]);
            }
            return RatePlan::create($data);
        });

        return response()->json($plan->load('createdBy:id,name'), 201);
    }

    public function update(Request $request, RatePlan $ratePlan)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'amount'      => 'sometimes|numeric|min:0.01',
            'unit'        => 'sometimes|numeric|min:0.00000001',
            'description' => 'nullable|string|max:500',
        ]);

        $ratePlan->update($data);

        return response()->json($ratePlan->fresh('createdBy:id,name'));
    }

    public function setActive(RatePlan $ratePlan)
    {
        DB::transaction(function () use ($ratePlan) {
            RatePlan::where('is_active', true)->update(['is_active' => false]);
            $ratePlan->update(['is_active' => true]);
        });

        return response()->json([
            'message' => "\"{$ratePlan->name}\" is now the active rate plan.",
            'plan'    => $ratePlan->fresh(),
        ]);
    }

    public function destroy(RatePlan $ratePlan)
    {
        if ($ratePlan->customers()->exists()) {
            return response()->json(['message' => 'Cannot delete a rate plan that is assigned to customers.'], 422);
        }

        $ratePlan->delete();
        return response()->json(['message' => 'Rate plan deleted.']);
    }
}
