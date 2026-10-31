<?php

namespace Tests\Unit;

use App\Services\LaborSettlementCalculator;
use PHPUnit\Framework\TestCase;

class LaborSettlementCalculatorTest extends TestCase
{
    private function sample(): array
    {
        return ['start_date' => '2023-01-15', 'end_date' => '2026-06-30', 'basic_salary' => 1750905, 'average_extras' => 150000, 'transport_allowance' => 249095, 'min_wage' => 1750905, 'cesantias_days' => 180, 'prima_days' => 180, 'vacation_days_pending' => 15, 'pending_salary_days' => 15, 'pending_transport_days' => 15, 'wrongful_dismissal' => false];
    }

    public function test_benefits_and_vacation_days_are_not_confused(): void
    {
        $r = (new LaborSettlementCalculator)->calculate($this->sample());
        $this->assertSame(1075000, $r['severance']);
        $this->assertSame(64500, $r['severance_interest']);
        $this->assertSame(875453, $r['vacations']);
        $this->assertSame(875453, $r['salary_due']);
    }

    public function test_below_10_minimum_wages_applies_30_days_first_year(): void
    {
        $r = (new LaborSettlementCalculator)->calculate([...$this->sample(), 'start_date' => '2026-01-01', 'end_date' => '2026-04-01', 'wrongful_dismissal' => true, 'contract_type' => 'indefinido']);
        $this->assertSame(30.0, $r['indemnification_days']);
    }

    public function test_equal_to_10_minimum_wages_applies_high_tier(): void
    {
        $r = (new LaborSettlementCalculator)->calculate([...$this->sample(), 'start_date' => '2026-01-01', 'end_date' => '2026-04-01', 'basic_salary' => 17509050, 'average_extras' => 0, 'transport_allowance' => 0, 'wrongful_dismissal' => true, 'contract_type' => 'indefinido']);
        $this->assertSame(20.0, $r['indemnification_days']);
    }

    public function test_fixed_term_uses_remaining_contract_days(): void
    {
        $r = (new LaborSettlementCalculator)->calculate([...$this->sample(), 'contract_type' => 'fijo', 'agreed_end_date' => '2026-07-10', 'wrongful_dismissal' => true]);
        $this->assertSame(10.0, $r['indemnification_days']);
    }

    public function test_february_counts_as_full_commercial_month(): void
    {
        $this->assertSame(30, LaborSettlementCalculator::days360('2026-02-01', '2026-02-28'));
    }

    public function test_no_indemnification_for_normal_termination(): void
    {
        $r = (new LaborSettlementCalculator)->calculate($this->sample());
        $this->assertSame(0, $r['indemnification']);
    }

    public function test_only_ordinary_surcharges_enter_vacation_base(): void
    {
        $r = (new LaborSettlementCalculator)->calculate([...$this->sample(), 'average_surcharges' => 100000]);
        $this->assertSame(1850905, $r['vacation_base']);
        $this->assertSame(2000905, $r['indemnification_base']);
        $this->assertSame(925453, $r['vacations']);
    }

    public function test_compensated_vacations_do_not_raise_health_or_pension(): void
    {
        $r = (new LaborSettlementCalculator)->calculate([...$this->sample(), 'vacation_days_pending' => 60]);
        $this->assertSame(35018, $r['health']);
        $this->assertSame(35018, $r['pension']);
    }

    public function test_annual_rates_are_used(): void
    {
        $r = (new LaborSettlementCalculator)->calculate([...$this->sample(), 'health_rate' => .03, 'pension_rate' => .05, 'interest_rate' => .1]);
        $this->assertSame(26264, $r['health']);
        $this->assertSame(43773, $r['pension']);
        $this->assertSame(53750, $r['severance_interest']);
    }

    public function test_work_contract_requires_manual_indemnification(): void
    {
        $this->expectException(\DomainException::class);
        (new LaborSettlementCalculator)->calculate([...$this->sample(), 'wrongful_dismissal' => true, 'contract_type' => 'obra']);
    }

    public function test_work_contract_enforces_minimum_15_days(): void
    {
        $this->expectException(\DomainException::class);
        (new LaborSettlementCalculator)->calculate([...$this->sample(), 'wrongful_dismissal' => true, 'contract_type' => 'obra', 'manual_indemnification' => 1]);
    }

    public function test_unknown_year_cannot_silently_use_2026_wage(): void
    {
        $this->expectException(\DomainException::class);
        $data = $this->sample();
        unset($data['min_wage']);
        (new LaborSettlementCalculator)->calculate($data);
    }

    public function test_blank_day_fields_are_derived_from_contract_dates(): void
    {
        $data = [...$this->sample(), 'start_date' => '2026-01-01', 'end_date' => '2026-06-15', 'vacation_days_taken' => 2];
        unset($data['vacation_days_pending'], $data['pending_salary_days'], $data['pending_transport_days']);
        $r = (new LaborSettlementCalculator)->calculate($data);
        $this->assertSame(6.88, $r['vacation_days_accrued']);
        $this->assertSame(4.88, $r['vacation_days_pending']);
        $this->assertSame(15, $r['pending_salary_days']);
        $this->assertSame(15, $r['pending_transport_days']);
        $this->assertSame(['vacation_days_pending', 'pending_salary_days', 'pending_transport_days'], $r['auto_fields']);
    }

    public function test_automatic_salary_days_start_at_hiring_date_within_the_month(): void
    {
        $r = (new LaborSettlementCalculator)->calculate([...$this->sample(), 'start_date' => '2026-06-20', 'end_date' => '2026-06-30', 'pending_salary_days' => null]);
        $this->assertSame(11, $r['pending_salary_days']);
    }

    public function test_explicit_zero_days_are_not_replaced_by_automatic_values(): void
    {
        $r = (new LaborSettlementCalculator)->calculate([...$this->sample(), 'vacation_days_pending' => 0, 'pending_salary_days' => 0, 'pending_transport_days' => 0]);
        $this->assertSame(0, $r['vacations']);
        $this->assertSame(0, $r['salary_due']);
        $this->assertSame([], $r['auto_fields']);
    }

    public function test_invalid_date_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        (new LaborSettlementCalculator)->calculate([...$this->sample(), 'start_date' => '2026-02-30']);
    }
}
