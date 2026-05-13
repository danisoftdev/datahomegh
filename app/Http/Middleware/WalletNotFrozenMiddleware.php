<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WalletNotFrozenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $this->denyGuest($request);
        }

        $user = $request->user()->loadMissing('wallet');

        if ($user->trashed()) {
            return $this->denyWallet($request, 'Your account cannot perform this action.');
        }

        if (in_array($user->status, ['held', 'deleted'], true)) {
            return $this->denyWallet($request, 'Your account cannot perform this action.');
        }

        if ($user->wallet_frozen) {
            return $this->denyWallet($request, 'Your wallet is frozen.');
        }

        $wallet = $user->wallet;

        if ($wallet === null) {
            return $this->denyWallet($request, 'No wallet found for this account.');
        }

        if ($wallet->is_frozen) {
            return $this->denyWallet($request, 'Your wallet is frozen.');
        }

        return $next($request);
    }

    private function denyGuest(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest('/login');
    }

    private function denyWallet(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->back()->with('error', $message);
    }
}
