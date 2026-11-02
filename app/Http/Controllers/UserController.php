<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserController
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $users = User::when($search, fn ($q) => $q->where(fn ($q) => $q->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%")))
            ->when(! $request->has('sort'), fn ($q) => $q->orderByDesc('active')->orderBy('name'), fn ($q) => Table::sort($q, $request, ['name' => 'name', 'role' => 'role', 'status' => 'active', 'created' => 'created_at']))
            ->paginate(Table::perPage($request))->withQueryString();
        $summary = ['total' => User::count(), 'admins' => User::where('role', 'admin')->where('active', true)->count(), 'inactive' => User::where('active', false)->count()];

        return view('users.index', compact('users', 'search', 'summary'));
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'collaborator', 'active' => true])]);
    }

    public function edit(User $user)
    {
        return view('users.form', compact('user'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([...$this->rules(), 'password' => ['nullable', 'confirmed', PasswordRule::min(8)->letters()->numbers()]]);
        $invite = blank($data['password'] ?? null);
        // Without an initial password the account gets an unusable random one and a link to choose its own.
        $user = User::create([...$data, 'password' => $invite ? Str::random(40) : $data['password'], 'active' => true]);
        if ($invite) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return redirect()->route('users.index')->with('success', $invite ? "Usuario creado. Enviamos a {$user->email} un enlace para crear su contraseña." : 'Usuario creado.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules($user));
        if ($user->is($request->user()) && $data['role'] !== $user->role) {
            return back()->withInput()->withErrors(['role' => 'No puedes cambiar tu propio rol.']);
        }
        if ($this->isLastAdmin($user) && $data['role'] !== 'admin') {
            return back()->withInput()->withErrors(['role' => 'Debe quedar al menos un administrador activo.']);
        }
        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Usuario actualizado.');
    }

    public function toggle(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }
        if ($user->active && $this->isLastAdmin($user)) {
            return back()->with('error', 'Debe quedar al menos un administrador activo.');
        }
        $user->forceFill(['active' => ! $user->active, 'remember_token' => Str::random(60)])->save();
        if (! $user->active) {
            $user->endSessions();
        }

        return back()->with('success', $user->active ? "{$user->name} puede volver a iniciar sesión." : "{$user->name} ya no puede iniciar sesión.");
    }

    public function sendResetLink(User $user)
    {
        if (! $user->active) {
            return back()->with('error', 'Activa la cuenta antes de enviar un enlace de contraseña.');
        }
        $status = Password::sendResetLink(['email' => $user->email]);

        return back()->with(...$status === Password::RESET_THROTTLED
            ? ['error', 'Ya se envió un enlace hace poco. Espera un minuto antes de pedir otro.']
            : ['success', "Enviamos a {$user->email} un enlace para restablecer la contraseña."]);
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', Rule::in(['admin', 'collaborator'])],
        ];
    }

    private function isLastAdmin(User $user): bool
    {
        return $user->isAdmin() && $user->active && User::where('role', 'admin')->where('active', true)->count() === 1;
    }
}
