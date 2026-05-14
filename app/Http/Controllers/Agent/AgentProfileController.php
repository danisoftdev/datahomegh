<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAccountPurgeService;
use App\Support\UserProfileImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgentProfileController extends Controller
{
    public function __construct(
        private readonly UserAccountPurgeService $userAccountPurgeService,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->role?->slug === Role::SLUG_AGENT, 403);

        return view('agent.profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role?->slug === Role::SLUG_AGENT, 403);

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique(User::class, 'username')->ignore($user->id)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'regex:/^0\d{9}$/'],
            'shop_name' => ['nullable', 'string', 'max:255'],
            'business_description' => ['nullable', 'string', 'max:10000'],
            'whatsapp_number' => ['nullable', 'string', 'max:64'],
            'whatsapp_channel' => ['nullable', 'string', 'max:255'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'profile_picture' => ['nullable', 'image', 'max:5120'],
            'logo' => ['nullable', 'image', 'max:8192'],
        ]);

        $user->username = $validated['username'];
        $user->name = $validated['name'];
        $user->email = $validated['email'] ?? null;
        $user->phone = $validated['phone'];
        $user->shop_name = $validated['shop_name'] ?? null;
        $user->business_description = $validated['business_description'] ?? null;
        $user->whatsapp_number = $validated['whatsapp_number'] ?? null;
        $user->whatsapp_channel = $validated['whatsapp_channel'] ?? null;

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

        if ($request->hasFile('logo')) {
            $path = UserProfileImageStorage::storeLogo($request->file('logo'));
            if ($user->logo) {
                Storage::disk('public')->delete($user->logo);
            }
            $user->logo = $path;
        }

        $user->save();

        return redirect()->route('agent.profile.edit')->with('status', __('Profile updated.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role?->slug === Role::SLUG_AGENT, 403);

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'delete_account' => ['accepted'],
        ]);

        $userId = (int) $user->getKey();

        Auth::logoutCurrentDevice();

        $this->userAccountPurgeService->permanentlyDelete(User::query()->findOrFail($userId));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('Your account has been permanently deleted. You may register again with the same details if you wish.'));
    }
}
