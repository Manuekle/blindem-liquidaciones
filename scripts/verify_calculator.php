<?php

require __DIR__.'/../app/Services/LaborSettlementCalculator.php';
use App\Services\LaborSettlementCalculator as C;

function check($expect, $actual, $desc)
{
    if ($expect !== $actual) {
        fwrite(STDERR, "FAIL $desc: expected ".var_export($expect, true).' got '.var_export($actual, true)."\n");
        exit(1);
    } echo "PASS $desc\n";
}
$c = new C;
$sample = ['start_date' => '2023-01-15', 'end_date' => '2026-06-30', 'basic_salary' => 1750905, 'average_extras' => 150000, 'transport_allowance' => 249095, 'min_wage' => 1750905, 'cesantias_days' => 180, 'prima_days' => 180, 'vacation_days_pending' => 15, 'pending_salary_days' => 15, 'pending_transport_days' => 15, 'wrongful_dismissal' => false];
$r = $c->calculate($sample);
check(1075000, $r['severance'], 'cesantías del Excel');
check(64500, $r['severance_interest'], 'intereses del Excel');
check(1075000, $r['premium'], 'prima del Excel');
check(875453, $r['vacations'], 'vacaciones 15 días pendientes sin horas extras');
check(875453, $r['salary_due'], 'salario pendiente');
check(124548, $r['transport_due'], 'auxilio pendiente');
check(30, C::days360('2026-02-01', '2026-02-28'), 'febrero como mes comercial completo');
check(30, $c->calculate([...$sample, 'start_date' => '2026-01-01', 'end_date' => '2026-01-30', 'cesantias_days' => null, 'prima_days' => null])['service_days_360'], 'convención 30/360');
check(30.0, $c->calculate([...$sample, 'start_date' => '2026-01-01', 'end_date' => '2026-03-01', 'wrongful_dismissal' => true, 'contract_type' => 'indefinido'])['indemnification_days'], 'indemnización primer año no se prorratea');
$high = $c->calculate([...$sample, 'basic_salary' => 17509050, 'average_extras' => 0, 'transport_allowance' => 0, 'wrongful_dismissal' => true, 'contract_type' => 'indefinido']);
check(20.0, $c->calculate([...$sample, 'basic_salary' => 17509050, 'average_extras' => 0, 'transport_allowance' => 0, 'start_date' => '2026-02-01', 'end_date' => '2026-02-15', 'wrongful_dismissal' => true, 'contract_type' => 'indefinido'])['indemnification_days'], 'umbral 10 SMLMV');
check(0, $c->calculate([...$sample, 'wrongful_dismissal' => false])['indemnification'], 'sin indemnización no aplica');
check(10.0, $c->calculate([...$sample, 'contract_type' => 'fijo', 'agreed_end_date' => '2026-07-10', 'wrongful_dismissal' => true])['indemnification_days'], 'término fijo días faltantes');
check($r['gross'] - $r['total_deductions'], $r['net'], 'devengado menos deducciones coincide con neto');
echo 'All calculator checks passed. Net sample: COP '.number_format($r['net'],0,',','.')."\n";
