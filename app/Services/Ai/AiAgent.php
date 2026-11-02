<?php

namespace App\Services\Ai;

use App\Models\Contract;
use App\Models\Settlement;
use App\Models\Worker;
use App\Services\Ai\Simulated\Text;
use App\Services\LaborSettlementCalculator;
use App\Services\SettlementInputs;
use DomainException;

/**
 * The floating assistant: one chat box that works on whatever page the user is on.
 *
 * It does not ask a model what to do. Rules read the message, work out the intent and the contract it
 * is about (named in the message, the page being viewed or the conversation so far), and hand it to the
 * AiAssistant task that already answers that kind of question, with the same verification. When the
 * message is ambiguous ("la liquidación de Carlos" with two Carlos) it asks instead of guessing.
 *
 * Every answer lists the `steps` it took, so the panel can show what was understood and done, and a
 * `mood` for the avatar: happy (done), surprised (something needs attention), unsure (a question back)
 * and sad (could not do it).
 */
class AiAgent
{
    private const CONCEPTS = ['prima', 'cesantia', 'interes', 'vacacion', 'indemniz', 'transporte', 'salario pendiente', 'descuento', 'aporte', 'neto', 'bruto', 'moratoria'];

    /** Words that name a worker in a message but never are part of a name. */
    private const STOPWORDS = ['de', 'del', 'la', 'las', 'los', 'el', 'para', 'por', 'con', 'que', 'una', 'uno', 'contrato', 'contratos', 'liquidacion', 'trabajador', 'trabajadora'];

    /** Workers named in the current message (or the one the user just picked). */
    private array $named = [];

    public function __construct(
        private AiAssistant $assistant,
        private LaborSettlementCalculator $calculator,
        private SettlementInputs $inputs,
    ) {}

    /**
     * `contract` is the one the conversation is about (the page's, until the user picks another), `page_contract`,
     * `worker` and `settlement` come from the page being viewed, and `chosen_*` from a choice the user just clicked.
     *
     * @param  array{route?: ?string, contract?: ?int, page_contract?: ?int, worker?: ?int, settlement?: ?int, chosen_contract?: ?int, chosen_worker?: ?int}  $context
     */
    public function handle(string $message, array $context): array
    {
        $plain = Text::fold($message);
        $this->named = ($context['chosen_worker'] ?? null) ? [(int) $context['chosen_worker']] : $this->workersIn($plain);
        $intent = $this->intent($plain, $context);
        $steps = [['label' => 'Entendí la petición', 'detail' => self::INTENTS[$intent]]];

        if ($intent === 'help') {
            return $this->help($context, $steps);
        }
        if ($intent === 'insights') {
            return $this->insights($steps);
        }
        if ($intent === 'search') {
            return $this->search($message, $steps);
        }
        if ($intent === 'question' && ! $this->named && ! ($context['contract'] ?? null) && ! ($context['chosen_contract'] ?? null)) {
            return $this->question($message, $steps);
        }

        // The remaining intents work on one contract.
        $target = $this->contractFor($plain, $context);
        if ($target['choices'] ?? null) {
            $steps[] = ['label' => 'Busqué el contrato', 'detail' => $target['detail']];

            return $this->reply('clarify', 'unsure', $steps, $target['ask'], choices: $target['choices']);
        }
        if (! $target['contract']) {
            if ($intent === 'question') {
                return $this->question($message, $steps);
            }
            $steps[] = ['label' => 'Busqué el contrato', 'detail' => $target['detail'] ?? 'No hay un contrato abierto ni nombrado'];

            return $this->reply('clarify', 'unsure', $steps, '¿De qué trabajador hablamos? Escribe su nombre o número de documento, o abre su contrato y vuelve a preguntarme.');
        }
        $contract = $target['contract'];
        $steps[] = ['label' => $target['how'], 'detail' => $this->contractLabel($contract)];
        $focus = ['contract' => $contract->id, 'label' => $this->contractLabel($contract)];

        return match ($intent) {
            'review' => $this->review($contract, $steps, $focus),
            'navigate' => $this->navigate($plain, $contract, $steps, $focus),
            'explain' => $this->explain($contract, $context, $steps, $focus),
            default => $this->settlementQuestion($message, $contract, $context, $steps, $focus),
        };
    }

    private const INTENTS = [
        'help' => 'Qué puedo hacer',
        'insights' => 'Pendientes de hoy',
        'search' => 'Buscar contratos',
        'review' => 'Revisar un contrato',
        'explain' => 'Explicar una liquidación',
        'navigate' => 'Abrir una pantalla',
        'question' => 'Pregunta laboral',
    ];

    private function intent(string $plain, array $context): string
    {
        $has = fn (string ...$needles) => (bool) array_filter($needles, fn ($needle) => preg_match('/\b'.$needle.'/u', $plain));
        $words = str_word_count(preg_replace('/[^a-z0-9 ]/', ' ', $plain));

        return match (true) {
            $words <= 3 && $has('hola', 'ayuda', 'buenas', 'hey') => 'help',
            $has('que puedes', 'que sabes', 'como funcionas', 'ayudame con que', 'en que me ayudas') => 'help',
            $has('pendiente', 'revisar hoy', 'alerta', 'urgente', 'por vencer', 'vencen', 'prioridad', 'que hay para', 'que tengo') && ! $this->named => 'insights',
            $has('abre', 'abrir', 'ir a ', 'llevame', 've a ', 'muestrame el contrato', 'editar el contrato', 'edita') => 'navigate',
            $has('revisa', 'revision', 'problema', 'riesgo', 'errore', 'listo para liquidar', 'puedo liquidar', 'algo mal', 'falta algo') => 'review',
            $has('busca', 'encuentra', 'lista', 'listar', 'muestra', 'mostrar', 'cuantos contratos', 'quienes', 'filtra') && ! $has('liquidacion') => 'search',
            $has('explica', 'desglose', 'cuanto le (toca|corresponde|pagan|debo)', 'cuanto (recibe|gana con)', 'resumen de la liquidacion', 'calcula') => 'explain',
            $has('liquidacion') && ! $has('cuando', 'como', 'que pasa', 'puede', 'plazo') && ! array_filter(self::CONCEPTS, fn ($c) => str_contains($plain, $c)) => 'explain',
            $has('activos', 'retirados', 'vigentes', 'terminados') && ! ($context['contract'] ?? null) => 'search',
            default => 'question',
        };
    }

    /** Workers named in the message (by any part of the name, or the document number), best match first. */
    private function workersIn(string $plain): array
    {
        $scores = [];
        foreach (Worker::all(['id', 'full_name', 'document_number']) as $worker) {
            $document = preg_replace('/\D/', '', (string) $worker->document_number);
            if (strlen($document) >= 5 && str_contains(preg_replace('/\D/', '', $plain), $document)) {
                $scores[$worker->id] = 10;

                continue;
            }
            $parts = array_filter(preg_split('/\s+/', Text::fold($worker->full_name)), fn ($part) => mb_strlen($part) > 2 && ! in_array($part, self::STOPWORDS, true));
            $hits = count(array_filter($parts, fn ($part) => preg_match('/\b'.preg_quote($part, '/').'\b/u', $plain)));
            if ($hits) {
                $scores[$worker->id] = $hits;
            }
        }
        if (! $scores) {
            return [];
        }
        arsort($scores);
        $best = reset($scores);

        // Only the strongest matches: "Juan Carlos" beats a plain "Carlos".
        return array_keys(array_filter($scores, fn ($score) => $score === $best));
    }

    /**
     * The contract a message is about: "contrato 12" > a worker named in it > the page being viewed > the conversation.
     *
     * @return array{contract: ?Contract, how?: string, detail?: string, ask?: string, choices?: array}
     */
    private function contractFor(string $plain, array $context): array
    {
        $with = ['worker', 'company', 'contractType'];
        if (($context['chosen_contract'] ?? null) && ($contract = Contract::with($with)->find($context['chosen_contract']))) {
            return ['contract' => $contract, 'how' => 'Usé el contrato elegido'];
        }
        if (preg_match('/contrato\s*(?:#|n(?:o|um|umero)?\.?\s*)?(\d+)\b/u', $plain, $m) && ($contract = Contract::with($with)->find((int) $m[1]))) {
            return ['contract' => $contract, 'how' => 'Abrí el contrato #'.$contract->id];
        }
        $workers = $this->named;
        if (count($workers) > 1) {
            $names = Worker::whereIn('id', $workers)->orderBy('full_name')->get();

            return ['contract' => null, 'detail' => $names->count().' trabajadores coinciden', 'ask' => '¿A quién te refieres?', 'choices' => $names->map(fn ($w) => ['label' => $w->full_name, 'worker' => $w->id])->all()];
        }
        if ($workers) {
            $contracts = Contract::with($with)->where('worker_id', $workers[0])->orderByRaw("case when status = 'active' then 0 else 1 end")->latest('start_date')->get();
            if ($contracts->count() === 1) {
                return ['contract' => $contracts->first(), 'how' => 'Encontré el contrato'];
            }
            if ($contracts->isEmpty()) {
                return ['contract' => null, 'detail' => Worker::find($workers[0])->full_name.' no tiene contratos'];
            }
            // The page or the conversation may already point at one of this worker's contracts.
            $current = $contracts->firstWhere('id', $context['contract'] ?? null);
            if ($current) {
                return ['contract' => $current, 'how' => 'Uso el contrato abierto'];
            }

            return ['contract' => null, 'detail' => $contracts->count().' contratos de '.$contracts->first()->worker->full_name, 'ask' => '¿Cuál contrato?', 'choices' => $contracts->map(fn ($c) => ['label' => $c->company->name.' · desde '.$c->start_date->format('d/m/Y').' · '.$c->display_status, 'contract' => $c->id])->all()];
        }
        if ($context['contract'] ?? null) {
            $contract = Contract::with($with)->find($context['contract']);

            return ['contract' => $contract, 'how' => (int) $context['contract'] === (int) ($context['page_contract'] ?? 0) ? 'Uso el contrato abierto' : 'Sigo con el mismo contrato'];
        }
        if ($context['worker'] ?? null) {
            $contracts = Contract::with($with)->where('worker_id', $context['worker'])->latest('start_date')->get();
            if ($contracts->count() === 1) {
                return ['contract' => $contracts->first(), 'how' => 'Uso el contrato del trabajador abierto'];
            }
            if ($contracts->count() > 1) {
                return ['contract' => null, 'detail' => $contracts->count().' contratos del trabajador abierto', 'ask' => '¿Cuál contrato?', 'choices' => $contracts->map(fn ($c) => ['label' => $c->company->name.' · desde '.$c->start_date->format('d/m/Y').' · '.$c->display_status, 'contract' => $c->id])->all()];
            }
        }

        return ['contract' => null];
    }

    private function help(array $context, array $steps): array
    {
        $onContract = (bool) ($context['contract'] ?? null);
        $followups = $onContract
            ? ['Explícame esta liquidación', 'Revisa este contrato', '¿Cuándo se debe pagar?']
            : ['¿Qué hay pendiente hoy?', 'Busca los contratos retirados', '¿Cuánto dura el periodo de prueba?'];

        return $this->reply('help', 'happy', $steps, 'Puedo decirte qué revisar hoy, buscar contratos, revisar un contrato antes de liquidarlo, explicar una liquidación y responder preguntas laborales con la norma citada. Nombra al trabajador o abre su contrato y pregúntame.', followups: $followups);
    }

    private function insights(array $steps): array
    {
        $data = $this->assistant->dashboardInsights();
        $steps[] = ['label' => 'Revisé la cartera', 'detail' => count($data['insights']).' '.(count($data['insights']) === 1 ? 'aviso' : 'avisos')];
        $items = array_map(fn ($i) => ['title' => $i['title'], 'detail' => $i['detail'], 'tone' => $i['tone'], 'href' => $i['contract_id'] ? route(match ($i['route']) { 'settlement' => 'settlements.show', 'edit' => 'contracts.edit', default => 'contracts.show' }, $i['contract_id']) : null, 'action' => $i['action']], $data['insights']);
        $urgent = (bool) array_filter($data['insights'], fn ($i) => in_array($i['tone'], ['alert', 'warn'], true));

        return $this->reply('insights', $urgent ? 'surprised' : 'happy', $steps, $urgent ? 'Esto conviene revisarlo hoy:' : 'No hay nada urgente. Esto es lo que vi:', items: $items, source: $data);
    }

    private function search(string $message, array $steps): array
    {
        $data = $this->assistant->interpretSearch(mb_substr($message, 0, 200));
        $steps[] = ['label' => 'Convertí la búsqueda en filtros', 'detail' => $data['understood'] ? implode(' · ', $data['understood']) : 'Ningún filtro reconocido'];
        if (! $data['filters']) {
            return $this->reply('search', 'unsure', $steps, $data['message'], followups: ['Busca los contratos activos', 'Busca los contratos retirados']);
        }
        $query = Contract::with('worker', 'company')->filter($data['filters']);
        $total = (clone $query)->count();
        $contracts = $query->latest()->limit(5)->get();
        $steps[] = ['label' => 'Consulté los contratos', 'detail' => $total.' '.($total === 1 ? 'resultado' : 'resultados')];
        $items = $contracts->map(fn ($c) => ['title' => $c->worker->full_name, 'detail' => $c->company->name.' · '.$c->display_status.' · desde '.$c->start_date->format('d/m/Y'), 'tone' => $c->status === 'active' ? 'good' : 'info', 'href' => route('contracts.show', $c), 'action' => 'Ver contrato'])->all();
        $reply = match (true) {
            $total === 0 => 'No hay contratos con esos filtros.',
            $total <= 5 => $total === 1 ? 'Encontré 1 contrato:' : "Encontré {$total} contratos:",
            default => "Encontré {$total} contratos; estos son los 5 más recientes:",
        };

        return $this->reply('search', $total ? 'happy' : 'unsure', $steps, $reply, items: $items, actions: $total ? [['label' => 'Ver todos en Contratos', 'href' => route('contracts.index', $data['filters'])]] : [], source: $data);
    }

    private function question(string $message, array $steps): array
    {
        $data = $this->assistant->answerQuestion(mb_substr($message, 0, 300));
        $steps[] = ['label' => 'Consulté la base de conocimiento', 'detail' => $data['sources'] ? count($data['sources']).' '.(count($data['sources']) === 1 ? 'fuente' : 'fuentes') : 'Sin fuentes pertinentes'];

        return $this->reply('question', $data['status'] === 'answered' ? 'happy' : 'unsure', $steps, $data['answer'], sources: $data['sources'], source: $data);
    }

    private function review(Contract $contract, array $steps, array $focus): array
    {
        $data = $this->assistant->reviewContract($contract);
        $steps[] = ['label' => 'Revisé el contrato', 'detail' => count($data['findings']).' '.(count($data['findings']) === 1 ? 'hallazgo' : 'hallazgos')];
        $tone = ['alta' => 'alert', 'media' => 'warn', 'baja' => 'info'];
        $items = array_map(fn ($f) => ['title' => $f['title'], 'detail' => $f['detail'], 'tone' => $tone[$f['severity']] ?? 'info'], $data['findings']);
        $serious = (bool) array_filter($data['findings'], fn ($f) => $f['severity'] === 'alta');

        return $this->reply('review', $serious ? 'surprised' : 'happy', $steps, $data['summary'], items: $items, actions: [['label' => 'Abrir contrato', 'href' => route('contracts.show', $contract)], ['label' => 'Editar contrato', 'href' => route('contracts.edit', $contract)]], followups: ['Explícame la liquidación'], focus: $focus, source: $data);
    }

    private function navigate(string $plain, Contract $contract, array $steps, array $focus): array
    {
        [$route, $label] = match (true) {
            str_contains($plain, 'edit') => ['contracts.edit', 'Editar contrato'],
            str_contains($plain, 'liquidacion') => ['settlements.show', 'Abrir liquidación'],
            str_contains($plain, 'trabajador') => ['workers.show', 'Abrir trabajador'],
            default => ['contracts.show', 'Abrir contrato'],
        };
        $href = $route === 'workers.show' ? route($route, $contract->worker_id) : route($route, $contract);
        $steps[] = ['label' => 'Preparé el enlace', 'detail' => $label];

        return $this->reply('navigate', 'happy', $steps, 'Listo, aquí tienes el enlace.', actions: [['label' => $label, 'href' => $href, 'go' => true]], focus: $focus);
    }

    private function explain(Contract $contract, array $context, array $steps, array $focus): array
    {
        $preview = $this->preview($contract, $context);
        if (is_string($preview)) {
            $steps[] = ['label' => 'Intenté calcular la liquidación', 'detail' => 'El cálculo está bloqueado'];

            return $this->reply('explain', 'sad', $steps, $preview, actions: [['label' => 'Editar contrato', 'href' => route('contracts.edit', $contract)]], followups: ['Revisa este contrato'], focus: $focus);
        }
        $steps[] = ['label' => $preview['saved'] ? 'Usé la liquidación guardada' : 'Calculé la liquidación', 'detail' => 'Neto '.Text::money($preview['result']['net'])];
        $data = $this->assistant->explainSettlement($preview, $preview['inputs']['context'] ?? $this->inputs->fromContract($contract)['context']);
        // The four largest concepts; the full breakdown is one click away on the settlement page.
        $items = array_map(fn ($h) => ['title' => $h['label'], 'detail' => $h['value'].' · '.$h['share'].'% del total', 'tone' => 'info'], array_slice($data['highlights'], 0, 4));

        return $this->reply('explain', 'happy', $steps, implode("\n\n", [$data['headline'], ...$data['paragraphs']]), items: $items, actions: [['label' => 'Abrir liquidación', 'href' => route('settlements.show', $contract)]], followups: $data['followups'], focus: $focus, source: $data);
    }

    private function settlementQuestion(string $message, Contract $contract, array $context, array $steps, array $focus): array
    {
        $preview = $this->preview($contract, $context);
        if (is_string($preview)) {
            // No settlement to lean on: the question is still answerable from the knowledge base.
            $steps[] = ['label' => 'Intenté calcular la liquidación', 'detail' => 'El cálculo está bloqueado'];

            return ['focus' => $focus] + $this->question($message, $steps);
        }
        $data = $this->assistant->answerSettlement(mb_substr($message, 0, 300), $preview, $preview['inputs']['context'] ?? $this->inputs->fromContract($contract)['context']);
        $steps[] = ['label' => $data['sources'] ? 'Crucé el cálculo con la norma' : 'Usé las cifras del cálculo', 'detail' => $data['sources'] ? count($data['sources']).' '.(count($data['sources']) === 1 ? 'fuente' : 'fuentes') : 'Neto '.Text::money($preview['result']['net'])];

        return $this->reply('question', $data['status'] === 'answered' ? 'happy' : 'unsure', $steps, $data['answer'], sources: $data['sources'], followups: $data['followups'], focus: $focus, source: $data);
    }

    /** The settlement a contract answer is based on: the saved one being viewed, otherwise the live calculation. A string when it cannot be calculated. */
    private function preview(Contract $contract, array $context): array|string
    {
        if (($context['settlement'] ?? null) && ($saved = Settlement::where('contract_id', $contract->id)->find($context['settlement']))) {
            return ['inputs' => $saved->inputs, 'result' => $saved->result, 'saved' => true];
        }
        try {
            $data = $this->inputs->fromContract($contract);

            return ['inputs' => $data, 'result' => $this->calculator->calculate($data), 'saved' => false];
        } catch (DomainException $e) {
            return $e->getMessage();
        }
    }

    private function contractLabel(Contract $contract): string
    {
        return $contract->worker->full_name.' · '.$contract->company->name.' · '.$contract->display_status;
    }

    private function reply(string $intent, string $mood, array $steps, string $text, array $items = [], array $sources = [], array $choices = [], array $actions = [], array $followups = [], ?array $focus = null, array $source = []): array
    {
        return [
            'intent' => $intent,
            'mood' => $mood,
            'steps' => $steps,
            'reply' => $text,
            'items' => $items,
            'sources' => $sources,
            'choices' => $choices,
            'actions' => $actions,
            'followups' => array_slice($followups, 0, 3),
            'focus' => $focus,
            'source' => $source['source'] ?? 'rules',
            'fallback' => $source['fallback'] ?? false,
            'status' => $source['status'] ?? null,
        ];
    }
}
