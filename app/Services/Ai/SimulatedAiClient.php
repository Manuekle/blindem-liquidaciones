<?php

namespace App\Services\Ai;

use App\Services\Ai\Simulated;

/**
 * Answers every task with deterministic local heuristics so the product flows can be built and
 * tested before a real model is connected. It ignores `$instructions`; a real client sends them.
 */
class SimulatedAiClient implements AiClient
{
    private const TASKS = [
        'contract.extract' => Simulated\ContractExtraction::class,
        'contract.review' => Simulated\ContractReview::class,
        'settlement.explain' => Simulated\SettlementExplanation::class,
        'settlement.answer' => Simulated\SettlementAnswer::class,
        'settlement.letter' => Simulated\SettlementLetter::class,
        'dashboard.insights' => Simulated\DashboardInsights::class,
        'contracts.search' => Simulated\SearchInterpretation::class,
    ];

    public function run(string $task, string $instructions, array $input, array $schema = []): array
    {
        $handler = self::TASKS[$task] ?? throw new AiUnavailable("La tarea {$task} no está disponible.");

        return (new $handler)($input);
    }

    public function label(): string
    {
        return 'Simulado';
    }
}
