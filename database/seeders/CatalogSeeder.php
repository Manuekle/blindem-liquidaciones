<?php

namespace Database\Seeders;

use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\JobTitle;
use App\Models\TerminationReason;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['CC' => 'Cédula de ciudadanía', 'CE' => 'Cédula de extranjería', 'TI' => 'Tarjeta de identidad', 'PAS' => 'Pasaporte', 'PPT' => 'Permiso por Protección Temporal'] as $code => $name) {
            DocumentType::firstOrCreate(['code' => $code], ['name' => $name]);
        }
        foreach (['Analista operativo', 'Auxiliar administrativo', 'Contador', 'Ingeniero de sistemas', 'Asesor comercial', 'Coordinador'] as $name) {
            JobTitle::firstOrCreate(['name' => $name]);
        }
        foreach (['indefinido' => 'Término indefinido', 'fijo' => 'Término fijo', 'obra' => 'Obra o labor'] as $code => $name) {
            ContractType::firstOrCreate(['code' => $code], ['name' => $name, 'calculation_method' => $code]);
        }
        foreach (['mutuo_acuerdo' => ['Mutuo acuerdo', false], 'renuncia' => ['Renuncia voluntaria', false], 'sin_justa_causa' => ['Despido sin justa causa', true], 'justa_causa' => ['Despido con justa causa', false], 'vencimiento' => ['Vencimiento del término', false]] as $code => [$name,$flag]) {
            TerminationReason::firstOrCreate(['code' => $code], ['name' => $name, 'implies_indemnification' => $flag]);
        }
    }
}
