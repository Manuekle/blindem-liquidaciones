<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 'legal' = ajusted engine (default); 'template' = reproduces the Blindem Excel format exactly.
        Schema::table('contracts', fn (Blueprint $table) => $table->string('settlement_method', 20)->default('legal'));
    }

    public function down(): void
    {
        Schema::table('contracts', fn (Blueprint $table) => $table->dropColumn('settlement_method'));
    }
};
