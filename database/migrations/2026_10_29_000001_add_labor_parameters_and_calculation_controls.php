<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labor_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->bigInteger('min_wage');
            $table->bigInteger('transport_allowance');
            $table->decimal('health_rate', 6, 5)->default(0.04);
            $table->decimal('pension_rate', 6, 5)->default(0.04);
            $table->decimal('interest_rate', 6, 5)->default(0.12);
            $table->string('wage_source', 500);
            $table->string('transport_source', 500);
            $table->timestamps();
        });

        $sources = [
            [2023, 1160000, 140606, '2613_2022', '2614_2022'],
            [2024, 1300000, 162000, '2292_2023', '2293_2023'],
            [2025, 1423500, 200000, '1572_2024', '1573_2024'],
            [2026, 1750905, 249095, '0159_2026', '1470_2025'],
        ];
        foreach ($sources as [$year, $wage, $transport, $wageDecree, $transportDecree]) {
            DB::table('labor_parameters')->insert([
                'year' => $year, 'min_wage' => $wage, 'transport_allowance' => $transport,
                'health_rate' => .04, 'pension_rate' => .04, 'interest_rate' => .12,
                'wage_source' => "https://normativa.colpensiones.gov.co/compilacion/docs/decreto_{$wageDecree}.htm",
                'transport_source' => "https://www.cancilleria.gov.co/normograma/compilacion/docs/decreto_{$transportDecree}.htm",
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('contract_types', function (Blueprint $table) {
            $table->string('calculation_method')->default('manual');
        });
        foreach (['indefinido', 'fijo', 'obra'] as $method) {
            DB::table('contract_types')->where('code', $method)->update(['calculation_method' => $method]);
        }
        Schema::table('contracts', function (Blueprint $table) {
            $table->bigInteger('average_surcharges')->default(0);
            $table->boolean('salary_components_reviewed')->default(true);
        });
        // Preserve the old combined amount; do not guess how much was overtime.
        DB::table('contracts')->where('average_extras', '>', 0)->update(['salary_components_reviewed' => false]);

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->foreign('contract_id')->references('id')->on('contracts')->restrictOnDelete();
        });
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['termination_reason_id']);
            $table->foreign('termination_reason_id')->references('id')->on('termination_reasons')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['termination_reason_id']);
            $table->foreign('termination_reason_id')->references('id')->on('termination_reasons')->nullOnDelete();
            $table->dropColumn(['average_surcharges', 'salary_components_reviewed']);
        });
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
        });
        Schema::dropIfExists('sessions');
        Schema::table('contract_types', fn (Blueprint $table) => $table->dropColumn('calculation_method'));
        Schema::dropIfExists('labor_parameters');
    }
};
