<?php

use App\Http\Middleware\AgentOrSupplierMiddleware;
use App\Http\Middleware\ApplyApiCorsHeaders;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SupplierMiddleware;
use App\Http\Middleware\WalletNotFrozenMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', [
            ApplyApiCorsHeaders::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'supplier' => SupplierMiddleware::class,
            'wallet' => WalletNotFrozenMiddleware::class,
            'agent_or_supplier' => AgentOrSupplierMiddleware::class,
            'api.token' => AuthenticateApiToken::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->validateCsrfTokens(except: [
            'wallet/paystack/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
