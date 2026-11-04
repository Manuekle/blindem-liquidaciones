<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = ['contracts', 'workers', 'settlements'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->uuid('public_id')->nullable()->unique());
            DB::table($table)->whereNull('public_id')->orderBy('id')->each(fn ($row) => DB::table($table)->where('id', $row->id)->update(['public_id' => (string) Str::uuid()]));
            Schema::table($table, fn (Blueprint $t) => $t->uuid('public_id')->nullable(false)->change());
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('public_id'));
        }
    }
};
