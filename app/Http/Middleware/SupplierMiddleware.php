<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupplierMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $this->denyGuest($request);
        }

        $request->user()->loadMissing('role');

        if ($request->user()->role?->slug !== Role::SLUG_SUPPLIER) {
            return $this->denyForbidden($request);
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

    private function denyForbidden(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return redirect('/login');
    }
}
