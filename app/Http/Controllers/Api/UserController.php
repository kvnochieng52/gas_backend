<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles:id,name')
            ->withCount('customers');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->role));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min((int) $request->get('limit', 20), 100);
        $users = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $users->items(),
            'total' => $users->total(),
            'page' => $users->currentPage(),
            'limit' => $perPage,
            'total_pages' => $users->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|max:255|unique:users,email',
            'phone'                 => 'nullable|string|max:20|unique:users,phone',
            'password'              => ['required', Password::min(8)->mixedCase()->numbers()],
            'password_confirmation' => 'required|same:password',
            'role'                  => 'required|string|exists:roles,name',
            'is_active'             => 'boolean',
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => $data['password'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->syncRoles([$data['role']]);
        $user->load('roles:id,name');

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'phone'     => 'nullable|string|max:20|unique:users,phone,' . $user->id,
            'role'      => 'sometimes|string|exists:roles,name',
            'is_active' => 'sometimes|boolean',
        ]);

        $role = $data['role'] ?? null;
        unset($data['role']);

        $user->update($data);

        if ($role) {
            $user->syncRoles([$role]);
        }

        $user->load('roles:id,name');
        $user->loadCount('customers');

        return response()->json($user);
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password'              => ['required', Password::min(8)->mixedCase()->numbers()],
            'password_confirmation' => 'required|same:password',
        ]);

        $user->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function destroy(User $user)
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json(['message' => 'User deactivated successfully.']);
    }
}
