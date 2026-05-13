<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()->withCount('permissions')->with('permissions')->orderBy('name')->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        $role->is_enabled = $data['is_enabled'];
        $role->save();

        return back()->with('status', __('Role saved.'));
    }
}
