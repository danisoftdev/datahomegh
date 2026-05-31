<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return $this->denyGuest($request);
        }

        $request->user()->loadMissing('role');

        $slug = $request->user()->role?->slug;

        foreach ($roles as $roleSlug) {
            if ($roleSlug === Role::SLUG_AGENT && $request->user()->canAccessAgentArea()) {
                return $next($request);
            }

            if ($roleSlug === Role::SLUG_BUYER && $request->user()->canAccessBuyerArea()) {
                return $next($request);
            }

            if ($slug === $roleSlug) {
                return $next($request);
            }
        }

        return $this->denyForbidden($request);
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
