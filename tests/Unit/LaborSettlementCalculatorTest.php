<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Services\LaborSettlementCalculator;
class LaborSettlementCalculatorTest extends TestCase {
 private function sample():array {return ['start_date'=>'2023-01-15','end_date'=>'2026-06-30','basic_salary'=>1750905,'average_extras'=>150000,'transport_allowance'=>249095,'min_wage'=>1750905,'cesantias_days'=>180,'prima_days'=>180,'vacation_days_pending'=>15,'pending_salary_days'=>15,'pending_transport_days'=>15,'wrongful_dismissal'=>false];}
 public function test_benefits_and_vacation_days_are_not_confused():void{$r=(new LaborSettlementCalculator())->calculate($this->sample());$this->assertSame(1075000,$r['severance']);$this->assertSame(64500,$r['severance_interest']);$this->assertSame(950453,$r['vacations']);$this->assertSame(875453,$r['salary_due']);}
 public function test_below_10_minimum_wages_applies_30_days_first_year():void{$r=(new LaborSettlementCalculator())->calculate([...$this->sample(),'start_date'=>'2026-01-01','end_date'=>'2026-04-01','wrongful_dismissal'=>true,'contract_type'=>'indefinido']);$this->assertSame(30.0,$r['indemnification_days']);}
 public function test_equal_to_10_minimum_wages_applies_high_tier():void{$r=(new LaborSettlementCalculator())->calculate([...$this->sample(),'start_date'=>'2026-01-01','end_date'=>'2026-04-01','basic_salary'=>17509050,'average_extras'=>0,'transport_allowance'=>0,'wrongful_dismissal'=>true,'contract_type'=>'indefinido']);$this->assertSame(20.0,$r['indemnification_days']);}
 public function test_fixed_term_uses_remaining_contract_days():void{$r=(new LaborSettlementCalculator())->calculate([...$this->sample(),'contract_type'=>'fijo','agreed_end_date'=>'2026-07-10','wrongful_dismissal'=>true]);$this->assertSame(10.0,$r['indemnification_days']);}
 public function test_february_counts_as_full_commercial_month():void{$this->assertSame(30,LaborSettlementCalculator::days360('2026-02-01','2026-02-28'));}
 public function test_no_indemnification_for_normal_termination():void{$r=(new LaborSettlementCalculator())->calculate($this->sample());$this->assertSame(0,$r['indemnification']);}
}
