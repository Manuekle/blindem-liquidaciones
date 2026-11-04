<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') && ! config('app.allow_demo_seed')) {
            throw new \LogicException('El seeder contiene datos demo. En producción usa php artisan blindem:admin.');
        }
        // Demo accounts always end with the documented password, even if one was created earlier.
        User::updateOrCreate(['email' => 'admin@blindem.test'], ['name' => 'Administrador Blindem', 'password' => 'Cambiar123!', 'role' => 'admin']);
        User::updateOrCreate(['email' => 'colaborador@blindem.test'], ['name' => 'Colaborador Blindem', 'password' => 'Cambiar123!', 'role' => 'collaborator']);
        $this->call(CatalogSeeder::class);
        if (env('DEMO_DATASET') === 'excel') {
            // Only the two cases of the reference Excel; no extra demo contracts.
            $this->call(ExcelCaseSeeder::class);

            return;
        }
        $this->call(DemoContractsSeeder::class);
    }
}
