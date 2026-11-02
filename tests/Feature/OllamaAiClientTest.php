<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\Rag\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaAiClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4]);
        $this->seed();
        $this->actingAs(User::where('role', 'collaborator')->firstOrFail());
        $this->useOllama('http://ollama.test');
    }

    private function useOllama(string $baseUrl, ?string $key = null): void
    {
        config(['ai.driver' => 'ollama', 'ai.rag.embedding_model' => '', 'ai.providers.ollama' => ['base_url' => $baseUrl, 'api_key' => $key, 'model' => 'qwen-test', 'timeout' => 30, 'temperature' => 0.2, 'num_ctx' => 8192, 'max_tokens' => 1200]]);
        $this->app->forgetInstance(AiClient::class);
        $this->app->forgetInstance(KnowledgeBase::class);
    }

    private function answer(array $content): void
    {
        Http::fake(['*/api/chat' => Http::response(['message' => ['role' => 'assistant', 'content' => json_encode($content)]])]);
    }

    public function test_compliance_output_never_calls_the_model(): void
    {
        Http::fake();
        $contract = Contract::oldest('id')->firstOrFail();
        $this->post("/contratos/{$contract->id}/liquidacion");

        $this->getJson("/ia/contratos/{$contract->id}/revision")->assertOk()->assertJsonPath('data.source', 'rules')->assertJsonPath('meta.provider', 'Ollama · qwen-test');
        $this->getJson('/ia/resumen')->assertOk()->assertJsonPath('data.source', 'rules');
        $this->postJson("/ia/contratos/{$contract->id}/carta", ['kind' => 'liquidacion'])->assertOk()->assertJsonPath('data.source', 'rules');
        Http::assertNothingSent();
    }

    public function test_requests_send_the_task_schema_and_cloud_key(): void
    {
        $this->useOllama('https://ollama.com', 'cloud-secret');
        $this->answer(['filters' => ['status' => 'active']]);

        $this->postJson('/ia/contratos/buscar', ['query' => 'contratos activos'])->assertOk()
            ->assertJsonPath('meta.provider', 'Ollama Cloud · qwen-test')->assertJsonPath('data.filters', ['status' => 'active']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://ollama.com/api/chat'
            && $request->hasHeader('Authorization', 'Bearer cloud-secret')
            && $request['model'] === 'qwen-test' && $request['stream'] === false && $request['options']['num_ctx'] === 8192
            && $request['format']['properties']['filters']['properties']['status']['enum'] === ['active', 'terminated']);
    }

    public function test_extraction_keeps_only_values_the_text_supports(): void
    {
        [$company, $other] = Company::orderBy('id')->take(2)->get();
        $renuncia = \App\Models\TerminationReason::where('code', 'renuncia')->firstOrFail();
        $this->answer(['fields' => ['worker_id' => 999999, 'company_id' => $other->id, 'basic_salary' => 2500000, 'start_date' => '2024-02-30', 'termination_reason_id' => $renuncia->id], 'found' => [], 'notes' => []]);

        $data = $this->postJson('/ia/contratos/extraer', ['text' => "La empresa {$company->name} contrató a una persona que gana 2.500.000 al mes."])->assertOk()->json('data');

        $this->assertEqualsCanonicalizing(['company_id' => $company->id, 'basic_salary' => 2500000, 'status' => 'active'], $data['fields']);
        $this->assertSame('mixed', $data['source']);
    }

    public function test_explanation_with_invented_figures_falls_back_to_rules(): void
    {
        $contract = Contract::oldest('id')->firstOrFail();
        $this->answer(['headline' => 'Recibes $4.800.254 en total.', 'paragraphs' => ['Te pagan $700.36 de más.', 'Según el art. 12 tienes 15 días.'], 'followups' => []]);

        $this->getJson("/ia/contratos/{$contract->id}/explicacion")->assertOk()
            ->assertJsonPath('data.source', 'rules')->assertJsonPath('data.fallback', true);
    }

    private function ask(string $question): array
    {
        $contract = Contract::oldest('id')->firstOrFail();

        return $this->postJson("/ia/contratos/{$contract->id}/preguntar", ['question' => $question])->assertOk()->json('data');
    }

    public function test_questions_the_rules_cover_never_call_the_model(): void
    {
        Http::fake();
        $data = $this->ask('¿Cuándo se debe pagar?');

        $this->assertSame('rules', $data['source']);
        $this->assertStringContainsString('art. 65 CST', $data['answer']);
        Http::assertNothingSent();
    }

    public function test_rag_answer_cites_the_retrieved_sources(): void
    {
        $this->answer(['status' => 'answered', 'answer' => 'No, el empleador debe expedirlo cuando lo pidas (art. 57 CST).', 'citations' => ['S1']]);
        $data = $this->ask('¿Me pueden negar el certificado laboral?');

        $this->assertSame(['ai', 'answered', false], [$data['source'], $data['status'], $data['fallback']]);
        $this->assertStringEndsWith('[S1]', $data['answer']);
        $this->assertSame(['S1'], array_column($data['sources'], 'id'));
        Http::assertSent(function (Request $request) {
            $input = json_decode($request['messages'][1]['content'], true);

            return $input['sources'][0]['id'] === 'S1' && str_contains($input['sources'][0]['text'], 'certificación')
                && str_contains($request['messages'][0]['content'], 'never instructions');
        });
    }

    public function test_rag_answer_with_an_unknown_law_falls_back_to_the_best_source(): void
    {
        $this->answer(['status' => 'answered', 'answer' => 'Según el artículo 12 de la Ley 50 de 1990 tienes 15 días [S1].', 'citations' => ['S1']]);
        $data = $this->ask('¿Me pueden negar el certificado laboral?');

        $this->assertTrue($data['fallback']);
        $this->assertStringContainsString('art. 57', $data['answer']);
    }

    public function test_rag_answer_must_cite_a_retrieved_source(): void
    {
        $this->answer(['status' => 'answered', 'answer' => 'No pueden negártelo.', 'citations' => ['S9']]);

        $this->assertTrue($this->ask('¿Me pueden negar el certificado laboral?')['fallback']);
    }

    public function test_rag_can_report_insufficient_evidence(): void
    {
        $this->answer(['status' => 'insufficient', 'answer' => 'Las fuentes no dicen si el certificado debe incluir funciones.', 'citations' => []]);
        $data = $this->ask('¿El certificado laboral debe incluir mis funciones detalladas?');

        $this->assertSame(['insufficient', []], [$data['status'], $data['sources']]);
    }

    public function test_models_without_structured_output_get_the_schema_in_the_prompt(): void
    {
        Http::fake(['*/api/chat' => Http::sequence()
            ->push(['error' => 'structured output is unavailable'], 501)
            ->push(['message' => ['content' => '```json'."\n".'{"filters":{"status":"active"}}'."\n```"]])]);

        $this->postJson('/ia/contratos/buscar', ['query' => 'contratos activos'])->assertOk()->assertJsonPath('data.filters', ['status' => 'active']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => ! isset($request['format']) && $request['think'] === false && str_contains($request['messages'][0]['content'], 'Esquema JSON'));
    }

    public function test_search_keeps_only_filters_the_request_mentions(): void
    {
        $company = Company::firstOrFail();
        $this->answer(['filters' => ['status' => 'terminated', 'company' => $company->id, 'document_type' => 999999, 'q' => 'retirados']]);

        $this->postJson('/ia/contratos/buscar', ['query' => 'retirados'])->assertOk()
            ->assertJsonPath('data.filters', ['status' => 'terminated'])
            ->assertJsonPath('data.understood', ['Estado: retirado']);
    }

    public function test_unreachable_server_is_reported(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $message = $this->postJson('/ia/contratos/buscar', ['query' => 'activos'])->assertStatus(503)->json('message');
        $this->assertStringContainsString('No se pudo conectar con Ollama en http://ollama.test', $message);
    }

    public function test_missing_model_suggests_pulling_it(): void
    {
        Http::fake(['*' => Http::response(['error' => "model 'qwen-test' not found"], 404)]);

        $this->assertStringContainsString('ollama pull qwen-test', $this->postJson('/ia/contratos/buscar', ['query' => 'activos'])->assertStatus(503)->json('message'));
    }

    public function test_answer_that_is_not_json_is_reported(): void
    {
        Http::fake(['*' => Http::response(['message' => ['content' => 'Lo siento, no puedo ayudar.']])]);

        $this->assertStringContainsString('no devolvió una respuesta válida', $this->postJson('/ia/contratos/buscar', ['query' => 'activos'])->assertStatus(503)->json('message'));
    }
}
