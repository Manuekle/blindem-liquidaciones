<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4, 'ai.driver' => 'simulated']);
        $this->seed();
        $this->actingAs(User::where('role', 'collaborator')->firstOrFail());
    }

    private function ask(string $message, array $context = []): array
    {
        return $this->postJson('/ia/agente', ['message' => $message, 'context' => $context])->assertOk()->json('data');
    }

    public function test_agent_requires_login_and_a_message(): void
    {
        $this->postJson('/ia/agente', ['message' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('message');
        auth()->logout();
        $this->postJson('/ia/agente', ['message' => 'hola'])->assertUnauthorized();
    }

    public function test_every_page_shows_the_floating_assistant_with_its_context(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();

        $this->get("/contratos/{$contract->id}")->assertOk()
            ->assertSee('data-agent', false)
            ->assertSee('"page_contract":'.$contract->id, false)
            ->assertSee('js/agent.js', false)
            ->assertSee('js/sounds.js', false)
            ->assertSee('id="sound-toggle"', false);
    }

    public function test_greeting_lists_what_it_can_do(): void
    {
        $data = $this->ask('hola');

        $this->assertSame('help', $data['intent']);
        $this->assertSame('happy', $data['mood']);
        $this->assertNotEmpty($data['followups']);
    }

    public function test_pending_work_comes_from_the_portfolio_insights(): void
    {
        $data = $this->ask('¿Qué hay pendiente hoy?');

        $this->assertSame('insights', $data['intent']);
        $this->assertSame('surprised', $data['mood']);
        $this->assertStringContainsString('retiros sin liquidación', $data['items'][0]['title']);
        $this->assertStringContainsString('/contratos/', $data['items'][0]['href']);
    }

    public function test_search_runs_the_filters_and_links_to_the_list(): void
    {
        $data = $this->ask('busca los contratos retirados');

        $this->assertSame('search', $data['intent']);
        $this->assertCount(min(5, Contract::where('status', 'terminated')->count()), $data['items']); // the assistant lists at most five
        $this->assertStringEndsWith('/contratos?status=terminated', $data['actions'][0]['href']);
    }

    public function test_review_uses_the_contract_on_screen(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $contract->update(['salary_components_reviewed' => false]);

        $data = $this->ask('revisa este contrato', ['route' => 'contracts.show', 'page_contract' => $contract->id, 'contract' => $contract->id]);

        $this->assertSame('review', $data['intent']);
        $this->assertSame('Uso el contrato abierto', $data['steps'][1]['label']);
        $this->assertSame($contract->id, $data['focus']['contract']);
        $this->assertNotEmpty($data['items']);
    }

    public function test_ambiguous_names_get_a_question_back_and_the_choice_answers_it(): void
    {
        $data = $this->ask('explícame la liquidación de Carlos');
        $this->assertSame('clarify', $data['intent']);
        $this->assertSame('unsure', $data['mood']);
        $this->assertEqualsCanonicalizing(['Carlos Andrés Ruiz', 'Juan Carlos Pérez'], array_column($data['choices'], 'label'));

        // Juan Carlos has several contracts: choosing him asks which one, choosing a contract explains it.
        $juan = Worker::where('full_name', 'Juan Carlos Pérez')->firstOrFail();
        $this->assertSame('clarify', $this->ask('explícame la liquidación de Carlos', ['chosen_worker' => $juan->id])['intent']);
        $data = $this->ask('explícame la liquidación de Carlos', ['chosen_worker' => $juan->id, 'chosen_contract' => $juan->contracts()->value('id')]);
        $this->assertSame('explain', $data['intent']);
        $this->assertContains($data['focus']['contract'], $juan->contracts()->pluck('id')->all());
        $this->assertStringContainsString('Juan', $data['reply']);
        $this->assertNotEmpty($data['items']);
    }

    public function test_a_worker_with_several_contracts_is_asked_which_one(): void
    {
        $data = $this->ask('explícame la liquidación de María');
        $this->assertSame('¿Cuál contrato?', $data['reply']);
        $this->assertCount(2, $data['choices']);

        $data = $this->ask('explícame la liquidación de María', ['chosen_contract' => $data['choices'][0]['contract']]);
        $this->assertSame('explain', $data['intent']);
        $this->assertSame('Usé el contrato elegido', $data['steps'][1]['label']);
    }

    public function test_follow_up_questions_stay_on_the_same_contract_and_use_its_figures(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $this->post("/contratos/{$contract->id}/liquidacion");
        $saved = Settlement::firstOrFail();

        $data = $this->ask('¿Cuándo se debe pagar?', ['contract' => $contract->id, 'settlement' => $saved->id]);

        $this->assertSame('question', $data['intent']);
        $this->assertSame('Sigo con el mismo contrato', $data['steps'][1]['label']);
        $this->assertStringContainsString('mismo día', $data['reply']);
    }

    public function test_general_questions_are_answered_from_the_knowledge_base(): void
    {
        $data = $this->ask('¿Cuánto dura el periodo de prueba?');

        $this->assertSame('question', $data['intent']);
        $this->assertSame('Periodo de prueba', $data['sources'][0]['title']);
        $this->assertStringContainsString('[S1]', $data['reply']);
    }

    public function test_navigation_returns_a_link_to_the_requested_screen(): void
    {
        $laura = Worker::where('full_name', 'Laura Sofía Martínez')->firstOrFail();

        $data = $this->ask('abre la liquidación de Laura');

        $this->assertSame('navigate', $data['intent']);
        $this->assertStringEndsWith('/contratos/'.$laura->contracts()->value('id').'/liquidacion', $data['actions'][0]['href']);
    }

    public function test_without_a_contract_it_asks_who_instead_of_guessing(): void
    {
        $data = $this->ask('explica la liquidación');

        $this->assertSame('clarify', $data['intent']);
        $this->assertSame([], $data['choices']);
        $this->assertStringContainsString('¿De qué trabajador', $data['reply']);
    }

    public function test_general_questions_with_a_model_keep_the_source_checks(): void
    {
        config(['ai.driver' => 'ollama', 'ai.rag.embedding_model' => '']);
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => json_encode(['status' => 'answered', 'answer' => 'Dura máximo dos meses [S1] y se paga $9.999.999.', 'citations' => ['S1']])]])]);

        $data = $this->ask('¿Cuánto dura el periodo de prueba?');

        // The invented amount is not in any source, so the excerpt of the norm is shown instead.
        $this->assertTrue($data['fallback']);
        $this->assertStringNotContainsString('9.999.999', $data['reply']);
    }
}
