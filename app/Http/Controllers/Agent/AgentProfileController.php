<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Intervention\Image\Exceptions\DriverException;
use Intervention\Image\ImageManager;
use RuntimeException;

class AgentProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('agent.profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'shop_name' => ['nullable', 'string', 'max:255'],
            'business_description' => ['nullable', 'string', 'max:10000'],
            'whatsapp_number' => ['nullable', 'string', 'max:64'],
            'whatsapp_channel' => ['nullable', 'string', 'max:255'],
            'profile_picture' => ['nullable', 'image', 'max:5120'],
            'logo' => ['nullable', 'image', 'max:8192'],
        ]);

        $user->shop_name = $validated['shop_name'] ?? null;
        $user->business_description = $validated['business_description'] ?? null;
        $user->whatsapp_number = $validated['whatsapp_number'] ?? null;
        $user->whatsapp_channel = $validated['whatsapp_channel'] ?? null;

        if ($request->hasFile('profile_picture')) {
            $path = $this->storeResizedImage($request->file('profile_picture'), 300, 300);
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }
            $user->profile_picture = $path;
        }

        if ($request->hasFile('logo')) {
            $path = $this->storeResizedImage($request->file('logo'), 600, 200);
            if ($user->logo) {
                Storage::disk('public')->delete($user->logo);
            }
            $user->logo = $path;
        }

        $user->save();

        return redirect()->route('agent.profile.edit')->with('status', __('Profile updated.'));
    }

    private function storeResizedImage(UploadedFile $file, int $width, int $height): string
    {
        try {
            $manager = ImageManager::gd();
        } catch (DriverException) {
            throw new RuntimeException('Image processing (GD) is not available on this server.');
        }

        $image = $manager->read($file)->cover($width, $height);

        $relative = 'profiles/'.uniqid('img_', true).'.jpg';

        Storage::disk('public')->put($relative, $image->toJpeg(85)->toString());

        return $relative;
    }
}
