<?php

namespace Tests\Feature;

use App\Services\Ai\Rag\Embedder;
use App\Services\Ai\Rag\KnowledgeBase;
use App\Services\Ai\AiUnavailable;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    /** Golden questions: the note that answers each one must come first (recall@1). */
    private const GOLDEN = [
        '¿Me pueden negar el certificado laboral?' => 'certificado-laboral',
        '¿Cuánto dura el periodo de prueba?' => 'periodo-prueba',
        '¿Con cuántos días de anticipación deben avisar que no renuevan mi contrato?' => 'contrato-termino-fijo',
        '¿Me deben dar uniforme y zapatos?' => 'dotacion',
        'Estoy embarazada, ¿me pueden despedir?' => 'fueros',
        'Si firmo el paz y salvo pierdo mis derechos?' => 'irrenunciabilidad',
        '¿Cuál es el recargo dominical este año?' => 'jornada-recargos',
        '¿Qué pasa si renuncio, me pagan algo?' => 'renuncia',
        '¿Me pueden echar por llegar tarde?' => 'justa-causa',
        '¿El salario integral tiene prima?' => 'salario-integral',
        '¿Por qué se divide en 360?' => 'metodo-blindem',
        '¿Tengo derecho al examen médico de retiro?' => 'examen-egreso',
    ];

    private function knowledge(?Embedder $embedder = null): KnowledgeBase
    {
        return new KnowledgeBase(config('ai.rag'), $embedder);
    }

    public function test_golden_questions_retrieve_their_note_first(): void
    {
        $knowledge = $this->knowledge();
        foreach (self::GOLDEN as $question => $note) {
            $this->assertSame($note, $knowledge->search($question)[0]['doc'] ?? null, $question);
        }
    }

    public function test_unrelated_questions_get_no_sources(): void
    {
        $this->assertSame([], $this->knowledge()->search('¿Cuál es la capital de Francia?'));
        $this->assertSame([], $this->knowledge()->search('¿Cómo hago una torta de chocolate?'));
    }

    public function test_sources_carry_their_norm_and_stay_few(): void
    {
        $sources = $this->knowledge()->search('¿Me pueden negar el certificado laboral?');

        $this->assertLessThanOrEqual(config('ai.rag.top_k'), count($sources));
        $this->assertSame('Art. 57, numeral 7 CST', $sources[0]['norm']);
        $this->assertStringContainsString('no puede negarse', $sources[0]['text']);
    }

    public function test_excerpt_keeps_the_sentences_that_match_the_question(): void
    {
        $text = 'El auxilio se paga cada mes. La dotación se entrega tres veces al año. Nada más.';

        $this->assertSame('La dotación se entrega tres veces al año.', $this->knowledge()->excerpt('¿Cuándo entregan la dotación?', $text, 1));
    }

    public function test_semantic_search_finds_paraphrases_and_survives_failures(): void
    {
        // Two-dimensional "embeddings": the paraphrase points the same way as the dotación note only.
        $embedder = new class implements Embedder
        {
            public bool $fail = false;

            public function embed(array $texts, bool $query = false): array
            {
                if ($this->fail) {
                    throw new AiUnavailable('down');
                }

                return array_map(fn ($text) => str_contains($text, 'Dotación') || str_contains($text, 'ropa de trabajo') ? [1.0, 0.0] : [0.0, 1.0], $texts);
            }

            public function model(): string
            {
                return 'fake-'.spl_object_id($this);
            }
        };

        $this->assertSame('dotacion', $this->knowledge($embedder)->search('¿Me dan ropa de trabajo?')[0]['doc'] ?? null);
        $embedder->fail = true;
        $this->assertSame('dotacion', $this->knowledge($embedder)->search('¿Cada cuánto entregan la dotación?')[0]['doc'] ?? null);
    }
}
