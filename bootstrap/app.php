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
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

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
            'webhooks/encarta',
            'register/agent-fee/callback',
            'wallet/topup/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if (! $request->isMethod('POST')) {
                return null;
            }

            if ($request->is('register')) {
                return redirect()
                    ->route('register')
                    ->withErrors([
                        'registration' => __('Your session expired before the form was sent. Please fill the form again and continue to Paystack.'),
                    ])
                    ->withInput($request->except('password', 'password_confirmation'));
            }

            if ($request->is('login')) {
                return redirect()
                    ->route('login')
                    ->withErrors([
                        'username' => __('Your session expired. Please sign in again.'),
                    ])
                    ->withInput($request->only('username'));
            }

            return null;
        });
    })->create();
