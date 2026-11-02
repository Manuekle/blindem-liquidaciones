<?php

namespace App\Services\Ai\Rag;

use App\Services\Ai\Simulated\Text;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Retrieval over the labor-law notes in resources/knowledge (one Markdown file per topic, with
 * `title`, `norm` and `tags` front matter).
 *
 * Notes are split into chunks of a few paragraphs that overlap by one sentence. A question is
 * ranked two ways — BM25 over folded, stemmed words, and cosine similarity of embeddings when an
 * embedder is configured — and the rankings are merged with reciprocal rank fusion. A chunk is a
 * candidate only when it clears the lexical or the semantic threshold and stays close to the best
 * match, so an unrelated question gets no sources and a clear one gets few. Chunks of the same note are merged into one source.
 */
class KnowledgeBase
{
    private const MAX_CHUNK = 700;

    private const STOPWORDS = ['que', 'los', 'las', 'del', 'por', 'con', 'una', 'uno', 'unos', 'unas', 'para', 'como', 'mas', 'pero', 'sus', 'este', 'esta', 'estos', 'estas', 'ese', 'esa', 'eso', 'cual', 'cuales', 'cuando', 'donde', 'quien', 'sin', 'sobre', 'entre', 'hasta', 'desde', 'ser', 'son', 'fue', 'hay', 'han', 'haber', 'tiene', 'tengo', 'tienen', 'puede', 'pueden', 'puedo', 'debe', 'deben', 'debo', 'me', 'mi', 'mis', 'le', 'les', 'lo', 'se', 'su', 'yo', 'el', 'la', 'de', 'en', 'al', 'es', 'un', 'si', 'no', 'ya', 'muy', 'todo', 'toda', 'todos', 'todas', 'otro', 'otra', 'otros', 'otras', 'cada', 'tambien', 'segun', 'solo', 'aqui', 'algo', 'nada', 'esto', 'tan', 'asi', 'pasa', 'hace', 'hacer'];

    /** @var list<array{id: int, doc: string, title: string, norm: string, body: string, text: string, terms: array<string, int>, length: int}>|null */
    private ?array $chunks = null;

    private array $documentFrequency = [];

    private float $averageLength = 1;

    public function __construct(private array $config, private ?Embedder $embedder = null) {}

    /**
     * The sources that best answer `$query`, most relevant first.
     *
     * @return list<array{doc: string, title: string, norm: string, text: string, score: float}>
     */
    public function search(string $query, ?int $limit = null): array
    {
        $chunks = $this->chunks();
        $limit ??= (int) ($this->config['top_k'] ?? 4);
        $queryTerms = array_unique(self::terms($query));

        $lexical = [];
        foreach ($chunks as $chunk) {
            $lexical[$chunk['id']] = $this->bm25($queryTerms, $chunk);
        }
        $semantic = $this->similarities($query);

        // Absolute thresholds drop unrelated questions; staying close to the best match drops the long tail.
        $minLexical = max((float) ($this->config['min_lexical'] ?? 2.0), max($lexical ?: [0]) / 2);
        $minSimilarity = max((float) ($this->config['min_similarity'] ?? 0.62), max($semantic ?: [0]) - 0.1);
        $candidates = array_filter(array_keys($lexical), fn ($id) => $lexical[$id] >= $minLexical || ($semantic[$id] ?? 0) >= $minSimilarity);

        // Reciprocal rank fusion: robust to the two scores living on different scales.
        $fused = [];
        foreach ([$lexical, $semantic] as $scores) {
            arsort($scores);
            $rank = 0;
            foreach ($scores as $id => $score) {
                $rank++;
                if ($score > 0 && in_array($id, $candidates, true)) {
                    $fused[$id] = ($fused[$id] ?? 0) + 1 / (60 + $rank);
                }
            }
        }
        arsort($fused);

        $sources = [];
        foreach (array_keys($fused) as $id) {
            $chunk = $chunks[$id];
            $sources[$chunk['doc']] ??= ['doc' => $chunk['doc'], 'title' => $chunk['title'], 'norm' => $chunk['norm'], 'parts' => [], 'score' => round($fused[$id] * 1000, 2)];
            $sources[$chunk['doc']]['parts'][$id] = $chunk['body'];
        }

        return array_values(array_map(function ($source) {
            ksort($source['parts']);
            $text = implode("\n\n", $source['parts']);
            unset($source['parts']);

            return array_slice($source, 0, 3) + ['text' => $text, 'score' => $source['score']];
        }, array_slice($sources, 0, $limit)));
    }

    /** The sentences of `$text` that share the most words with `$query`, kept in reading order. */
    public function excerpt(string $query, string $text, int $sentences = 2): string
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-ZÁÉÍÓÚÑ¿(])/u', trim(preg_replace('/\s+/', ' ', $text))) ?: [];
        $queryTerms = array_flip(self::terms($query));
        $scored = [];
        foreach ($parts as $i => $sentence) {
            $scored[$i] = count(array_intersect_key(array_flip(self::terms($sentence)), $queryTerms)) - $i * 0.01;
        }
        arsort($scored);
        $best = array_slice(array_keys($scored), 0, $sentences);
        sort($best);

        return implode(' ', array_map(fn ($i) => $parts[$i], $best));
    }

    /** Folded, stemmed content words: "Las cesantías" becomes ["cesant"]. */
    public static function terms(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', Text::fold($text), -1, PREG_SPLIT_NO_EMPTY);
        $terms = [];
        foreach ($words as $word) {
            if (strlen($word) < 3 && ! ctype_digit($word) || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            // A light Spanish stemmer: drop plural endings and keep the first six letters.
            $word = preg_replace('/(?<=[a-z]{4})(es|s)$/', '', $word);
            $terms[] = ctype_digit($word) ? $word : substr($word, 0, 6);
        }

        return $terms;
    }

    private function bm25(array $queryTerms, array $chunk): float
    {
        $count = count($this->chunks);
        $score = 0.0;
        foreach ($queryTerms as $term) {
            $frequency = $chunk['terms'][$term] ?? 0;
            if ($frequency === 0) {
                continue;
            }
            $df = $this->documentFrequency[$term];
            $idf = log(1 + ($count - $df + 0.5) / ($df + 0.5));
            $score += $idf * $frequency * 2.2 / ($frequency + 1.2 * (0.25 + 0.75 * $chunk['length'] / $this->averageLength));
        }

        return $score;
    }

    /** Cosine similarity of the query to every chunk, or nothing when embeddings are off or fail. */
    private function similarities(string $query): array
    {
        if (! $this->embedder) {
            return [];
        }

        try {
            $chunks = $this->chunks();
            $key = 'rag:'.md5($this->embedder->model().'|'.implode('|', array_column($chunks, 'text')));
            $vectors = Cache::rememberForever($key, fn () => $this->embedder->embed(array_column($chunks, 'text')));
            [$queryVector] = $this->embedder->embed([$query], true);
        } catch (Throwable $e) {
            Log::warning('Semantic search unavailable, using BM25 only: '.$e->getMessage());

            return [];
        }

        $similarities = [];
        foreach ($vectors as $id => $vector) {
            $similarities[$id] = self::cosine($queryVector, $vector);
        }

        return $similarities;
    }

    private static function cosine(array $a, array $b): float
    {
        $dot = $normA = $normB = 0.0;
        foreach ($a as $i => $value) {
            $dot += $value * ($b[$i] ?? 0);
            $normA += $value * $value;
        }
        foreach ($b as $value) {
            $normB += $value * $value;
        }

        return $normA > 0 && $normB > 0 ? $dot / sqrt($normA * $normB) : 0.0;
    }

    private function chunks(): array
    {
        if ($this->chunks !== null) {
            return $this->chunks;
        }

        $this->chunks = [];
        foreach (glob(rtrim((string) $this->config['path'], '/').'/*.md') ?: [] as $file) {
            [$meta, $body] = $this->parse((string) file_get_contents($file));
            $previous = '';
            foreach ($this->split($body) as $part) {
                // The last sentence of the previous chunk rides along, so a fact cut at a boundary survives.
                $text = trim($meta['title'].'. '.$meta['norm'].'. '.$previous.' '.$part);
                $terms = self::terms($text.' '.$meta['tags']);
                $this->chunks[] = [
                    'id' => count($this->chunks),
                    'doc' => basename($file, '.md'),
                    'title' => $meta['title'],
                    'norm' => $meta['norm'],
                    'body' => $part,
                    'text' => $text,
                    'terms' => array_count_values($terms),
                    'length' => count($terms),
                ];
                preg_match('/[^.!?]+[.!?]\s*$/u', $part, $last);
                $previous = trim($last[0] ?? '');
            }
        }

        foreach ($this->chunks as $chunk) {
            foreach (array_keys($chunk['terms']) as $term) {
                $this->documentFrequency[$term] = ($this->documentFrequency[$term] ?? 0) + 1;
            }
        }
        $this->averageLength = max(1, array_sum(array_column($this->chunks, 'length')) / max(1, count($this->chunks)));

        return $this->chunks;
    }

    /** Front matter (`key: value` lines between `---`) and the body. */
    private function parse(string $content): array
    {
        $meta = ['title' => '', 'norm' => '', 'tags' => ''];
        if (preg_match('/\A---\s*\n(.*?)\n---\s*\n(.*)\z/s', $content, $match)) {
            foreach (explode("\n", $match[1]) as $line) {
                [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
                $meta[trim($key)] = trim($value);
            }
            $content = $match[2];
        }

        return [$meta, trim($content)];
    }

    /** Paragraphs grouped into chunks of at most MAX_CHUNK characters. */
    private function split(string $body): array
    {
        $chunks = [];
        $current = '';
        foreach (preg_split('/\n\s*\n/', $body) as $paragraph) {
            $paragraph = trim(preg_replace('/\s+/', ' ', $paragraph));
            if ($current !== '' && mb_strlen($current) + mb_strlen($paragraph) > self::MAX_CHUNK) {
                $chunks[] = $current;
                $current = '';
            }
            $current = trim($current."\n\n".$paragraph);
        }

        return array_values(array_filter([...$chunks, $current]));
    }
}
