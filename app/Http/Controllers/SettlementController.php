<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Settlement;
use App\Services\LaborSettlementCalculator;
use App\Services\SettlementInputs;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettlementController
{
    public function __construct(private LaborSettlementCalculator $calculator, private SettlementInputs $inputFactory) {}

    private function context(Contract $c): array
    {
        $c->loadMissing('worker.documentType', 'company', 'jobTitle', 'contractType', 'terminationReason');

        return ['worker_name' => $c->worker->full_name, 'document_type' => $c->worker->documentType->code, 'document_number' => $c->worker->document_number, 'company_name' => $c->company->name, 'company_nit' => $c->company->nit, 'job_title' => $c->jobTitle->name, 'contract_type_name' => $c->contractType->name, 'termination_reason' => $c->terminationReason?->name ?? 'No aplica'];
    }

    private function getResult(Contract $c): array
    {
        try {
            $data = $this->inputFactory->fromContract($c);

            return ['inputs' => $data, 'result' => $this->calculator->calculate($data), 'error' => null];
        } catch (\DomainException $e) {
            return ['inputs' => $data ?? [], 'result' => null, 'error' => $e->getMessage()];
        }
    }

    public function show(Contract $contract)
    {
        $display = $this->context($contract);
        $preview = $this->getResult($contract);
        $history = $contract->settlements()->latest('id')->paginate(12);

        return view('liquidations.show', compact('contract', 'preview', 'history', 'display') + ['saved' => null, 'legacyContext' => false]);
    }

    public function history(Contract $contract, Settlement $settlement)
    {
        abort_unless($settlement->contract_id === $contract->id, 404);
        $preview = ['inputs' => $settlement->inputs, 'result' => $settlement->result, 'error' => null];
        $display = $settlement->inputs['context'] ?? $this->context($contract);
        $legacyContext = ! isset($settlement->inputs['context']);
        $history = $contract->settlements()->latest('id')->paginate(12);

        return view('liquidations.show', compact('contract', 'preview', 'display', 'history', 'legacyContext') + ['saved' => $settlement]);
    }

    public function store(Request $request, Contract $contract)
    {
        $contract->load('contractType');
        $preview = $this->getResult($contract);
        if ($preview['error']) {
            return back()->with('error', $preview['error']);
        }
        $contract->settlements()->create(['calculated_by' => $request->user()->id, 'calculation_version' => LaborSettlementCalculator::VERSION, 'inputs' => $preview['inputs'], 'result' => $preview['result'], 'net_amount' => $preview['result']['net']]);

        return back()->with('success', 'Liquidación guardada en el historial con su fotografía de cálculo.');
    }

    public function export(Contract $contract): StreamedResponse
    {
        $contract->load('worker.documentType', 'company', 'jobTitle', 'contractType', 'terminationReason');
        $preview = $this->getResult($contract);
        if ($preview['error']) {
            throw ValidationException::withMessages(['liquidacion' => $preview['error']]);
        }

        return $this->download($preview['inputs'], $preview['result'], $preview['inputs']['context'], 'liquidacion-'.$contract->id.'.xlsx');
    }

    public function exportHistory(Contract $contract, Settlement $settlement): StreamedResponse
    {
        abort_unless($settlement->contract_id === $contract->id, 404);

        return $this->download($settlement->inputs, $settlement->result, $settlement->inputs['context'] ?? $this->context($contract), 'liquidacion-'.$contract->id.'-historial-'.$settlement->id.'.xlsx', ! isset($settlement->inputs['context']));
    }

    private function download(array $i, array $r, array $display, string $filename, bool $legacyContext = false): StreamedResponse
    {
        $ss = new Spreadsheet;
        $s = $ss->getActiveSheet();
        $s->setTitle('Liquidación');
        $s->getColumnDimension('A')->setWidth(42);
        $s->getColumnDimension('B')->setWidth(27);
        $s->getColumnDimension('C')->setWidth(20);
        $s->getColumnDimension('D')->setWidth(46);
        $s->mergeCells('A1:D1');
        $s->setCellValue('A1', 'BLINDEM  /  LIQUIDACIÓN LABORAL');
        $s->getRowDimension(1)->setRowHeight(38);
        $s->mergeCells('A2:D2');
        $s->setCellValue('A2', $legacyContext ? 'Histórico antiguo: identificación actual; los importes conservan el cálculo guardado.' : 'Documento informativo · verificar casos particulares con asesoría laboral');
        $rows = [4 => ['Trabajador', $display['worker_name'], 'Documento', trim(($display['document_type'] ?? '').' '.$display['document_number'])], 5 => ['Empresa', $display['company_name'], 'NIT', $display['company_nit']], 6 => ['Contrato', $display['contract_type_name'], 'Cargo', $display['job_title']], 7 => ['Fecha de ingreso', $i['start_date'], 'Fecha de retiro', $i['end_date']], 8 => ['Días servicio 30/360', $r['service_days_360'], 'Días calendario', $r['service_days_actual']], 10 => ['CONCEPTO', 'VALOR (COP)', 'DÍAS / BASE', 'FÓRMULA'], 11 => ['Cesantías', $r['severance'], $r['cesantias_days'], $r['formulas']['severance']], 12 => ['Intereses sobre cesantías', $r['severance_interest'], $r['cesantias_days'], $r['formulas']['severance_interest']], 13 => ['Prima de servicios', $r['premium'], $r['prima_days'], $r['formulas']['premium']], 14 => ['Vacaciones compensadas', $r['vacations'], $r['vacation_days_pending'], $r['formulas']['vacations']], 15 => ['Salario pendiente', $r['salary_due'], $r['pending_salary_days'] ?? $i['pending_salary_days'], $r['formulas']['salary_due']], 16 => ['Auxilio de transporte pendiente', $r['transport_due'], $r['pending_transport_days'] ?? $i['pending_transport_days'], $r['formulas']['transport_due']], 17 => ['Indemnización', $r['indemnification'], $r['indemnification_days'], 'Art. 64 CST / tipo de contrato'], 18 => ['TOTAL DEVENGADO', $r['gross'], '', ''], 20 => ['Deducción salud ('.(($r['health_rate'] ?? 0.04) * 100).'%)', $r['health'], '', $r['formulas']['health']], 21 => ['Deducción pensión ('.(($r['pension_rate'] ?? 0.04) * 100).'%)', $r['pension'], '', $r['formulas']['pension']], 22 => ['Anticipos', $r['advances'], '', ''], 23 => ['Otras deducciones', $r['other_deductions'], '', ''], 24 => ['TOTAL DEDUCCIONES', $r['total_deductions'], '', ''], 26 => ['NETO A RECIBIR', $r['net'], '', '']];
        foreach ($rows as $n => $cols) {
            foreach ($cols as $j => $value) {
                is_string($value) ? $s->setCellValueExplicit([$j + 1, $n], $value, DataType::TYPE_STRING) : $s->setCellValue([$j + 1, $n], $value);
            }$s->getRowDimension($n)->setRowHeight(in_array($n, [10, 18, 24, 26]) ? 27 : 22);
        }
        $s->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2938D8');
        $s->getStyle('A1:D1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
        foreach ([10, 18, 24] as $n) {
            $s->getStyle("A{$n}:D{$n}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFF2F8');
            $s->getStyle("A{$n}:D{$n}")->getFont()->setBold(true);
        }
        $s->getStyle('A26:D26')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EBFF');
        $s->getStyle('A26:D26')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('2938D8');
        $s->getStyle('B11:B26')->getNumberFormat()->setFormatCode('"$" #,##0;[Red]("$" #,##0)');
        $s->getStyle('A1:D28')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $s->getStyle('D10:D26')->getAlignment()->setWrapText(true);
        $s->mergeCells('A29:D29');
        $s->setCellValue('A29', 'Firma trabajador: ____________________     Firma empleador: ____________________');
        $source = $ss->createSheet();
        $source->setTitle('Parámetros y fuentes');
        $source->getColumnDimension('A')->setWidth(38);
        $source->getColumnDimension('B')->setWidth(90);
        $sourceRows = [['Parámetro', 'Valor / Fuente'], ['Versión del motor', $r['version']], ['Vigencia', $i['parameter_year'] ?? substr($i['end_date'], 0, 4)], ['SMLMV', $r['min_wage']], ['Base prestacional', $r['prestational_base']], ['Base vacaciones', $r['vacation_base']], ['Base indemnización', $r['indemnification_base'] ?? null], ['Salario básico', $i['basic_salary']], ['Promedio extras y descansos', $i['average_extras'] ?? 0], ['Promedio recargos ordinarios', $i['average_surcharges'] ?? 'No separado en esta versión'], ['Auxilio de transporte', $i['transport_allowance']], ['Auxilio de referencia', $i['reference_transport_allowance'] ?? 'No guardado'], ['Tarifa salud', $r['health_rate'] ?? 0.04], ['Tarifa pensión', $r['pension_rate'] ?? 0.04], ['Tarifa intereses', $r['interest_rate'] ?? 0.12], ['Motivo de retiro', $display['termination_reason']], ['Método indemnización', $i['contract_type']], ['SMLMV: fuente', $i['wage_source'] ?? 'No guardada en esta versión'], ['Transporte: fuente', $i['transport_source'] ?? 'No guardada en esta versión'], ['CST, arts. 64, 189 y 192', 'https://www.cancilleria.gov.co/normograma/compilacion/docs/codigo_sustantivo_trabajo.htm'], ['Vacaciones compensadas: aportes', 'https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=195887']];
        foreach ($sourceRows as $row => $values) {
            foreach ($values as $column => $value) {
                if (is_string($value)) {
                    $source->setCellValueExplicit([$column + 1, $row + 1], $value, DataType::TYPE_STRING);
                } else {
                    $source->setCellValue([$column + 1, $row + 1], $value);
                }
            }
        }

        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
            $ss->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
