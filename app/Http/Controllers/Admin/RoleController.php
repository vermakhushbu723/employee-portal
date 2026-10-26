<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\CustomRole;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin-made roles: a name (e.g. "Recruiter") + which portal it opens (access level). */
class RoleController extends Controller
{
    public function index()
    {
        return view('admin.roles.index', [
            'roles' => CustomRole::withCount('users')->orderBy('name')->get(),
            'baseRoles' => Role::cases(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:roles,name'],
            'base_role' => ['required', Rule::enum(Role::class)],
            'description' => ['nullable', 'string', 'max:255'],
        ], ['name.unique' => 'A role with this name already exists.']);

        $role = CustomRole::create($data);
        $audit->log('role.create', "Created role {$role->name} ({$role->base_role->label()} access)");

        return back()->with('success', "Role \"{$role->name}\" created. Choose it in Employees → Add / Edit.");
    }

    public function destroy(CustomRole $role, AuditLogger $audit)
    {
        if ($role->users()->exists()) {
            return back()->withErrors(['action' => "\"{$role->name}\" is used by {$role->users()->count()} employee(s). Change their role first."]);
        }

        $role->delete();
        $audit->log('role.delete', "Deleted role {$role->name}");

        return back()->with('success', "Role \"{$role->name}\" deleted.");
    }
}
