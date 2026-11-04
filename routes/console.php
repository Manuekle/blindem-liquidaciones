<?php

use App\Models\Company;
use App\Models\Contract;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
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

Artisan::command('blindem:excel-only {--force : Ejecutar sin confirmar}', function () {
    // Keeps only the two contracts of the reference Excel (Juan Carlos Pérez, 1098765432, retiro 2024-06-30).
    $keep = Contract::whereHas('worker', fn ($w) => $w->where('document_number', '1098765432'))
        ->whereDate('end_date', '2024-06-30')->whereIn('start_date', ['2023-01-15', '2021-03-15'])->where('settlement_method', 'template')->pluck('id');
    if ($keep->count() !== 2) {
        $this->error('Faltan los casos del Excel. Corre primero: php artisan db:seed --class=ExcelCaseSeeder');

        return 1;
    }
    $extra = Contract::whereNotIn('id', $keep)->with('worker')->get();
    foreach ($extra as $c) {
        $this->line("Se elimina contrato #{$c->id} de {$c->worker->full_name} ({$c->start_date->toDateString()}) con {$c->settlements()->count()} liquidación(es).");
    }
    if ($extra->isEmpty()) {
        $this->info('Solo existen los casos del Excel.');

        return 0;
    }
    if (! $this->option('force') && ! $this->confirm('¿Eliminar estos contratos y sus liquidaciones?')) {
        return 1;
    }
    DB::transaction(function () use ($extra, $keep) {
        Settlement::whereIn('contract_id', $extra->pluck('id'))->delete();
        Contract::whereIn('id', $extra->pluck('id'))->delete();
        Worker::whereDoesntHave('contracts')->delete();
        Company::whereDoesntHave('contracts')->delete();
    });
    $this->info('Quedan solo los casos del Excel: neto '.number_format(Settlement::sum('net_amount'), 0, ',', '.'));

    return 0;
})->purpose('Deja la base solo con los dos casos del Excel de prueba.');
