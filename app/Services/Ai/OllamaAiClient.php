<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Ollama's chat API, either a local server or Ollama Cloud (same API plus a bearer key).
 * Each task sends its JSON Schema as `format`, so the model answers with structured output. Models
 * that do not support it (Ollama answers 501) get the schema in the prompt instead.
 */
class OllamaAiClient implements AiClient
{
    /** Models that rejected a JSON Schema in `format` (MLX and some newer engines); they get it in the prompt. */
    private array $withoutStructuredOutput = [];

    public function __construct(private array $config) {}

    public function run(string $task, string $instructions, array $input, array $schema = []): array
    {
        $baseUrl = rtrim((string) $this->config['base_url'], '/');
        $model = (string) $this->config['model'];
        $timeout = max(5, (int) ($this->config['timeout'] ?? 120));
        // Local models can take longer than PHP's default request limit.
        @set_time_limit($timeout + 15);

        $structured = ! isset($this->withoutStructuredOutput[$baseUrl.'|'.$model]);
        $response = $this->send($baseUrl, $model, $timeout, $instructions, $input, $schema, $structured);
        if ($structured && $response->status() === 501 && str_contains((string) $response->json('error'), 'structured output')) {
            $this->withoutStructuredOutput[$baseUrl.'|'.$model] = true;
            $response = $this->send($baseUrl, $model, $timeout, $instructions, $input, $schema, false);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new AiUnavailable('Ollama rechazó la solicitud: revisa OLLAMA_API_KEY.');
        }
        if ($response->status() === 404) {
            throw new AiUnavailable("El modelo {$model} no está disponible en Ollama. Descárgalo con «ollama pull {$model}».");
        }
        if ($response->failed()) {
            throw new AiUnavailable('Ollama respondió con un error ('.$response->status().'): '.($response->json('error') ?? 'sin detalle').'.');
        }

        $content = (string) $response->json('message.content');
        $data = json_decode($content, true);
        if (! is_array($data) && preg_match('/\{.*\}/s', $content, $match)) {
            $data = json_decode($match[0], true);
        }
        if (! is_array($data)) {
            throw new AiUnavailable('El modelo no devolvió una respuesta válida. Inténtalo de nuevo o usa un modelo más capaz.');
        }

        return $data;
    }

    private function send(string $baseUrl, string $model, int $timeout, string $instructions, array $input, array $schema, bool $structured): Response
    {
        $system = trim($instructions)."\n\nResponde siempre en español de Colombia. Devuelve únicamente JSON válido que cumpla el esquema, sin texto adicional.";
        if (! $structured && $schema) {
            $system .= "\nEsquema JSON de la respuesta:\n".json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        try {
            return Http::baseUrl($baseUrl)
                ->timeout($timeout)
                ->connectTimeout(5)
                ->acceptJson()
                ->when($this->config['api_key'] ?? null, fn ($request, $key) => $request->withToken($key))
                ->post('/api/chat', array_filter([
                    'model' => $model,
                    'stream' => false,
                    'think' => (bool) ($this->config['think'] ?? false),
                    'format' => $structured ? ($schema ?: 'json') : null,
                    'options' => array_filter([
                        'temperature' => (float) ($this->config['temperature'] ?? 0.2),
                        'num_ctx' => (int) ($this->config['num_ctx'] ?? 0) ?: null,
                        'num_predict' => (int) ($this->config['max_tokens'] ?? 0) ?: null,
                    ], fn ($value) => $value !== null),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ],
                ], fn ($value) => $value !== null));
        } catch (ConnectionException) {
            throw new AiUnavailable("No se pudo conectar con Ollama en {$baseUrl}. Verifica que el servicio esté en ejecución.");
        }
    }

    public function label(): string
    {
        return (str_contains((string) $this->config['base_url'], 'ollama.com') ? 'Ollama Cloud' : 'Ollama').' · '.$this->config['model'];
    }
}
