<?php

namespace App\Services\Ai\Simulated;

use App\Services\LaborSettlementCalculator;
use Illuminate\Support\Carbon;

/**
 * Looks over a contract before it is settled and lists what a payroll reviewer would question.
 */
class ContractReview
{
    private array $findings = [];

    public function __invoke(array $input): array
    {
        $c = $input['contract'];
        $p = $input['parameters'] ?? null;
        $today = $input['today'];
        $cut = $c['end_date'] ?? $today;
        $salary = (int) $c['basic_salary'];

        if (! $c['salary_components_reviewed']) {
            $this->add('alta', 'Separación de extras y recargos sin confirmar', 'El cálculo queda bloqueado hasta confirmar en el formulario que extras y recargos están en campos separados.');
        }
        if ($c['status'] === 'terminated' && ! $c['termination_reason']) {
            $this->add('alta', 'Falta el motivo de retiro', 'Sin motivo no se puede determinar si corresponde indemnización.');
        }
        if ($c['status'] === 'terminated' && ! $c['end_date']) {
            $this->add('alta', 'Contrato retirado sin fecha de retiro', 'La liquidación se proyectaría a hoy. Registra el último día laborado.');
        }
        if ($c['status'] === 'active' && $c['end_date'] && $c['end_date'] < $today) {
            $this->add('media', 'Fecha de retiro vencida en un contrato activo', 'La fecha de retiro ('.Text::date($c['end_date']).') ya pasó. Cambia el estado a retirado si la persona ya salió.');
        }
        if ($c['type_method'] === 'fijo' && ! $c['agreed_end_date']) {
            $this->add('alta', 'Término fijo sin fecha pactada', 'La indemnización de un contrato a término fijo depende de los días que faltan para la fecha pactada.');
        }
        if ($c['type_method'] === 'fijo' && $c['status'] === 'active' && $c['agreed_end_date']) {
            $days = Carbon::parse($today)->diffInDays(Carbon::parse($c['agreed_end_date']), false);
            if ($days >= 0 && $days <= 45) {
                $this->add($days <= 30 ? 'alta' : 'media', "El contrato vence en {$days} días", 'Si no se va a renovar, el preaviso escrito debe entregarse con al menos 30 días de anticipación (art. 46 CST); de lo contrario se prorroga por el mismo término.');
            }
        }
        if ($p) {
            if ($salary < $p['min_wage']) {
                $this->add('alta', 'Salario por debajo del mínimo '.$p['year'], 'El salario básico ('.Text::money($salary).') es menor al salario mínimo vigente ('.Text::money($p['min_wage']).').');
            }
            $transport = (int) $c['transport_allowance'];
            if ($transport > 0 && $salary > 2 * $p['min_wage']) {
                $this->add('media', 'Auxilio de transporte que no corresponde', 'Quien gana más de 2 salarios mínimos no tiene derecho al auxilio. El cálculo lo excluirá automáticamente.');
            } elseif ($transport === 0 && $salary <= 2 * $p['min_wage']) {
                $this->add('media', 'Falta el auxilio de transporte', 'Con un salario hasta 2 salarios mínimos corresponde auxilio de '.Text::money($p['transport_allowance']).' mensuales, que también entra en la base de prestaciones.');
            }
        } else {
            $this->add('alta', 'No hay parámetros laborales para el año de corte', 'Pide al administrador registrar la vigencia de '.substr($cut, 0, 4).'.');
        }
        if ($salary > 0 && (int) $c['average_extras'] > $salary * 0.5) {
            $this->add('media', 'Promedio de horas extras inusualmente alto', 'Las extras equivalen al '.Text::percent($c['average_extras'], $salary).'% del salario. Verifica que el promedio corresponda a los últimos 12 meses.');
        }
        if ($c['vacation_days_pending'] !== null && $c['start_date'] <= $cut) {
            $accrued = LaborSettlementCalculator::days360($c['start_date'], $cut) * 15 / 360 - (float) $c['vacation_days_taken'];
            if ((float) $c['vacation_days_pending'] > $accrued + 1) {
                $this->add('media', 'Vacaciones pendientes por encima de lo causado', 'Registraste '.$c['vacation_days_pending'].' días, pero el tiempo trabajado solo causa '.number_format(max(0, $accrued), 2, ',', '.').'. Deja el campo en blanco para calcularlo automáticamente.');
            }
        }
        if ($c['status'] === 'terminated' && $input['settlements_count'] === 0) {
            $this->add('media', 'Liquidación pendiente de guardar', 'El contrato está retirado y aún no hay un cálculo guardado en el historial.');
        }
        if (! $input['worker']['email']) {
            $this->add('baja', 'El trabajador no tiene correo registrado', 'Agrégalo para poder enviarle la liquidación y la carta.');
        }

        $blocking = count(array_filter($this->findings, fn ($f) => $f['severity'] === 'alta'));
        usort($this->findings, fn ($a, $b) => ['alta' => 0, 'media' => 1, 'baja' => 2][$a['severity']] <=> ['alta' => 0, 'media' => 1, 'baja' => 2][$b['severity']]);

        return [
            'status' => $blocking ? 'attention' : ($this->findings ? 'review' : 'ok'),
            'summary' => match (true) {
                $blocking > 0 => "Encontré {$blocking} ".($blocking === 1 ? 'punto que requiere' : 'puntos que requieren').' atención antes de liquidar.',
                (bool) $this->findings => 'El contrato se puede liquidar; revisa estas observaciones menores.',
                default => 'No encontré inconsistencias. El contrato está listo para liquidar.',
            },
            'findings' => $this->findings,
        ];
    }

    private function add(string $severity, string $title, string $detail): void
    {
        $this->findings[] = compact('severity', 'title', 'detail');
    }
}
