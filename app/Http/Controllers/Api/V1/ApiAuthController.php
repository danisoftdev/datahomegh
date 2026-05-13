<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApiAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('username', $data['username'])
            ->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active',
            ], 403);
        }

        $user->loadMissing('role');

        if (! $user->role || ! $user->role->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Your role is disabled. Contact support.',
            ], 403);
        }

        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $expires = now()->addDays(30);

        ApiToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => $hash,
            'name' => 'api',
            'expires_at' => $expires,
            'last_used_at' => now(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'token' => $plain,
                'token_type' => 'Bearer',
                'expires_at' => $expires->format('Y-m-d H:i:s'),
                'user' => $this->publicUserPayload($user),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicUserPayload(User $user): array
    {
        $user->makeHidden(['password', 'remember_token']);

        return $user->toArray();
    }
}
