<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\LaborParameter;
use App\Models\Settlement;
use App\Models\TerminationReason;
use App\Models\User;
use App\Models\Worker;
use App\Services\LaborSettlementCalculator;
use App\Services\SettlementInputs;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SettlementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4]);
        $this->seed();
    }

    private function collaborator(): User
    {
        return User::where('role', 'collaborator')->firstOrFail();
    }

    private function payload(Contract $contract): array
    {
        $data = array_intersect_key($contract->getAttributes(), array_flip([
            'worker_id', 'company_id', 'job_title_id', 'contract_type_id', 'termination_reason_id',
            'start_date', 'end_date', 'agreed_end_date', 'basic_salary', 'transport_allowance',
            'average_extras', 'average_surcharges', 'cesantias_days', 'prima_days', 'vacation_days_pending',
            'pending_salary_days', 'pending_transport_days', 'advances', 'other_deductions',
            'manual_indemnification', 'status', 'notes', 'salary_components_reviewed',
        ]));
        foreach (['start_date', 'end_date', 'agreed_end_date'] as $field) {
            $data[$field] = $contract->$field?->toDateString();
        }

        return $data;
    }

    public function test_retirement_reason_activates_indemnification_without_checkbox(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $before = Contract::count();
        $this->actingAs($this->collaborator())->post('/contratos', $this->payload($contract))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before + 1, Contract::count());
        $created = Contract::latest('id')->firstOrFail();
        $this->assertTrue($created->wrongful_dismissal);
        $this->post("/contratos/{$created->id}/liquidacion")->assertRedirect();
        $this->assertGreaterThan(0, $created->settlements()->firstOrFail()->result['indemnification']);
    }

    public function test_non_indemnifying_reason_cannot_be_overridden_by_checkbox(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $reason = TerminationReason::where('code', 'renuncia')->firstOrFail();
        $this->actingAs($this->collaborator())->post('/contratos', [
            ...$this->payload($contract), 'termination_reason_id' => $reason->id, 'wrongful_dismissal' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $created = Contract::latest('id')->firstOrFail();
        $inputs = app(SettlementInputs::class)->fromContract($created);
        $this->assertFalse($inputs['wrongful_dismissal']);
        $this->assertSame(0, app(LaborSettlementCalculator::class)->calculate($inputs)['indemnification']);
    }

    public function test_historical_year_selects_its_own_salary_threshold(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $contract->update(['start_date' => '2024-01-01', 'end_date' => '2024-06-30', 'basic_salary' => 14000000, 'average_extras' => 0]);
        $inputs = app(SettlementInputs::class)->fromContract($contract->fresh());
        $result = app(LaborSettlementCalculator::class)->calculate($inputs);
        $this->assertSame(2024, $inputs['parameter_year']);
        $this->assertSame(1300000, $inputs['min_wage']);
        $this->assertSame(20.0, $result['indemnification_days']);
    }

    public function test_missing_year_blocks_save_instead_of_reusing_current_parameters(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $contract->update(['end_date' => '2027-06-30']);
        $this->actingAs($this->collaborator())->from("/contratos/{$contract->id}/liquidacion")
            ->post("/contratos/{$contract->id}/liquidacion")->assertSessionHas('error');
        $this->assertSame(0, Settlement::count());
        $this->get("/contratos/{$contract->id}/liquidacion")->assertOk()->assertSee('No hay parámetros laborales para 2027');
    }

    public function test_legacy_combined_salary_needs_review_without_altering_existing_value(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $contract->update(['salary_components_reviewed' => false]);
        $this->actingAs($this->collaborator())->get("/contratos/{$contract->id}/liquidacion")
            ->assertOk()->assertSee('Revisa y confirma');
        $this->putJson("/contratos/{$contract->id}", [...$this->payload($contract), 'salary_components_reviewed' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('salary_components_reviewed');
        $this->assertSame(150000, (int) $contract->fresh()->average_extras);
        $this->put("/contratos/{$contract->id}", [...$this->payload($contract), 'salary_components_reviewed' => true])->assertRedirect();
        $this->get("/contratos/{$contract->id}/liquidacion")->assertOk()->assertSee('Neto a recibir');
    }

    public function test_snapshot_html_and_excel_survive_changes_to_contract_identity_and_parameters(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $contract->worker->update(['full_name' => '=2+2', 'document_number' => '0012345']);
        $this->actingAs($this->collaborator())->post("/contratos/{$contract->id}/liquidacion")->assertRedirect();
        $snapshot = Settlement::firstOrFail();
        $savedInputs = $snapshot->inputs;
        $savedResult = $snapshot->result;
        $contract->update(['basic_salary' => 7000000]);
        $contract->worker->update(['full_name' => 'Nombre cambiado', 'document_number' => '99999']);
        $contract->company->update(['name' => 'Empresa cambiada']);
        LaborParameter::where('year', 2026)->update(['min_wage' => 9000000, 'health_rate' => .03]);

        $this->get("/contratos/{$contract->id}/liquidaciones/{$snapshot->id}")
            ->assertOk()->assertSee('=2+2')->assertDontSee('Nombre cambiado')->assertDontSee('Empresa cambiada');
        $response = $this->get("/contratos/{$contract->id}/liquidaciones/{$snapshot->id}/excel")->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'blindem-xlsx-');
        try {
            file_put_contents($path, $response->streamedContent());
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame('=2+2', $sheet->getCell('B4')->getValue());
            $this->assertSame('s', $sheet->getCell('B4')->getDataType());
            $this->assertSame('CC 0012345', $sheet->getCell('D4')->getValue());
            $this->assertSame($savedResult['net'], $sheet->getCell('B26')->getValue());
            $this->assertSame(1750905, $workbook->getSheet(1)->getCell('B4')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->assertSame($savedInputs, $snapshot->fresh()->inputs);
        $this->assertSame($savedResult, $snapshot->fresh()->result);
    }

    public function test_contract_with_history_cannot_be_deleted(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $this->actingAs($this->collaborator())->post("/contratos/{$contract->id}/liquidacion");
        $this->from("/contratos/{$contract->id}")->delete("/contratos/{$contract->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('contracts', ['id' => $contract->id]);
        $this->assertSame(1, Settlement::count());
    }

    public function test_snapshot_id_cannot_be_accessed_through_another_contract(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $this->actingAs($this->collaborator())->post("/contratos/{$contract->id}/liquidacion");
        $snapshot = Settlement::firstOrFail();
        $other = $contract->replicate();
        $other->save();
        $this->get("/contratos/{$other->id}/liquidaciones/{$snapshot->id}")->assertNotFound();
        $this->get("/contratos/{$other->id}/liquidaciones/{$snapshot->id}/excel")->assertNotFound();
    }

    public function test_old_snapshots_remain_accessible_even_when_current_contract_cannot_be_calculated(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $this->actingAs($this->collaborator())->post("/contratos/{$contract->id}/liquidacion");
        $snapshot = Settlement::firstOrFail();
        $inputs = $snapshot->inputs;
        unset($inputs['context']);
        $snapshot->update(['inputs' => $inputs]);
        $contract->update(['salary_components_reviewed' => false]);
        $this->get("/contratos/{$contract->id}/liquidaciones/{$snapshot->id}")
            ->assertOk()->assertSee('Esta versión antigua no guardó la identificación');
        $this->get("/contratos/{$contract->id}/liquidacion")->assertOk()->assertSee('Ver cálculo');
        $this->get("/contratos/{$contract->id}/liquidaciones/{$snapshot->id}/excel")->assertOk();
    }

    public function test_custom_contract_code_uses_explicit_method(): void
    {
        $type = ContractType::where('code', 'indefinido')->firstOrFail();
        $type->update(['code' => 'codigo-personalizado']);
        $contract = Contract::oldest('id')->firstOrFail();
        $inputs = app(SettlementInputs::class)->fromContract($contract);
        $this->assertSame('indefinido', $inputs['contract_type']);
        $this->assertGreaterThan(0, app(LaborSettlementCalculator::class)->calculate($inputs)['indemnification']);
    }

    public function test_new_manual_type_rejects_missing_indemnification(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $type = ContractType::create(['code' => 'otro', 'name' => 'Otro contrato', 'calculation_method' => 'manual']);
        $this->actingAs($this->collaborator())->postJson('/contratos', [...$this->payload($contract), 'contract_type_id' => $type->id])
            ->assertUnprocessable()->assertJsonValidationErrors('liquidacion');
    }

    public function test_admin_manages_annual_parameters_but_cannot_delete_year_in_use(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $data = ['year' => 2027, 'min_wage' => 2000000, 'transport_allowance' => 300000,
            'health_percent' => 4, 'pension_percent' => 4, 'interest_percent' => 12,
            'wage_source' => 'https://example.gov.co/salario', 'transport_source' => 'https://example.gov.co/transporte'];
        $this->actingAs($admin)->from('/parametros-laborales')->post('/parametros-laborales', $data)->assertSessionHas('success');
        $parameter = LaborParameter::where('year', 2027)->firstOrFail();
        $this->put("/parametros-laborales/{$parameter->id}", [...$data, 'min_wage' => 2100000])->assertSessionHas('success');
        $this->assertSame(2100000, $parameter->fresh()->min_wage);
        $this->delete("/parametros-laborales/{$parameter->id}")->assertSessionHas('success');
        $used = LaborParameter::where('year', 2026)->firstOrFail();
        $this->delete("/parametros-laborales/{$used->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('labor_parameters', ['id' => $used->id]);
        $this->get('/parametros-laborales')->assertOk();
    }

    public function test_collaborator_cannot_change_parameters_or_catalogs(): void
    {
        $this->actingAs($this->collaborator());
        $this->get('/parametros-laborales')->assertForbidden();
        $this->post('/parametros-laborales', [])->assertForbidden();
        $this->put('/parametros-laborales/1', [])->assertForbidden();
        $this->delete('/parametros-laborales/1')->assertForbidden();
        $this->post('/catalogos/contratos', [])->assertForbidden();
    }

    public function test_admin_cannot_delete_used_reason(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $this->actingAs(User::where('role', 'admin')->firstOrFail())->from('/catalogos/retiros')
            ->delete('/catalogos/retiros/'.$contract->termination_reason_id)->assertSessionHas('error');
        $this->assertNotNull($contract->fresh()->terminationReason);
    }

    public function test_safe_admin_command_hashes_password_and_does_not_create_demo_data(): void
    {
        $this->artisan('blindem:admin', ['email' => 'nuevo@example.test', '--name' => 'Nueva administradora'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'ClaveSegura123!')
            ->expectsQuestion('Confirmar contraseña', 'ClaveSegura123!')
            ->assertSuccessful();
        $user = User::where('email', 'nuevo@example.test')->firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertTrue(Hash::check('ClaveSegura123!', $user->password));
        $this->artisan('blindem:admin', ['email' => $user->email])->assertFailed();
    }

    public function test_search_filters_work_on_the_configured_database(): void
    {
        $this->actingAs($this->collaborator())->get('/contratos?status=terminated&document=1098765432&q=Juan')
            ->assertOk()->assertSee('Juan Carlos Pérez');
        $this->get('/contratos?document=000000')->assertOk()->assertSee('No hay contratos');
        $this->get('/trabajadores?q=juan')->assertOk()->assertSee('Juan Carlos Pérez')->assertDontSee('Laura Sofía Martínez');
        $this->get('/trabajadores?q=456789')->assertOk()->assertSee('Carlos Andrés Ruiz');
        $this->get('/empresas?q=andinos')->assertOk()->assertSee('Servicios Andinos')->assertDontSee('Logística del Cauca');
    }

    public function test_all_operational_forms_render_for_each_role(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $id = $contract->id;
        $worker = $contract->worker_id;
        $this->actingAs($this->collaborator());
        foreach (['/', '/contratos', '/contratos/nuevo', "/contratos/{$id}", "/contratos/{$id}/editar", '/trabajadores', "/trabajadores/{$worker}", '/empresas'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        foreach (['/catalogos/documentos', '/catalogos/cargos', '/catalogos/contratos', '/catalogos/retiros', '/parametros-laborales'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_collaborator_registers_and_edits_workers_but_cannot_delete_them(): void
    {
        $this->actingAs($this->collaborator());
        $document = DocumentType::where('code', 'CC')->value('id');
        $this->get('/trabajadores/nuevo/crear')->assertOk();
        $this->post('/trabajadores', ['full_name' => 'Ana Torres', 'document_type_id' => $document, 'document_number' => '55555'])->assertRedirect()->assertSessionHasNoErrors();
        $worker = Worker::where('document_number', '55555')->firstOrFail();
        $this->put("/trabajadores/{$worker->id}", ['full_name' => 'Ana María Torres', 'document_type_id' => $document, 'document_number' => '55555'])->assertRedirect();
        $this->assertSame('Ana María Torres', $worker->fresh()->full_name);
        $this->get("/contratos/nuevo?worker={$worker->id}")->assertOk()->assertSee('value="'.$worker->id.'" selected', false);
        $this->delete("/trabajadores/{$worker->id}")->assertForbidden();
    }

    public function test_contract_with_blank_days_is_saved_as_automatic(): void
    {
        $payload = [...$this->payload(Contract::oldest('id')->firstOrFail()), 'vacation_days_pending' => '', 'pending_salary_days' => '', 'pending_transport_days' => '', 'vacation_days_taken' => '3'];
        $this->actingAs($this->collaborator())->post('/contratos', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $created = Contract::latest('id')->firstOrFail();
        $this->assertNull($created->pending_salary_days);
        $this->get("/contratos/{$created->id}/liquidacion")->assertOk()->assertSee('auto desde la fecha de corte');
    }

    public function test_contract_without_history_can_still_be_deleted(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $this->actingAs($this->collaborator())->delete("/contratos/{$contract->id}")->assertRedirect('/contratos');
        $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
    }

    public function test_admin_must_associate_a_calculation_method_with_a_new_type(): void
    {
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        $data = ['code' => 'nuevo', 'name' => 'Nuevo tipo'];
        $this->postJson('/catalogos/contratos', $data)->assertUnprocessable()->assertJsonValidationErrors('calculation_method');
        $this->post('/catalogos/contratos', [...$data, 'calculation_method' => 'manual'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contract_types', [...$data, 'calculation_method' => 'manual']);
    }

    public function test_production_catalog_seed_does_not_create_users_or_contracts(): void
    {
        User::query()->delete();
        $contracts = Contract::count();
        app()->instance('env', 'production');
        $this->artisan('db:seed', ['--class' => CatalogSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertSame(0, User::count());
        $this->assertSame($contracts, Contract::count());
    }

    public function test_demo_seed_is_blocked_in_production(): void
    {
        app()->instance('env', 'production');
        $this->expectException(\LogicException::class);
        (new DatabaseSeeder)->run();
    }
}
