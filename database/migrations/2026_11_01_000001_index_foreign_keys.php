<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Postgres does not index foreign keys on its own; joins, withCount() and RESTRICT checks need them.
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->index('worker_id');
            $table->index('company_id');
            $table->index('job_title_id');
            $table->index('contract_type_id');
            $table->index('termination_reason_id');
            $table->index('created_by');
        });
        Schema::table('settlements', function (Blueprint $table) {
            // Also serves the contract history, ordered by id.
            $table->index(['contract_id', 'id']);
            $table->index('calculated_by');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropIndex(['calculated_by']);
            $table->dropIndex(['contract_id', 'id']);
        });
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['termination_reason_id']);
            $table->dropIndex(['contract_type_id']);
            $table->dropIndex(['job_title_id']);
            $table->dropIndex(['company_id']);
            $table->dropIndex(['worker_id']);
        });
    }
};
