<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->enum('role',['admin','collaborator'])->default('collaborator');$t->rememberToken();$t->timestamps();});
  Schema::create('companies',function(Blueprint $t){$t->id();$t->string('name');$t->string('nit')->unique();$t->string('email')->nullable();$t->string('phone')->nullable();$t->string('address')->nullable();$t->timestamps();});
  Schema::create('document_types',function(Blueprint $t){$t->id();$t->string('name');$t->string('code')->unique();$t->timestamps();});
  Schema::create('job_titles',function(Blueprint $t){$t->id();$t->string('name')->unique();$t->timestamps();});
  Schema::create('contract_types',function(Blueprint $t){$t->id();$t->string('name');$t->string('code')->unique();$t->timestamps();});
  Schema::create('termination_reasons',function(Blueprint $t){$t->id();$t->string('name');$t->string('code')->unique();$t->boolean('implies_indemnification')->default(false);$t->timestamps();});
  Schema::create('workers',function(Blueprint $t){$t->id();$t->foreignId('document_type_id')->constrained()->restrictOnDelete();$t->string('document_number');$t->string('full_name');$t->string('email')->nullable();$t->string('phone')->nullable();$t->timestamps();$t->unique(['document_type_id','document_number']);});
  Schema::create('contracts',function(Blueprint $t){$t->id();$t->foreignId('worker_id')->constrained()->restrictOnDelete();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('job_title_id')->constrained()->restrictOnDelete();$t->foreignId('contract_type_id')->constrained()->restrictOnDelete();$t->foreignId('termination_reason_id')->nullable()->constrained()->nullOnDelete();$t->date('start_date');$t->date('end_date')->nullable();$t->date('agreed_end_date')->nullable();$t->bigInteger('basic_salary');$t->bigInteger('transport_allowance')->default(0);$t->bigInteger('average_extras')->default(0);$t->unsignedSmallInteger('cesantias_days')->nullable();$t->unsignedSmallInteger('prima_days')->nullable();$t->decimal('vacation_days_pending',8,2)->default(0);$t->unsignedTinyInteger('pending_salary_days')->default(0);$t->unsignedTinyInteger('pending_transport_days')->default(0);$t->bigInteger('advances')->default(0);$t->bigInteger('other_deductions')->default(0);$t->boolean('wrongful_dismissal')->default(false);$t->bigInteger('manual_indemnification')->nullable();$t->string('notes',1000)->nullable();$t->enum('status',['active','terminated'])->default('active');$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();$t->index(['status','start_date']);});
  Schema::create('settlements',function(Blueprint $t){$t->id();$t->foreignId('contract_id')->constrained()->cascadeOnDelete();$t->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();$t->string('calculation_version')->default('1.0.0');$t->json('inputs');$t->json('result');$t->bigInteger('net_amount');$t->timestamps();});
 }
 public function down(): void {foreach(['settlements','contracts','workers','termination_reasons','contract_types','job_titles','document_types','companies','users'] as $name)Schema::dropIfExists($name);}
};
