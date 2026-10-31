<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\JobTitle;
use App\Models\TerminationReason;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \LogicException('El seeder contiene datos demo. En producción usa php artisan blindem:admin.');
        }
        User::firstOrCreate(['email' => 'admin@blindem.test'], ['name' => 'Administrador Blindem', 'password' => 'Cambiar123!', 'role' => 'admin']);
        User::firstOrCreate(['email' => 'colaborador@blindem.test'], ['name' => 'Colaborador Blindem', 'password' => 'Cambiar123!', 'role' => 'collaborator']);
        $this->call(CatalogSeeder::class);
        $company = Company::firstOrCreate(['nit' => '900123456-7'], ['name' => 'Blindem Demo S.A.S.', 'email' => 'demo@blindem.test', 'address' => 'Popayán, Colombia']);
        $worker = Worker::firstOrCreate(['document_type_id' => DocumentType::where('code', 'CC')->value('id'), 'document_number' => '1098765432'], ['full_name' => 'Juan Carlos Pérez', 'email' => 'juan.perez@example.test']);
        Contract::firstOrCreate(['worker_id' => $worker->id, 'company_id' => $company->id, 'start_date' => '2023-01-15'], ['job_title_id' => JobTitle::first()->id, 'contract_type_id' => ContractType::where('code', 'indefinido')->value('id'), 'termination_reason_id' => TerminationReason::where('code', 'sin_justa_causa')->value('id'), 'end_date' => '2026-06-30', 'basic_salary' => 1750905, 'transport_allowance' => 249095, 'average_extras' => 150000, 'average_surcharges' => 0, 'salary_components_reviewed' => true, 'vacation_days_pending' => 15, 'pending_salary_days' => 15, 'pending_transport_days' => 15, 'cesantias_days' => 180, 'prima_days' => 180, 'wrongful_dismissal' => true, 'status' => 'terminated', 'created_by' => User::first()->id]);

        // Extra demo cases: both sheets of the reference Excel plus active, fixed-term and work contracts with automatic days.
        $id = fn (string $model, string $code) => $model::where('code', $code)->value('id');
        $job = fn (string $name) => JobTitle::where('name', $name)->value('id');
        $andinos = Company::firstOrCreate(['nit' => '901234567-1'], ['name' => 'Servicios Andinos S.A.S.', 'email' => 'nomina@andinos.test', 'address' => 'Cali, Colombia']);
        $cauca = Company::firstOrCreate(['nit' => '900765432-1'], ['name' => 'Logística del Cauca S.A.S.', 'email' => 'talento@logicauca.test', 'address' => 'Popayán, Colombia']);
        $maria = Worker::firstOrCreate(['document_type_id' => $id(DocumentType::class, 'CC'), 'document_number' => '1061712345'], ['full_name' => 'María Fernanda Gómez', 'email' => 'maria.gomez@example.test']);
        $carlos = Worker::firstOrCreate(['document_type_id' => $id(DocumentType::class, 'CE'), 'document_number' => '456789'], ['full_name' => 'Carlos Andrés Ruiz', 'email' => 'carlos.ruiz@example.test']);
        $laura = Worker::firstOrCreate(['document_type_id' => $id(DocumentType::class, 'PPT'), 'document_number' => '7654321'], ['full_name' => 'Laura Sofía Martínez', 'phone' => '3001234567']);
        $excelCase = ['basic_salary' => 1750905, 'transport_allowance' => 249095, 'average_extras' => 150000, 'average_surcharges' => 0, 'salary_components_reviewed' => true, 'vacation_days_pending' => 15, 'pending_salary_days' => 15, 'pending_transport_days' => 15, 'cesantias_days' => 180, 'prima_days' => 180, 'status' => 'terminated', 'created_by' => User::first()->id];
        $cases = [
            [['worker_id' => $maria->id, 'company_id' => $andinos->id, 'start_date' => '2023-01-15'], [...$excelCase, 'job_title_id' => $job('Analista operativo'), 'contract_type_id' => $id(ContractType::class, 'indefinido'), 'termination_reason_id' => $id(TerminationReason::class, 'renuncia'), 'end_date' => '2024-06-30', 'notes' => 'Reproduce la hoja 1 del formato Excel (sin indemnización).']],
            [['worker_id' => $carlos->id, 'company_id' => $cauca->id, 'start_date' => '2021-03-15'], [...$excelCase, 'job_title_id' => $job('Analista operativo'), 'contract_type_id' => $id(ContractType::class, 'indefinido'), 'termination_reason_id' => $id(TerminationReason::class, 'sin_justa_causa'), 'end_date' => '2024-06-30', 'wrongful_dismissal' => true, 'notes' => 'Reproduce la hoja 2 del formato Excel (despido sin justa causa).']],
            [['worker_id' => $laura->id, 'company_id' => $company->id, 'start_date' => '2025-08-01'], ['job_title_id' => $job('Coordinador'), 'contract_type_id' => $id(ContractType::class, 'indefinido'), 'basic_salary' => 2400000, 'transport_allowance' => 249095, 'average_surcharges' => 120000, 'vacation_days_taken' => 5, 'status' => 'active', 'notes' => 'Contrato activo: todos los días se calculan automáticamente a la fecha de hoy.']],
            [['worker_id' => $maria->id, 'company_id' => $company->id, 'start_date' => '2025-02-01'], ['job_title_id' => $job('Auxiliar administrativo'), 'contract_type_id' => $id(ContractType::class, 'fijo'), 'termination_reason_id' => $id(TerminationReason::class, 'sin_justa_causa'), 'end_date' => '2025-11-15', 'agreed_end_date' => '2026-01-31', 'basic_salary' => 1600000, 'transport_allowance' => 200000, 'wrongful_dismissal' => true, 'status' => 'terminated', 'notes' => 'Término fijo terminado antes del plazo: indemnización por días faltantes.']],
            [['worker_id' => $carlos->id, 'company_id' => $andinos->id, 'start_date' => '2026-03-01'], ['job_title_id' => $job('Ingeniero de sistemas'), 'contract_type_id' => $id(ContractType::class, 'obra'), 'basic_salary' => 3000000, 'transport_allowance' => 249095, 'average_extras' => 80000, 'status' => 'active', 'notes' => 'Obra o labor en curso.']],
        ];
        foreach ($cases as [$keys, $values]) {
            Contract::firstOrCreate($keys, ['salary_components_reviewed' => true, 'created_by' => User::first()->id, ...$values]);
        }
    }
}
