<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController
{
    public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (Auth::attemptWhen($credentials, fn (User $user) => $user->active, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }
        // Only someone who knows the password learns that the account is deactivated.
        $user = Auth::getLastAttempted();
        $blocked = $user && ! $user->active && Auth::getProvider()->validateCredentials($user, $credentials);

        return back()->withErrors(['email' => $blocked ? 'Tu cuenta está desactivada. Pide acceso a un administrador.' : 'Las credenciales no son correctas.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
