<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    // ── Roles ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $roles = Role::where('guard_name', 'web')
            ->withCount('users')
            ->with('permissions:id,name')
            ->get();

        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        return response()->json($role->load('permissions:id,name'), 201);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name,' . $role->id,
        ]);

        $role->update($data);

        return response()->json($role->fresh()->load('permissions:id,name'));
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return response()->json(['message' => 'Cannot delete a role that is assigned to users.'], 422);
        }

        $role->delete();
        return response()->json(['message' => 'Role deleted.']);
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions'   => 'present|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->syncPermissions($data['permissions']);

        return response()->json([
            'message'     => "Permissions updated for \"{$role->name}\".",
            'permissions' => $role->fresh()->permissions()->pluck('name'),
        ]);
    }

    // ── Permissions ───────────────────────────────────────────────────────────

    public function permissions()
    {
        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($permissions);
    }

    public function createPermission(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:permissions,name',
        ]);

        $permission = Permission::create(['name' => $data['name'], 'guard_name' => 'web']);

        return response()->json($permission, 201);
    }

    public function deletePermission(Permission $permission)
    {
        $permission->delete();
        return response()->json(['message' => 'Permission deleted.']);
    }
}
