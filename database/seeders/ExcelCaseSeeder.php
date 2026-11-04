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
use App\Services\LaborSettlementCalculator;
use App\Services\SettlementInputs;
use Illuminate\Database\Seeder;

// The two cases of "Formato_Liquidacion_e_Indemnizacion_Prueba Blindem.xlsx" with the template method.
// Safe for production: creates no users and is idempotent. Needs an existing admin to own the settlements.
class ExcelCaseSeeder extends Seeder
{
    public function run(LaborSettlementCalculator $calculator, SettlementInputs $inputFactory): void
    {
        $owner = User::where('role', 'admin')->oldest('id')->first() ?? User::oldest('id')->first();
        if (! $owner) {
            $this->command?->warn('Sin usuarios: crea el administrador antes de cargar los casos del Excel.');

            return;
        }
        $this->call(CatalogSeeder::class);
        $company = Company::firstOrCreate(['nit' => '900123456-7'], ['name' => 'Blindem Demo S.A.S.', 'email' => 'demo@blindem.test', 'address' => 'Popayán, Colombia']);
        $worker = Worker::firstOrCreate(['document_type_id' => DocumentType::where('code', 'CC')->value('id'), 'document_number' => '1098765432'], ['full_name' => 'Juan Carlos Pérez', 'email' => 'juan.perez@example.test']);
        $common = ['job_title_id' => JobTitle::where('name', 'Analista operativo')->value('id'), 'contract_type_id' => ContractType::where('code', 'indefinido')->value('id'), 'end_date' => '2024-06-30', 'basic_salary' => 1750905, 'transport_allowance' => 249095, 'average_extras' => 150000, 'average_surcharges' => 0, 'salary_components_reviewed' => true, 'vacation_days_pending' => 15, 'pending_salary_days' => 15, 'pending_transport_days' => 15, 'cesantias_days' => 180, 'prima_days' => 180, 'status' => 'terminated', 'settlement_method' => 'template', 'created_by' => $owner->id];
        $cases = [
            ['2023-01-15', 'vencimiento', false, 'Prueba Blindem, hoja 1 (método plantilla): neto $3.180.899.'],
            ['2021-03-15', 'sin_justa_causa', true, 'Prueba Blindem, hoja 2 (método plantilla): neto $7.614.926.'],
        ];
        foreach ($cases as [$start, $reason, $dismissal, $notes]) {
            $contract = Contract::firstOrCreate(['worker_id' => $worker->id, 'company_id' => $company->id, 'start_date' => $start, 'end_date' => '2024-06-30'], [...$common, 'termination_reason_id' => TerminationReason::where('code', $reason)->value('id'), 'wrongful_dismissal' => $dismissal, 'notes' => $notes]);
            if ($contract->settlements()->exists()) {
                continue;
            }
            $inputs = $inputFactory->fromContract($contract);
            $result = $calculator->calculate($inputs);
            $contract->settlements()->create(['calculated_by' => $owner->id, 'calculation_version' => LaborSettlementCalculator::VERSION, 'inputs' => $inputs, 'result' => $result, 'net_amount' => $result['net']]);
        }
    }
}
