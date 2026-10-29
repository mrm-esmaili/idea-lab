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
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'contact.fresh' => \App\Http\Middleware\EnsureContactSessionIsFresh::class,
            'public.form' => \App\Http\Middleware\GuardPublicForm::class,
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
        // مقادیر محرمانه پس از خطای اعتبارسنجی در نشست نگه داشته نشوند
        $exceptions->dontFlash(['code', 'password', 'password_confirmation', 'current_password', '_form_started']);
    })->create();
