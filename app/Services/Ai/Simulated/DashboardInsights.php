<?php

namespace App\Services\Ai\Simulated;

/**
 * Picks the few observations about the portfolio that need someone's attention today.
 */
class DashboardInsights
{
    public function __invoke(array $input): array
    {
        $insights = [];
        foreach ($input['expiring_fixed'] as $c) {
            $insights[] = [
                'tone' => $c['days_left'] <= 30 ? 'alert' : 'warn',
                'title' => "El contrato de {$c['worker']} vence en {$c['days_left']} días",
                'detail' => 'Término fijo con '.$c['company'].'. Si no se renueva, entrega el preaviso por escrito al menos 30 días antes del '.Text::date($c['agreed_end_date']).'.',
                'contract_id' => $c['id'],
                'action' => 'Revisar contrato',
            ];
        }
        foreach ($input['below_min_wage'] as $c) {
            $insights[] = [
                'tone' => 'alert',
                'title' => "{$c['worker']} gana menos del salario mínimo",
                'detail' => 'Salario de '.Text::money($c['salary']).' frente a '.Text::money($c['min_wage']).' vigente. Actualízalo antes de liquidar.',
                'contract_id' => $c['id'],
                'action' => 'Corregir salario',
            ];
        }
        $pending = $input['terminated_without_settlement'];
        if ($pending) {
            $names = array_column(array_slice($pending, 0, 2), 'worker');
            $insights[] = [
                'tone' => 'warn',
                'title' => count($pending).' '.(count($pending) === 1 ? 'retiro sin liquidación guardada' : 'retiros sin liquidación guardada'),
                'detail' => (count($pending) > 2 ? implode(', ', $names).' y '.(count($pending) - 2).' más' : implode(' y ', $names)).' ya salieron y su cálculo no está en el historial. Guardarlo deja trazabilidad del pago.',
                'contract_id' => $pending[0]['id'],
                'action' => 'Liquidar',
                'route' => 'settlement',
            ];
        }
        if ($input['unreviewed'] > 0) {
            $insights[] = [
                'tone' => 'warn',
                'title' => $input['unreviewed'].' '.($input['unreviewed'] === 1 ? 'contrato no se puede liquidar' : 'contratos no se pueden liquidar'),
                'detail' => 'Falta confirmar la separación de horas extras y recargos en el formulario.',
                'contract_id' => $input['unreviewed_first'],
                'action' => 'Completar',
                'route' => 'edit',
            ];
        }
        if ($input['settlements_count'] > 0) {
            $insights[] = [
                'tone' => 'info',
                'title' => 'Liquidación promedio de '.Text::money($input['settled_net'] / $input['settlements_count']),
                'detail' => $input['settlements_count'].' '.($input['settlements_count'] === 1 ? 'cálculo guardado suma ' : 'cálculos guardados suman ').Text::money($input['settled_net']).' netos.',
                'contract_id' => null,
                'action' => null,
            ];
        } elseif (! $insights) {
            $insights[] = ['tone' => 'good', 'title' => 'Todo en orden', 'detail' => 'No hay vencimientos próximos, salarios bajo el mínimo ni retiros pendientes de liquidar.', 'contract_id' => null, 'action' => null];
        }

        return ['insights' => array_slice($insights, 0, 4)];
    }
}
