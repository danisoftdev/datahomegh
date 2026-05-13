<?php

namespace App\Http\Controllers;

use App\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FcmController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'device_type' => ['nullable', 'string', 'max:64'],
        ]);

        FcmToken::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'token' => $validated['token'],
            ],
            [
                'device_type' => $validated['device_type'] ?? null,
                'created_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }
}
