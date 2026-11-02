<?php

namespace App\Services\Ai\Simulated;

/**
 * Drafts the letter that accompanies a settlement payment.
 */
class SettlementLetter
{
    public function __invoke(array $input): array
    {
        $ctx = $input['context'];
        $i = $input['inputs'];
        $r = $input['result'];
        $kind = $input['kind'];
        $date = \Illuminate\Support\Carbon::parse($input['today'])->locale('es')->translatedFormat('j \d\e F \d\e Y');
        $concepts = array_filter([
            'Cesantías' => $r['severance'], 'Intereses sobre cesantías' => $r['severance_interest'], 'Prima de servicios' => $r['premium'],
            'Vacaciones compensadas' => $r['vacations'], 'Salario pendiente' => $r['salary_due'], 'Auxilio de transporte' => $r['transport_due'],
            'Indemnización' => $r['indemnification'],
        ]);
        $lines = implode("\n", array_map(fn ($label, $value) => "• {$label}: ".Text::money($value), array_keys($concepts), $concepts));
        $period = 'del '.Text::date($i['start_date']).' al '.Text::date($r['effective_end_date']);

        if ($kind === 'paz_y_salvo') {
            return [
                'subject' => 'Paz y salvo laboral — '.$ctx['worker_name'],
                'body' => "{$date}\n\nYo, {$ctx['worker_name']}, identificado(a) con {$ctx['document_type']} {$ctx['document_number']}, declaro que recibí de {$ctx['company_name']} (nit {$ctx['company_nit']}) la suma de ".Text::money($r['net'])." por concepto de liquidación final de prestaciones sociales del contrato ".Text::contractKind($ctx['contract_type_name'])." que rigió {$period}, en el cargo de {$ctx['job_title']}.\n\nDetalle de lo recibido:\n{$lines}\n• Deducciones: −".Text::money($r['total_deductions'])."\n\nCon este pago declaro a paz y salvo a la empresa por los conceptos aquí liquidados.\n\n\n______________________________\n{$ctx['worker_name']}\n{$ctx['document_type']} {$ctx['document_number']}",
            ];
        }

        return [
            'subject' => 'Liquidación de prestaciones sociales — '.$ctx['worker_name'],
            'body' => "{$date}\n\nSeñor(a)\n{$ctx['worker_name']}\n{$ctx['document_type']} {$ctx['document_number']}\n\nAsunto: liquidación final de prestaciones sociales\n\nCordial saludo.\n\nCon ocasión de la terminación de su contrato ".Text::contractKind($ctx['contract_type_name'])." {$period}, en el cargo de {$ctx['job_title']}".($ctx['termination_reason'] !== 'No aplica' ? ', por '.$this->lower($ctx['termination_reason']) : '').", le informamos los valores de su liquidación:\n\n{$lines}\n\nTotal devengado: ".Text::money($r['gross'])."\nDeducciones (salud, pensión y otras): −".Text::money($r['total_deductions'])."\nNeto a pagar: ".Text::money($r['net'])."\n\nEl valor será consignado en la cuenta registrada para el pago de nómina. Si tiene dudas sobre algún concepto, puede comunicarse con el área de gestión humana.\n\nAtentamente,\n\n\n______________________________\n{$ctx['company_name']}\nNit {$ctx['company_nit']}",
        ];
    }

    private function lower(string $text): string
    {
        return mb_strtolower($text);
    }
}
