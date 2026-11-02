<?php

namespace App\Services\Ai\Simulated;

/**
 * Turns a settlement into a plain-language explanation a worker can follow.
 */
class SettlementExplanation
{
    public function __invoke(array $input): array
    {
        $ctx = $input['context'];
        $i = $input['inputs'];
        $r = $input['result'];
        $name = Text::firstName($ctx['worker_name']);
        $gross = max(1, $r['gross']);
        $concepts = [
            'Cesantías' => $r['severance'], 'Intereses sobre cesantías' => $r['severance_interest'], 'Prima de servicios' => $r['premium'],
            'Vacaciones compensadas' => $r['vacations'], 'Salario pendiente' => $r['salary_due'], 'Auxilio de transporte' => $r['transport_due'],
            'Indemnización' => $r['indemnification'],
        ];
        arsort($concepts);
        $top = array_key_first($concepts);

        $paragraphs = [
            "La mayor parte viene de {$this->article($top)} ({$this->share($concepts[$top], $gross)}). Las cesantías equivalen a un salario mensual por cada año trabajado y aquí se reconocen {$r['cesantias_days']} días, con intereses del ".($r['interest_rate'] * 100).'% anual ('.Text::money($r['severance_interest']).').',
            'La prima de servicios suma '.Text::money($r['premium'])." por {$r['prima_days']} días del semestre y las vacaciones compensadas ".Text::money($r['vacations']).' por '.$this->days($r['vacation_days_pending']).' de descanso que no alcanzó a disfrutar.',
        ];
        if ($r['salary_due'] > 0) {
            $paragraphs[] = 'También se pagan '.$this->days($r['pending_salary_days']).' de salario pendiente ('.Text::money($r['salary_due']).')'.($r['transport_due'] > 0 ? ' y su auxilio de transporte ('.Text::money($r['transport_due']).').' : '.');
        }
        $paragraphs[] = match (true) {
            $r['indemnification'] > 0 => "Como el retiro fue por {$this->lower($ctx['termination_reason'])}, corresponde indemnización: ".Text::money($r['indemnification']).' por '.$this->days($r['indemnification_days']).' de salario.',
            $ctx['termination_reason'] !== 'No aplica' => "Al tratarse de {$this->lower($ctx['termination_reason'])}, no hay indemnización.",
            default => 'El contrato sigue activo, así que este cálculo es una proyección al '.Text::date($r['effective_end_date']).' y no incluye indemnización.',
        };
        $paragraphs[] = 'Se descuentan '.Text::money($r['total_deductions']).': aportes a salud ('.Text::money($r['health']).') y pensión ('.Text::money($r['pension']).') sobre el salario pendiente'
            .($r['advances'] > 0 ? ', anticipos por '.Text::money($r['advances']) : '')
            .($r['other_deductions'] > 0 ? ' y otras deducciones por '.Text::money($r['other_deductions']) : '').'. Las prestaciones sociales no tienen aportes.';
        foreach ($r['warnings'] as $warning) {
            $paragraphs[] = 'Ten en cuenta: '.lcfirst($warning);
        }

        return [
            'headline' => "{$name} recibe ".Text::money($r['net']).' netos por '.Text::tenure($i['start_date'], $r['effective_end_date']).' de trabajo en '.Text::sentenceEnd($ctx['company_name']),
            'paragraphs' => $paragraphs,
            'highlights' => array_values(array_map(fn ($label, $value) => ['label' => $label, 'value' => Text::money($value), 'share' => Text::percent($value, $gross)], array_keys(array_filter($concepts)), array_filter($concepts))),
            'followups' => SettlementAnswer::suggestions($r, $ctx),
        ];
    }

    private function share(int $value, int $gross): string
    {
        return Text::money($value).', el '.Text::percent($value, $gross).'% del devengado';
    }

    private function article(string $concept): string
    {
        $article = match ($concept) {
            'Cesantías', 'Vacaciones compensadas' => 'las',
            'Prima de servicios', 'Indemnización' => 'la',
            'Intereses sobre cesantías' => 'los',
            default => 'el',
        };

        return $article.' '.mb_strtolower($concept);
    }

    private function lower(string $text): string
    {
        return mb_strtolower($text);
    }

    private function days(int|float $days): string
    {
        $formatted = rtrim(rtrim(number_format((float) $days, 2, ',', '.'), '0'), ',');

        return $formatted.' '.((float) $days === 1.0 ? 'día' : 'días');
    }
}
