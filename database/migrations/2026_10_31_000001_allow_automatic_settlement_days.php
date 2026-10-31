<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Null means "derive from the contract dates"; an explicit number (including 0) is kept as typed.
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('vacation_days_pending', 8, 2)->nullable()->default(null)->change();
            $table->unsignedTinyInteger('pending_salary_days')->nullable()->default(null)->change();
            $table->unsignedTinyInteger('pending_transport_days')->nullable()->default(null)->change();
            $table->decimal('vacation_days_taken', 8, 2)->default(0);
        });
    }

    public function down(): void
    {
        foreach (['vacation_days_pending', 'pending_salary_days', 'pending_transport_days'] as $column) {
            DB::table('contracts')->whereNull($column)->update([$column => 0]);
        }
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('vacation_days_taken');
            $table->decimal('vacation_days_pending', 8, 2)->nullable(false)->default(0)->change();
            $table->unsignedTinyInteger('pending_salary_days')->nullable(false)->default(0)->change();
            $table->unsignedTinyInteger('pending_transport_days')->nullable(false)->default(0)->change();
        });
    }
};
