<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');

        if (! preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            return $this->deny();
        }

        $token = trim($m[1]);
        if ($token === '') {
            return $this->deny();
        }

        $hash = hash('sha256', $token);

        $row = ApiToken::query()
            ->where('token_hash', $hash)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($row === null) {
            return $this->deny();
        }

        $user = User::query()->with('role')->find($row->user_id);

        if ($user === null) {
            return $this->deny();
        }

        $row->forceFill(['last_used_at' => now()])->save();

        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }

    private function deny(): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized',
        ], 401);
    }
}
