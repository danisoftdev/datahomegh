<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\User;
use App\Support\UserProfileImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->loadMissing('role');

        abort_unless($user->role?->slug === Role::SLUG_SUPPLIER, 403);

        return view('admin.profile.edit', [
            'user' => $user,
            'agentShopRegistrationFeeGhs' => (string) (old(
                'agent_shop_registration_fee_ghs',
                PlatformSetting::get(PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS, '') ?? ''
            )),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role?->slug === Role::SLUG_SUPPLIER, 403);

        $request->merge([
            'agent_shop_registration_fee_ghs' => $request->input('agent_shop_registration_fee_ghs') === '' || $request->input('agent_shop_registration_fee_ghs') === null
                ? null
                : $request->input('agent_shop_registration_fee_ghs'),
        ]);

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
            'agent_shop_registration_fee_ghs' => ['nullable', 'numeric', 'min:0.01', 'max:999999'],
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

        $feeRaw = $validated['agent_shop_registration_fee_ghs'] ?? null;
        if ($feeRaw === null || $feeRaw === '') {
            PlatformSetting::query()->where('key', PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS)->delete();
        } else {
            PlatformSetting::set(
                PlatformSetting::KEY_AGENT_SHOP_REGISTRATION_FEE_GHS,
                number_format((float) $feeRaw, 2, '.', ''),
            );
        }

        return redirect()->route('admin.profile.edit')->with('status', __('Account details updated.'));
    }
}
