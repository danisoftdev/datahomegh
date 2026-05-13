<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies CORS headers from config/cors.php for API routes (no wildcard in production).
 */
class ApplyApiCorsHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 204);
        } else {
            /** @var Response $response */
            $response = $next($request);
        }

        $allowed = config('cors.allowed_origins', []);
        if ($allowed === [] || $allowed === ['*']) {
            return $response;
        }

        $origin = $request->headers->get('Origin');
        if ($origin !== null && $origin !== '' && in_array($origin, $allowed, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        } elseif (count($allowed) === 1) {
            $response->headers->set('Access-Control-Allow-Origin', $allowed[0]);
        }

        $response->headers->set('Access-Control-Allow-Methods', implode(', ', [
            'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS',
        ]));
        $response->headers->set(
            'Access-Control-Allow-Headers',
            'Authorization, Content-Type, Accept, X-Requested-With'
        );
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}
