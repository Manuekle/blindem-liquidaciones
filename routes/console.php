<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment('Blindem · Liquidaciones');
});
Artisan::command('blindem:admin {email} {--name=Administrador}', function () {
    $email = (string) $this->argument('email');
    $name = (string) $this->option('name');
    Validator::make(['email' => $email, 'name' => $name], ['email' => 'required|email|max:255', 'name' => 'required|string|max:255'])->validate();
    if (User::where('email', $email)->exists()) {
        $this->error('Ya existe una cuenta con ese correo. No se modificó.');

        return 1;
    }
    $password = $this->secret('Contraseña (mínimo 12 caracteres)');
    Validator::make(['password' => $password], ['password' => 'required|string|min:12'])->validate();
    if ($password !== $this->secret('Confirmar contraseña')) {
        $this->error('Las contraseñas no coinciden.');

        return 1;
    }
    User::create(['email' => $email, 'name' => $name, 'password' => $password, 'role' => 'admin']);
    $this->info('Administrador creado.');

    return 0;
})->purpose('Crear un administrador sin datos demo ni contraseñas en argumentos.');
