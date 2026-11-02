<?php

namespace App\Services\Ai\Simulated;

/**
 * Answers follow-up questions about one settlement using its own figures.
 */
class SettlementAnswer
{
    public static function suggestions(array $r, array $ctx): array
    {
        return array_values(array_filter([
            '¿Cómo se calculan las cesantías?',
            $r['indemnification'] > 0 ? '¿Por qué hay indemnización?' : ($ctx['termination_reason'] !== 'No aplica' ? '¿Por qué no hay indemnización?' : null),
            '¿Qué descuentos se aplican?',
            '¿Cuándo se debe pagar la liquidación?',
            '¿Cómo se calculan las vacaciones?',
        ]));
    }

    public function __invoke(array $input): array
    {
        $q = Text::fold((string) $input['question']);
        $r = $input['result'];
        $ctx = $input['context'];
        $base = Text::money($r['prestational_base']);

        $answer = match (true) {
            (bool) preg_match('/interes/', $q) => 'Los intereses sobre cesantías son el '.($r['interest_rate'] * 100).'% anual de las cesantías, proporcional a los días: '.Text::money($r['severance']).' × '.$r['cesantias_days'].' × '.($r['interest_rate'] * 100).'% / 360 = '.Text::money($r['severance_interest']).'.',
            (bool) preg_match('/cesantia/', $q) => "Las cesantías se calculan con la base prestacional ({$base}: salario, auxilio de transporte cuando aplica y promedio de extras y recargos) multiplicada por los días del año en curso y dividida en 360: {$base} × {$r['cesantias_days']} / 360 = ".Text::money($r['severance']).'.',
            (bool) preg_match('/prima/', $q) => "La prima de servicios usa la misma base prestacional ({$base}) por los días del semestre: {$base} × {$r['prima_days']} / 360 = ".Text::money($r['premium']).'. Se paga en junio y diciembre; al retiro se liquida lo causado.',
            (bool) preg_match('/vacacion/', $q) => 'Por cada año de servicio se causan 15 días hábiles de vacaciones. Se compensan '.number_format($r['vacation_days_pending'], 2, ',', '.').' días pendientes sobre la base de vacaciones ('.Text::money($r['vacation_base']).' / 30) = '.Text::money($r['vacations']).'. Las horas extras no entran en esta base.',
            (bool) preg_match('/indemniz|despid/', $q) => $r['indemnification'] > 0
                ? 'Corresponde indemnización porque el motivo registrado es '.mb_strtolower($ctx['termination_reason']).' (art. 64 CST): '.number_format($r['indemnification_days'], 2, ',', '.').' días de salario, '.Text::money($r['indemnification']).'.'
                : ($ctx['termination_reason'] === 'No aplica'
                    ? 'El contrato sigue activo, así que no se calcula indemnización.'
                    : 'Con el motivo «'.$ctx['termination_reason'].'» la ley no obliga a indemnizar: solo el despido sin justa causa, o terminar un término fijo antes de la fecha pactada, genera indemnización.'),
            (bool) preg_match('/descuent|deduc|salud|pension|aporte/', $q) => 'Se descuenta el '.($r['health_rate'] * 100).'% para salud ('.Text::money($r['health']).') y el '.($r['pension_rate'] * 100).'% para pensión ('.Text::money($r['pension']).') solo sobre el salario pendiente; cesantías, prima, vacaciones e indemnización no tienen aportes. '.($r['advances'] + $r['other_deductions'] > 0 ? 'Además se restan anticipos y otras deducciones por '.Text::money($r['advances'] + $r['other_deductions']).', que deben estar autorizados por escrito.' : 'No hay anticipos ni otras deducciones registradas.'),
            (bool) preg_match('/cuando|plazo|pagar|pago|mora|demora/', $q) => 'La liquidación debe pagarse el mismo día de la terminación. Si se retrasa sin justificación, el empleador debe un día de salario por cada día de mora durante los primeros 24 meses (art. 65 CST), y las cesantías no consignadas generan otra sanción (art. 99, Ley 50 de 1990).',
            (bool) preg_match('/neto|total|cuanto|recibe/', $q) => 'El neto es el devengado ('.Text::money($r['gross']).') menos las deducciones ('.Text::money($r['total_deductions']).'): '.Text::money($r['net']).'.',
            (bool) preg_match('/transporte|auxilio/', $q) => $r['transport_due'] > 0
                ? 'El auxilio de transporte se paga por los días pendientes ('.Text::money($r['transport_due']).') y, como el salario no supera 2 salarios mínimos, también entra en la base de cesantías y prima.'
                : 'No hay auxilio de transporte pendiente: o no hay días por pagar o el salario supera 2 salarios mínimos.',
            default => 'Puedo explicarte cesantías, intereses, prima, vacaciones, indemnización, descuentos o el plazo de pago de esta liquidación. Prueba con una de las sugerencias.',
        };

        return ['answer' => $answer, 'followups' => self::suggestions($r, $ctx)];
    }
}
