<?php
namespace App\Http\Controllers;
use App\Models\{Contract,Settlement};
use App\Services\LaborSettlementCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment,Fill,Font};
use Symfony\Component\HttpFoundation\StreamedResponse;
class SettlementController {
 public function __construct(private LaborSettlementCalculator $calculator){}
 private function inputs(Contract $c):array {
  return ['start_date'=>$c->start_date->toDateString(),'end_date'=>$c->end_date?->toDateString()??now()->toDateString(),
   'agreed_end_date'=>$c->agreed_end_date?->toDateString(),'basic_salary'=>$c->basic_salary,'average_extras'=>$c->average_extras,
   'transport_allowance'=>$c->transport_allowance,'min_wage'=>config('liquidations.min_wage'),
   'cesantias_days'=>$c->cesantias_days,'prima_days'=>$c->prima_days,'vacation_days_pending'=>$c->vacation_days_pending,
   'pending_salary_days'=>$c->pending_salary_days,'pending_transport_days'=>$c->pending_transport_days,
   'advances'=>$c->advances,'other_deductions'=>$c->other_deductions,'wrongful_dismissal'=>$c->wrongful_dismissal,
   'manual_indemnification'=>$c->manual_indemnification,'contract_type'=>$c->contractType->code];
 }
 private function getResult(Contract $c):array {
  try{$data=$this->inputs($c);return ['inputs'=>$data,'result'=>$this->calculator->calculate($data),'error'=>null];}
  catch(\DomainException|\Exception $e){return ['inputs'=>$data??[],'result'=>null,'error'=>$e->getMessage()];}
 }
 public function show(Contract $contract){$contract->load('worker.documentType','company','jobTitle','contractType','terminationReason');$preview=$this->getResult($contract);$history=$contract->settlements()->latest()->take(8)->get();return view('liquidations.show',compact('contract','preview','history'));}
 public function store(Request $request,Contract $contract){$contract->load('contractType');$preview=$this->getResult($contract);if($preview['error'])return back()->with('error',$preview['error']);
  $contract->settlements()->create(['calculated_by'=>$request->user()->id,'calculation_version'=>LaborSettlementCalculator::VERSION,'inputs'=>$preview['inputs'],'result'=>$preview['result'],'net_amount'=>$preview['result']['net']]);
  return back()->with('success','Liquidación guardada en el historial con su fotografía de cálculo.');
 }
 public function export(Contract $contract):StreamedResponse {
  $contract->load('worker.documentType','company','jobTitle','contractType','terminationReason');$preview=$this->getResult($contract);
  if($preview['error'])throw ValidationException::withMessages(['liquidacion'=>$preview['error']]);
  $r=$preview['result'];$i=$preview['inputs'];$ss=new Spreadsheet();$s=$ss->getActiveSheet();$s->setTitle('Liquidación');$s->getColumnDimension('A')->setWidth(42);$s->getColumnDimension('B')->setWidth(27);$s->getColumnDimension('C')->setWidth(20);$s->getColumnDimension('D')->setWidth(46);
  $s->mergeCells('A1:D1');$s->setCellValue('A1','BLINDEM  /  LIQUIDACIÓN LABORAL');$s->getRowDimension(1)->setRowHeight(38);
  $s->mergeCells('A2:D2');$s->setCellValue('A2','Documento informativo · verificar casos particulares con asesoría laboral');
  $rows=[4=>['Trabajador',$contract->worker->full_name,'Documento',$contract->worker->document_number],5=>['Empresa',$contract->company->name,'NIT',$contract->company->nit],6=>['Contrato',$contract->contractType->name,'Cargo',$contract->jobTitle->name],7=>['Fecha de ingreso',$i['start_date'],'Fecha de retiro',$i['end_date']],8=>['Días servicio 30/360',$r['service_days_360'],'Días calendario',$r['service_days_actual']],10=>['CONCEPTO','VALOR (COP)','DÍAS / BASE','FÓRMULA'],11=>['Cesantías',$r['severance'],$r['cesantias_days'],$r['formulas']['severance']],12=>['Intereses sobre cesantías',$r['severance_interest'],$r['cesantias_days'],$r['formulas']['severance_interest']],13=>['Prima de servicios',$r['premium'],$r['prima_days'],$r['formulas']['premium']],14=>['Vacaciones compensadas',$r['vacations'],$r['vacation_days_pending'],$r['formulas']['vacations']],15=>['Salario pendiente',$r['salary_due'],$i['pending_salary_days'],$r['formulas']['salary_due']],16=>['Auxilio de transporte pendiente',$r['transport_due'],$i['pending_transport_days'],$r['formulas']['transport_due']],17=>['Indemnización',$r['indemnification'],$r['indemnification_days'],'Art. 64 CST / tipo de contrato'],18=>['TOTAL DEVENGADO',$r['gross'],'',''],20=>['Deducción salud (4%)',$r['health'],'',$r['formulas']['health']],21=>['Deducción pensión (4%)',$r['pension'],'',$r['formulas']['pension']],22=>['Anticipos',$r['advances'],'',''],23=>['Otras deducciones',$r['other_deductions'],'',''],24=>['TOTAL DEDUCCIONES',$r['total_deductions'],'',''],26=>['NETO A RECIBIR',$r['net'],'','']];
  foreach($rows as $n=>$cols){foreach($cols as $j=>$value)$s->setCellValue([$j+1,$n],$value);$s->getRowDimension($n)->setRowHeight(in_array($n,[10,18,24,26])?27:22);}
  $s->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2938D8');$s->getStyle('A1:D1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
  foreach([10,18,24] as $n){$s->getStyle("A{$n}:D{$n}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFF2F8');$s->getStyle("A{$n}:D{$n}")->getFont()->setBold(true);}
  $s->getStyle('A26:D26')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EBFF');$s->getStyle('A26:D26')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('2938D8');
  $s->getStyle('B11:B26')->getNumberFormat()->setFormatCode('"$" #,##0;[Red]("$" #,##0)');
  $s->getStyle('A1:D28')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);$s->getStyle('D10:D26')->getAlignment()->setWrapText(true);
  $s->mergeCells('A29:D29');$s->setCellValue('A29','Firma trabajador: ____________________     Firma empleador: ____________________');
  $source=$ss->createSheet();$source->setTitle('Parámetros y fuentes');$source->getColumnDimension('A')->setWidth(38);$source->getColumnDimension('B')->setWidth(80);$source->fromArray([['Parámetro','Valor / Fuente'],['Versión del motor',$r['version']],['SMLMV',$r['min_wage']],['Base prestacional',$r['prestational_base']],['Base vacaciones',$r['vacation_base']],['Ley / Código','https://cancilleria.gov.co/normograma/compilacion/docs/codigo_sustantivo_trabajo.htm'],['SMLMV 2026','https://normativa.colpensiones.gov.co/compilacion/docs/decreto_0159_2026.htm'],['Auxilio transporte 2026','https://cancilleria.gov.co/normograma/compilacion/docs/decreto_1470_2025.htm'],['Vacaciones compensadas','https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=71322']],null,'A1');
  return response()->streamDownload(function()use($ss){(new Xlsx($ss))->save('php://output');$ss->disconnectWorksheets();},'liquidacion-'.$contract->id.'-'.$contract->worker->document_number.'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
 }
}
