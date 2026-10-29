<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // فقط کاربر جدول users با نقش مدیریتی؛ مخاطب (guard: contact) هرگز
        $user = $request->user('web');

        if (! $user instanceof User || ! $user->isAdmin()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'این حساب دسترسی مدیریتی ندارد.',
                ]);
        }

        return $next($request);
    }
}
