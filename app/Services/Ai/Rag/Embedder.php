<?php

namespace App\Services\Ai\Rag;

/** Turns texts into vectors for semantic search. Failures throw App\Services\Ai\AiUnavailable. */
interface Embedder
{
    /**
     * @param  list<string>  $texts
     * @param  bool  $query  True for a search query, false for the passages being indexed.
     * @return list<list<float>>
     */
    public function embed(array $texts, bool $query = false): array;

    /** Identifies the vectors, so an index built with another model is never reused. */
    public function model(): string;
}
