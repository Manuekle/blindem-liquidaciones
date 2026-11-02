<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4, 'ai.driver' => 'simulated']);
        $this->seed();
        $this->actingAs(User::where('role', 'collaborator')->firstOrFail());
    }

    public function test_assistant_requires_login(): void
    {
        auth()->logout();
        $this->get('/ia/resumen')->assertRedirect('/login');
    }

    public function test_extraction_maps_free_text_to_contract_fields(): void
    {
        $contract = Contract::with('worker.documentType', 'company')->oldest('id')->firstOrFail();
        $text = "{$contract->worker->full_name}, {$contract->worker->documentType->code} {$contract->worker->document_number}, ingresó el 15 de enero de 2024 a {$contract->company->name} con contrato a término indefinido. Devenga un salario de $2.500.000. Presentó renuncia y su último día fue el 30/06/2025.";

        $fields = $this->postJson('/ia/contratos/extraer', ['text' => $text])->assertOk()->json('data.fields');

        $this->assertSame($contract->worker_id, $fields['worker_id']);
        $this->assertSame($contract->company_id, $fields['company_id']);
        $this->assertSame('2024-01-15', $fields['start_date']);
        $this->assertSame('2025-06-30', $fields['end_date']);
        $this->assertSame(2500000, $fields['basic_salary']);
        $this->assertSame('terminated', $fields['status']);
    }

    public function test_review_flags_fixed_term_problems(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $fixed = ContractType::where('calculation_method', 'fijo')->firstOrFail();
        $contract->update(['contract_type_id' => $fixed->id, 'agreed_end_date' => null]);
        $review = $this->getJson("/ia/contratos/{$contract->id}/revision")->assertOk()->assertJsonPath('data.status', 'attention')->json('data');
        $this->assertContains('Término fijo sin fecha pactada', array_column($review['findings'], 'title'));

        $contract->update(['status' => 'active', 'end_date' => null, 'termination_reason_id' => null, 'agreed_end_date' => now()->addDays(20)->toDateString()]);
        $titles = array_column($this->getJson("/ia/contratos/{$contract->id}/revision")->assertOk()->json('data.findings'), 'title');
        $this->assertContains('El contrato vence en 20 días', $titles);
    }

    public function test_explanation_and_answers_use_the_settlement_figures(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $this->post("/contratos/{$contract->id}/liquidacion");
        $net = Settlement::firstOrFail()->net_amount;

        $this->getJson("/ia/contratos/{$contract->id}/explicacion")->assertOk()
            ->assertSee('$'.number_format($net, 0, ',', '.'))->assertJsonPath('meta.provider', 'Simulado');
        $this->postJson("/ia/contratos/{$contract->id}/preguntar", ['question' => '¿Cuándo se debe pagar?'])
            ->assertOk()->assertSee('art. 65 CST');
    }

    public function test_questions_outside_the_rules_are_answered_from_the_knowledge_base(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $ask = fn (string $question) => $this->postJson("/ia/contratos/{$contract->id}/preguntar", ['question' => $question])->assertOk()->json('data');

        $data = $ask('¿Me pueden negar el certificado laboral?');
        $this->assertSame('answered', $data['status']);
        $this->assertSame('Certificado laboral', $data['sources'][0]['title']);
        $this->assertStringContainsString('[S1]', $data['answer']);

        // "cuánto" would match the rule about the net amount; retrieval routes it to the right note.
        $data = $ask('¿Cuánto dura el periodo de prueba?');
        $this->assertSame('Periodo de prueba', $data['sources'][0]['title']);
        $this->assertStringNotContainsString('neto', $data['answer']);

        $data = $ask('¿Cuál es la capital de Francia?');
        $this->assertSame('insufficient', $data['status']);
        $this->assertSame([], $data['sources']);
    }

    public function test_blocked_calculation_is_reported_instead_of_explained(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $contract->update(['salary_components_reviewed' => false]);
        $this->getJson("/ia/contratos/{$contract->id}/explicacion")->assertUnprocessable()->assertSee('Revisa y confirma');
    }

    public function test_letter_uses_a_saved_settlement_of_the_same_contract_only(): void
    {
        [$first, $other] = Contract::oldest('id')->take(2)->get();
        $this->post("/contratos/{$first->id}/liquidacion");
        $saved = Settlement::firstOrFail();

        $this->postJson("/ia/contratos/{$first->id}/carta?settlement={$saved->id}", ['kind' => 'paz_y_salvo'])->assertOk()
            ->assertJsonPath('data.subject', 'Paz y salvo laboral — '.$first->worker->full_name);
        $this->postJson("/ia/contratos/{$other->id}/carta?settlement={$saved->id}", ['kind' => 'liquidacion'])->assertNotFound();
    }

    public function test_search_turns_a_sentence_into_filters(): void
    {
        $company = Company::orderBy('id')->firstOrFail();
        $filters = $this->postJson('/ia/contratos/buscar', ['query' => 'retirados de '.$company->name])->assertOk()->json('data.filters');
        $this->assertSame(['status' => 'terminated', 'company' => $company->id], $filters);
    }

    public function test_dashboard_insights_point_to_terminated_contracts_without_settlement(): void
    {
        $insights = $this->getJson('/ia/resumen')->assertOk()->json('data.insights');
        $this->assertNotEmpty(array_filter($insights, fn ($insight) => str_contains($insight['title'], 'sin liquidación guardada')));
    }

    public function test_unconfigured_provider_fails_with_a_clear_message(): void
    {
        config(['ai.driver' => 'externo']);
        $this->app->forgetInstance(\App\Services\Ai\AiClient::class);
        $this->assertStringContainsString('no está configurado', $this->postJson('/ia/contratos/buscar', ['query' => 'activos'])->assertStatus(503)->json('message'));
    }
}
