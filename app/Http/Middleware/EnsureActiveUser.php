<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveUser
{
    // A deactivated account loses access on its next request, even with a session or remember cookie still alive.
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && ! $request->user()->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Tu cuenta está desactivada. Pide acceso a un administrador.']);
        }

        return $next($request);
    }
}
