<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ProfileController
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $sessions = collect();
        if (config('session.driver') === 'database') {
            $sessions = DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->orderByDesc('last_activity')->get()
                ->map(fn ($session) => (object) [
                    'device' => $this->device((string) $session->user_agent),
                    'ip' => $session->ip_address,
                    'active' => now()->setTimestamp($session->last_activity),
                    'current' => $session->id === $request->session()->getId(),
                ])->sortByDesc('current')->values();
        }

        return view('profile.edit', compact('user', 'sessions'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $user->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]));

        return redirect()->route('profile.edit')->with('success', 'Datos actualizados.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', PasswordRule::min(8)->letters()->numbers()],
        ]);
        $user = $request->user();
        $user->forceFill(['password' => $request->input('password'), 'remember_token' => Str::random(60)])->save();
        // A new password signs out every other device; this one keeps going with a fresh session id.
        $request->session()->regenerate();
        $user->endSessions(except: $request->session()->getId());

        return redirect()->route('profile.edit')->with('success', 'Contraseña actualizada. Se cerraron tus otras sesiones.');
    }

    public function destroySessions(Request $request)
    {
        $request->validate(['confirm_password' => ['required', 'current_password']]);
        $request->user()->endSessions(except: $request->session()->getId());

        return redirect()->route('profile.edit')->with('success', 'Se cerraron tus otras sesiones.');
    }

    // Enough to recognise a device in the list ("Chrome · macOS"), not a full user-agent parser.
    private function device(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Navegador',
        };
        $system = match (true) {
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $system ? "{$browser} · {$system}" : $browser;
    }
}
