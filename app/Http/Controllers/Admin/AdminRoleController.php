<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\AdminRolePermissionGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminRoleController extends Controller
{
    private const RESERVED_SLUGS = [
        Role::SLUG_SUPPLIER,
        Role::SLUG_AGENT,
        Role::SLUG_BUYER,
    ];

    public function index(): View
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('admin.roles.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique(Role::class, 'name')],
        ]);

        $slug = $this->makeUniqueSlug(Str::slug($data['name']));

        $role = Role::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'is_enabled' => true,
        ]);

        return redirect()
            ->route('admin.roles.edit', $role)
            ->with('status', __('Role created. Assign permissions below.'));
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');

        $groups = AdminRolePermissionGroups::groups();
        $slugs = AdminRolePermissionGroups::allSlugs();

        $permissions = Permission::query()
            ->whereIn('slug', $slugs)
            ->orderBy('name')
            ->get()
            ->keyBy('slug');

        $permissionsByCategory = [];
        foreach ($groups as $category => $categorySlugs) {
            $permissionsByCategory[$category] = collect($categorySlugs)
                ->map(fn (string $slug) => $permissions->get($slug))
                ->filter()
                ->values();
        }

        $selectedIds = $role->permissions->pluck('id')->all();

        return view('admin.roles.edit', compact('role', 'permissionsByCategory', 'selectedIds'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique(Role::class, 'name')->ignore($role->id)],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->name = $data['name'];
        $role->save();

        $ids = array_values(array_unique(array_map('intval', $data['permission_ids'] ?? [])));
        $allowed = Permission::query()
            ->whereIn('slug', AdminRolePermissionGroups::allSlugs())
            ->pluck('id')
            ->all();

        $ids = array_values(array_intersect($ids, $allowed));
        $role->permissions()->sync($ids);

        return redirect()
            ->route('admin.roles.edit', $role)
            ->with('status', __('Role updated.'));
    }

    public function toggle(Role $role): RedirectResponse
    {
        if ($role->slug === Role::SLUG_SUPPLIER) {
            return back()->with('error', __('The supplier role cannot be disabled.'));
        }

        $role->is_enabled = ! $role->is_enabled;
        $role->save();

        return back()->with('status', $role->is_enabled ? __('Role enabled.') : __('Role disabled.'));
    }

    private function makeUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'role';

        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            $slug = $slug.'-'.Str::lower(Str::random(6));
        }

        $original = $slug;
        $i = 2;
        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $original.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
