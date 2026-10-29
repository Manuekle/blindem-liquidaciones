<?php
namespace App\Services;
use DateTimeImmutable;
use DomainException;
/**
 * Colombian ordinary employment settlement, conceptual reference model.
 * Monetary values are integer COP; vacations_due_days are days of REST already earned,
 * not elapsed service days. Individual exceptions require legal review.
 */
final class LaborSettlementCalculator {
 public const VERSION='1.0.0';
 public static function days360(string $from,string $to):int {
  $a=new DateTimeImmutable($from);$b=new DateTimeImmutable($to);
  if($b<$a)throw new DomainException('La fecha de retiro no puede ser anterior al ingreso.');
  // Último día de febrero se considera día 30 en convención comercial 30/360.
  $endDay=(int)$b->format('j');
  if((int)$b->format('n')===2 && $endDay===(int)$b->format('t'))$endDay=30;
  return max(1,((int)$b->format('Y')-(int)$a->format('Y'))*360+((int)$b->format('n')-(int)$a->format('n'))*30+(min(30,$endDay)-min(30,(int)$a->format('j')))+1);
 }
 public static function daysActual(string $from,string $to):int {
  $a=new DateTimeImmutable($from);$b=new DateTimeImmutable($to);
  if($b<$a)throw new DomainException('La fecha de retiro no puede ser anterior al ingreso.');
  return (int)$a->diff($b)->days+1;
 }
 private static function money(float $v):int{return (int)round($v+1.0e-8,0,PHP_ROUND_HALF_UP);}
 public function calculate(array $data):array {
  $start=(string)($data['start_date']??'');
  $end=(string)($data['end_date']??date('Y-m-d'));
  $salary=(int)($data['basic_salary']??0);$extras=(int)($data['average_extras']??0);
  $transport=(int)($data['transport_allowance']??0);$smlmv=(int)($data['min_wage']??1750905);
  if($salary<=0||$smlmv<=0)throw new DomainException('El salario y SMLMV deben ser mayores a cero.');
  $service360=self::days360($start,$end);$actual=self::daysActual($start,$end);
  $y=(int)substr($end,0,4);$month=(int)substr($end,5,2);
  $startYear=max($start,sprintf('%04d-01-01',$y));
  $startSem=max($start,sprintf('%04d-%02d-01',$y,$month<=6?1:7));
  $cesDays=(int)($data['cesantias_days']??min(360,self::days360($startYear,$end)));
  $primaDays=(int)($data['prima_days']??min(180,self::days360($startSem,$end)));
  $vacDays=(float)($data['vacation_days_pending']??0);
  $salaryDays=(int)($data['pending_salary_days']??0);$transportDays=(int)($data['pending_transport_days']??0);
  foreach (['cesantías'=>$cesDays,'prima'=>$primaDays,'salario pendiente'=>$salaryDays,'transporte pendiente'=>$transportDays] as $name=>$value) if($value<0)throw new DomainException("Días inválidos para {$name}.");
  if($cesDays>360||$primaDays>180||$salaryDays>30||$transportDays>30||$vacDays<0)throw new DomainException('Los días exceden los límites permitidos.');
  $warnings=[];
  if(($salary+$extras)>2*$smlmv&&$transport>0){$warnings[]='Auxilio de transporte superior al umbral de 2 SMMLV: se excluye automáticamente del cálculo.';$transport=0;}
  $base=$salary+$extras+$transport;$vacBase=$salary+$extras;
  $severance=self::money($base*$cesDays/360);
  $severanceInterest=self::money($severance*$cesDays*0.12/360);
  $premium=self::money($base*$primaDays/360);
  $vacation=self::money(($vacBase/30)*$vacDays);
  $salaryDue=self::money(($salary/30)*$salaryDays);
  $transportDue=self::money(($transport/30)*$transportDays);
  $indemnification=0;$indDays=0.;$contractType=(string)($data['contract_type']??'indefinido');
  if(!empty($data['wrongful_dismissal'])){
   if(array_key_exists('manual_indemnification',$data)&&$data['manual_indemnification']!==null&&$data['manual_indemnification']!==''){
     $indemnification=(int)$data['manual_indemnification'];$warnings[]='Indemnización ingresada manualmente: verificar soporte jurídico.';
   } elseif($contractType==='fijo'){
     $agreed=$data['agreed_end_date']??null;
     if(!$agreed)throw new DomainException('Indique la fecha de finalización pactada en contrato fijo para indemnizar.');
     $last=new DateTimeImmutable($agreed);$retired=new DateTimeImmutable($end);
     $remaining=$last>$retired?(int)$retired->diff($last)->days:0;
     $indDays=(float)$remaining;
     $indemnification=self::money($vacBase/30*$remaining);
   } elseif($contractType==='indefinido'){
     $lowTier=$vacBase<10*$smlmv; // Legal threshold: strictly below 10 SMMLV
     $first=$lowTier?30:20;$additional=$lowTier?20:15;
     $indDays=$actual<=365?$first:$first+($actual-365)/365*$additional;
     $indemnification=self::money($vacBase/30*$indDays);
   } else {
     $warnings[]='Contrato por obra o labor: se requiere indemnización manual conforme duración de la obra (mínimo legal aplicable de 15 días).';
   }
  }
  if(!empty($data['wrongful_dismissal'])&&$contractType==='obra'&&$indemnification===0)$warnings[]='No se ha calculado indemnización automática para obra o labor.';
  $health=self::money($salaryDue*0.04);$pension=self::money($salaryDue*0.04);
  $advances=(int)($data['advances']??0);$other=(int)($data['other_deductions']??0);
  if($advances<0||$other<0||$indemnification<0)throw new DomainException('No se permiten valores negativos en deducciones o indemnización.');
  $devengado=$severance+$severanceInterest+$premium+$vacation+$salaryDue+$transportDue+$indemnification;
  $deductions=$health+$pension+$advances+$other;$net=$devengado-$deductions;
  if($net<0)$warnings[]='Las deducciones superan el devengado: revisar autorizaciones y límites legales antes del pago.';
  return [
   'version'=>self::VERSION,'effective_end_date'=>$end,'service_days_360'=>$service360,'service_days_actual'=>$actual,
   'cesantias_days'=>$cesDays,'prima_days'=>$primaDays,'vacation_days_pending'=>$vacDays,
   'prestational_base'=>$base,'vacation_base'=>$vacBase,'indemnification_base'=>$vacBase,'min_wage'=>$smlmv,
   'severance'=>$severance,'severance_interest'=>$severanceInterest,'premium'=>$premium,
   'vacations'=>$vacation,'salary_due'=>$salaryDue,'transport_due'=>$transportDue,
   'indemnification_days'=>round($indDays,3),'indemnification'=>$indemnification,
   'health'=>$health,'pension'=>$pension,'advances'=>$advances,'other_deductions'=>$other,
   'gross'=>$devengado,'total_deductions'=>$deductions,'net'=>$net,'warnings'=>$warnings,
   'formulas'=>[
    'severance'=>'Base prestacional × días cesantías / 360',
    'severance_interest'=>'Cesantías × días cesantías × 12% / 360',
    'premium'=>'Base prestacional × días prima / 360',
    'vacations'=>'Base vacaciones / 30 × días de descanso pendientes',
    'salary_due'=>'Salario básico / 30 × días salario pendientes',
    'transport_due'=>'Auxilio transporte / 30 × días transporte pendientes',
    'health'=>'Salario pendiente × 4% (no incluye vacaciones compensadas)',
    'pension'=>'Salario pendiente × 4% (no incluye vacaciones compensadas)',
   ],
  ];
 }
}
