<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\UserProfileImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BuyerProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->role?->slug === Role::SLUG_BUYER, 403);

        return view('buyer.profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role?->slug === Role::SLUG_BUYER, 403);

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique(User::class, 'username')->ignore($user->id)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'regex:/^0\d{9}$/'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'profile_picture' => ['nullable', 'image', 'max:5120'],
        ]);

        $user->username = $validated['username'];
        $user->name = $validated['name'];
        $user->email = $validated['email'] ?? null;
        $user->phone = $validated['phone'];

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        if ($request->hasFile('profile_picture')) {
            $path = UserProfileImageStorage::storeProfilePicture($request->file('profile_picture'));
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }
            $user->profile_picture = $path;
        }

        $user->save();

        return redirect()->route('buyer.profile.edit')->with('status', __('Profile updated.'));
    }
}
