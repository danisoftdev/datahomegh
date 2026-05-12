<?php

namespace App\Http\Middleware;

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

        if ($slug === null || ! in_array($slug, $roles, true)) {
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
