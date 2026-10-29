<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'contact.fresh' => \App\Http\Middleware\EnsureContactSessionIsFresh::class,
        ]);

        // مهمان بخش حساب مخاطب به ورود مخاطب می‌رود، نه ورود مدیران
        $middleware->redirectGuestsTo(
            fn (\Illuminate\Http\Request $request) => $request->is('account', 'account/*')
                ? route('account.login')
                : route('login')
        );

        $middleware->redirectUsersTo(
            fn (\Illuminate\Http\Request $request) => $request->is('account', 'account/*')
                ? route('account.dashboard')
                : route('admin.dashboard')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
