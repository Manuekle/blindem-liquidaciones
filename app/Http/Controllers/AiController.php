<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Settlement;
use App\Services\Ai\AiAgent;
use App\Services\Ai\AiAssistant;
use App\Services\Ai\AiUnavailable;
use App\Services\LaborSettlementCalculator;
use App\Services\SettlementInputs;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController
{
    public function __construct(private AiAssistant $assistant) {}

    public function extractContract(Request $request): JsonResponse
    {
        $data = $request->validate(['text' => 'required|string|min:15|max:5000']);

        return $this->respond(fn () => $this->assistant->extractContract($data['text']));
    }

    public function reviewContract(Contract $contract): JsonResponse
    {
        return $this->respond(fn () => $this->assistant->reviewContract($contract));
    }

    public function explainSettlement(Request $request, Contract $contract, LaborSettlementCalculator $calculator, SettlementInputs $inputs): JsonResponse
    {
        return $this->withSettlement($request, $contract, $calculator, $inputs, fn ($preview, $context) => $this->assistant->explainSettlement($preview, $context));
    }

    public function answerSettlement(Request $request, Contract $contract, LaborSettlementCalculator $calculator, SettlementInputs $inputs): JsonResponse
    {
        $question = $request->validate(['question' => 'required|string|min:3|max:300'])['question'];

        return $this->withSettlement($request, $contract, $calculator, $inputs, fn ($preview, $context) => $this->assistant->answerSettlement($question, $preview, $context));
    }

    public function draftLetter(Request $request, Contract $contract, LaborSettlementCalculator $calculator, SettlementInputs $inputs): JsonResponse
    {
        $kind = $request->validate(['kind' => 'required|in:liquidacion,paz_y_salvo'])['kind'];

        return $this->withSettlement($request, $contract, $calculator, $inputs, fn ($preview, $context) => $this->assistant->draftLetter($kind, $preview, $context));
    }

    public function dashboardInsights(): JsonResponse
    {
        return $this->respond(fn () => $this->assistant->dashboardInsights());
    }

    public function interpretSearch(Request $request): JsonResponse
    {
        $query = $request->validate(['query' => 'required|string|min:2|max:200'])['query'];

        return $this->respond(fn () => $this->assistant->interpretSearch($query));
    }

    /** The floating assistant: a free-text message plus what the user is looking at. */
    public function agent(Request $request, AiAgent $agent): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|min:2|max:400',
            'context' => 'array',
            'context.route' => 'nullable|string|max:80',
            ...array_fill_keys(array_map(fn ($key) => "context.{$key}", ['contract', 'page_contract', 'worker', 'settlement', 'chosen_contract', 'chosen_worker']), 'nullable|integer|min:1'),
        ]);

        return $this->respond(fn () => $agent->handle(trim($data['message']), $data['context'] ?? []));
    }

    /**
     * Uses a saved settlement when `settlement` is sent (the history view), otherwise the live calculation.
     */
    private function withSettlement(Request $request, Contract $contract, LaborSettlementCalculator $calculator, SettlementInputs $inputs, callable $task): JsonResponse
    {
        if ($request->filled('settlement')) {
            $saved = Settlement::where('contract_id', $contract->id)->findOrFail($request->integer('settlement'));
            $preview = ['inputs' => $saved->inputs, 'result' => $saved->result];
        } else {
            try {
                $data = $inputs->fromContract($contract);
                $preview = ['inputs' => $data, 'result' => $calculator->calculate($data)];
            } catch (DomainException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }
        $context = $preview['inputs']['context'] ?? $inputs->fromContract($contract)['context'];

        return $this->respond(fn () => $task($preview, $context));
    }

    private function respond(callable $task): JsonResponse
    {
        $started = microtime(true);
        try {
            $data = $task();
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['data' => $data, 'meta' => ['provider' => $this->assistant->label(), 'ms' => (int) round((microtime(true) - $started) * 1000)]]);
    }
}
