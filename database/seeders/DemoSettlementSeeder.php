<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\User;
use App\Services\LaborSettlementCalculator;
use App\Services\SettlementInputs;
use Illuminate\Database\Seeder;

class DemoSettlementSeeder extends Seeder
{
    // Saves one settlement per terminated demo contract that has none, so the dashboard starts with real figures.
    public function run(LaborSettlementCalculator $calculator, SettlementInputs $inputFactory): void
    {
        if (app()->environment('production') && ! config('app.allow_demo_seed')) {
            throw new \LogicException('El seeder contiene datos demo. En producción usa php artisan blindem:admin.');
        }
        $admin = User::where('email', 'admin@blindem.test')->firstOrFail();
        foreach (Contract::where('status', 'terminated')->doesntHave('settlements')->oldest('id')->get() as $contract) {
            try {
                $inputs = $inputFactory->fromContract($contract);
                $result = $calculator->calculate($inputs);
            } catch (\DomainException) {
                continue;
            }
            $contract->settlements()->create(['calculated_by' => $admin->id, 'calculation_version' => LaborSettlementCalculator::VERSION, 'inputs' => $inputs, 'result' => $result, 'net_amount' => $result['net']]);
        }
    }
}
