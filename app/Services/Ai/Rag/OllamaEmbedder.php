<?php

namespace App\Services\Ai\Rag;

use App\Services\Ai\AiUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Embeddings from Ollama's /api/embed, on the same server (local or cloud) as the chat model. */
class OllamaEmbedder implements Embedder
{
    public function __construct(private array $config, private string $model) {}

    public function embed(array $texts, bool $query = false): array
    {
        // Qwen3 embedding models retrieve better when the query states the task; passages go as they are.
        if ($query && str_contains($this->model, 'qwen3-embedding')) {
            $texts = array_map(fn ($text) => "Instruct: Given a question about Colombian labor law, retrieve passages that answer it\nQuery: {$text}", $texts);
        }

        try {
            $response = Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
                ->timeout(max(5, (int) ($this->config['timeout'] ?? 120)))
                ->connectTimeout(5)
                ->acceptJson()
                ->when($this->config['api_key'] ?? null, fn ($request, $key) => $request->withToken($key))
                ->post('/api/embed', ['model' => $this->model, 'input' => array_values($texts)]);
        } catch (ConnectionException) {
            throw new AiUnavailable('No se pudo conectar con Ollama para calcular embeddings.');
        }

        $vectors = $response->json('embeddings');
        if ($response->failed() || ! is_array($vectors) || count($vectors) !== count($texts)) {
            throw new AiUnavailable("El modelo de embeddings {$this->model} no respondió (".$response->status().'). Descárgalo con «ollama pull '.$this->model.'».');
        }

        return $vectors;
    }

    public function model(): string
    {
        return $this->model;
    }
}
