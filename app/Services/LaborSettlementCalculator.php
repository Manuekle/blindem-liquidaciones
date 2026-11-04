<?php

namespace App\Services;

use DateTimeImmutable;
use DomainException;

/**
 * Colombian ordinary employment settlement, conceptual reference model.
 * Monetary values are integer COP; vacations_due_days are days of REST already earned,
 * not elapsed service days. Individual exceptions require legal review.
 */
final class LaborSettlementCalculator
{
    public const VERSION = '2.2.0';

    public const METHODS = ['legal' => 'Legal ajustado', 'template' => 'Plantilla Blindem (Excel)'];

    public static function days360(string $from, string $to): int
    {
        $a = new DateTimeImmutable($from);
        $b = new DateTimeImmutable($to);
        if ($b < $a) {
            throw new DomainException('La fecha de retiro no puede ser anterior al ingreso.');
        }
        // Último día de febrero se considera día 30 en convención comercial 30/360.
        $endDay = (int) $b->format('j');
        if ((int) $b->format('n') === 2 && $endDay === (int) $b->format('t')) {
            $endDay = 30;
        }

        return max(1, ((int) $b->format('Y') - (int) $a->format('Y')) * 360 + ((int) $b->format('n') - (int) $a->format('n')) * 30 + (min(30, $endDay) - min(30, (int) $a->format('j'))) + 1);
    }

    public static function daysActual(string $from, string $to): int
    {
        $a = new DateTimeImmutable($from);
        $b = new DateTimeImmutable($to);
        if ($b < $a) {
            throw new DomainException('La fecha de retiro no puede ser anterior al ingreso.');
        }

        return (int) $a->diff($b)->days + 1;
    }

    private static function money(float $v): int
    {
        return (int) round($v + 1.0e-8, 0, PHP_ROUND_HALF_UP);
    }

    private static function blank(array $data, string $key): bool
    {
        return ! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '';
    }

    public function calculate(array $data): array
    {
        $start = (string) ($data['start_date'] ?? '');
        $end = (string) ($data['end_date'] ?? date('Y-m-d'));
        foreach ([$start, $end] as $date) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (! $parsed || $parsed->format('Y-m-d') !== $date) {
                throw new DomainException('Indique fechas válidas en formato año-mes-día.');
            }
        }
        $method = (string) ($data['settlement_method'] ?? 'legal');
        if (! array_key_exists($method, self::METHODS)) {
            throw new DomainException('Método de liquidación no válido.');
        }
        $template = $method === 'template';
        $salary = (int) ($data['basic_salary'] ?? 0);
        $extras = (int) ($data['average_extras'] ?? 0);
        $surcharges = (int) ($data['average_surcharges'] ?? 0);
        $transport = (int) ($data['transport_allowance'] ?? 0);
        $smlmv = (int) ($data['min_wage'] ?? 0);
        if ($salary <= 0 || $smlmv <= 0) {
            throw new DomainException('El salario y SMLMV deben ser mayores a cero.');
        }
        if (min($extras, $surcharges, $transport) < 0) {
            throw new DomainException('Los componentes salariales no pueden ser negativos.');
        }
        $healthRate = (float) ($data['health_rate'] ?? 0.04);
        $pensionRate = (float) ($data['pension_rate'] ?? 0.04);
        $interestRate = (float) ($data['interest_rate'] ?? 0.12);
        foreach ([$healthRate, $pensionRate, $interestRate] as $rate) {
            if (! is_finite($rate) || $rate < 0 || $rate > 1) {
                throw new DomainException('Las tarifas deben estar entre 0 y 1.');
            }
        }
        $service360 = self::days360($start, $end);
        $actual = self::daysActual($start, $end);
        $y = (int) substr($end, 0, 4);
        $month = (int) substr($end, 5, 2);
        $startYear = max($start, sprintf('%04d-01-01', $y));
        $startSem = max($start, sprintf('%04d-%02d-01', $y, $month <= 6 ? 1 : 7));
        $cesDays = (int) ($data['cesantias_days'] ?? min(360, self::days360($startYear, $end)));
        $primaDays = (int) ($data['prima_days'] ?? min(180, self::days360($startSem, $end)));
        // Blank day fields are derived from the dates; typed values (including 0) win.
        $auto = [];
        $vacTaken = (float) ($data['vacation_days_taken'] ?? 0);
        if ($vacTaken < 0) {
            throw new DomainException('Los días de vacaciones disfrutados no pueden ser negativos.');
        }
        $vacAccrued = round($service360 * 15 / 360, 2);
        if (self::blank($data, 'vacation_days_pending')) {
            $auto[] = 'vacation_days_pending';
            // Template divides by 720, so its day field counts service days, not rest days.
            $vacDays = $template ? max(0, $service360 - (int) round($vacTaken * 24)) : max(0, round($vacAccrued - $vacTaken, 2));
        } else {
            $vacDays = (float) $data['vacation_days_pending'];
        }
        if (self::blank($data, 'pending_salary_days')) {
            $auto[] = 'pending_salary_days';
            $salaryDays = min(30, self::days360(max($start, substr($end, 0, 8).'01'), $end));
        } else {
            $salaryDays = (int) $data['pending_salary_days'];
        }
        if (self::blank($data, 'pending_transport_days')) {
            $auto[] = 'pending_transport_days';
            $transportDays = $salaryDays;
        } else {
            $transportDays = (int) $data['pending_transport_days'];
        }
        foreach (['cesantías' => $cesDays, 'prima' => $primaDays, 'salario pendiente' => $salaryDays, 'transporte pendiente' => $transportDays] as $name => $value) {
            if ($value < 0) {
                throw new DomainException("Días inválidos para {$name}.");
            }
        }
        if ($cesDays > 360 || $primaDays > 180 || $salaryDays > 30 || $transportDays > 30 || $vacDays < 0) {
            throw new DomainException('Los días exceden los límites permitidos.');
        }
        $warnings = [];
        $salaryBase = $salary + $extras + $surcharges;
        if ($salaryBase > 2 * $smlmv && $transport > 0) {
            $warnings[] = 'Auxilio de transporte superior al umbral de 2 SMMLV: se excluye automáticamente del cálculo.';
            $transport = 0;
        }
        $base = $salaryBase + $transport;
        $vacBase = $template ? $salaryBase : $salary + $surcharges;
        $severance = self::money($base * $cesDays / 360);
        $severanceInterest = self::money($severance * $cesDays * $interestRate / 360);
        $premium = self::money($base * $primaDays / 360);
        $vacation = $template ? self::money($vacBase * $vacDays / 720) : self::money(($vacBase / 30) * $vacDays);
        $indBase = $template ? $salary : $salaryBase;
        $salaryDue = self::money(($salary / 30) * $salaryDays);
        $transportDue = self::money(($transport / 30) * $transportDays);
        $indemnification = 0;
        $indDays = 0.;
        $contractType = (string) ($data['contract_type'] ?? 'indefinido');
        if (! empty($data['wrongful_dismissal'])) {
            if (array_key_exists('manual_indemnification', $data) && $data['manual_indemnification'] !== null && $data['manual_indemnification'] !== '') {
                $indemnification = (int) $data['manual_indemnification'];
                $indDays = $indemnification * 30 / $indBase;
                if ($indemnification <= 0) {
                    throw new DomainException('La indemnización manual debe ser mayor a cero cuando aplica.');
                }
                if ($contractType === 'obra' && $indemnification < self::money($indBase / 30 * 15)) {
                    throw new DomainException('La indemnización por obra o labor no puede ser inferior a 15 días de salario.');
                }
                $warnings[] = 'Indemnización ingresada manualmente: verificar soporte jurídico.';
            } elseif ($contractType === 'fijo') {
                $agreed = $data['agreed_end_date'] ?? null;
                if (! $agreed) {
                    throw new DomainException('Indique la fecha de finalización pactada en contrato fijo para indemnizar.');
                }
                $last = new DateTimeImmutable($agreed);
                $retired = new DateTimeImmutable($end);
                $remaining = $last > $retired ? (int) $retired->diff($last)->days : 0;
                $indDays = (float) $remaining;
                $indemnification = self::money($indBase / 30 * $remaining);
            } elseif ($contractType === 'indefinido') {
                // Legal: strictly below 10 SMMLV. Template: salary <= 10 SMMLV, basic salary only.
                $lowTier = $template ? $indBase <= 10 * $smlmv : $indBase < 10 * $smlmv;
                $first = $lowTier ? 30 : 20;
                $additional = $lowTier ? 20 : 15;
                if ($template) {
                    // Excel: first-year days + (full years - 1) * additional + fractional year * additional.
                    $years = intdiv($actual, 365);
                    $indDays = $first + ($years >= 1 ? ($years - 1) * $additional : 0) + ($actual % 365) / 365 * $additional;
                } else {
                    $indDays = $actual <= 365 ? $first : $first + ($actual - 365) / 365 * $additional;
                }
                $indemnification = self::money($indBase / 30 * $indDays);
            } else {
                throw new DomainException('Este tipo de contrato requiere una indemnización manual sustentada; obra o labor tiene un mínimo de 15 días de salario.');
            }
        }
        $contribBase = $template ? $salaryDue + $vacation : $salaryDue;
        $health = self::money($contribBase * $healthRate);
        $pension = self::money($contribBase * $pensionRate);
        $advances = (int) ($data['advances'] ?? 0);
        $other = (int) ($data['other_deductions'] ?? 0);
        if ($advances < 0 || $other < 0 || $indemnification < 0) {
            throw new DomainException('No se permiten valores negativos en deducciones o indemnización.');
        }
        $devengado = $severance + $severanceInterest + $premium + $vacation + $salaryDue + $transportDue + $indemnification;
        $deductions = $health + $pension + $advances + $other;
        $net = $devengado - $deductions;
        if ($net < 0) {
            $warnings[] = 'Las deducciones superan el devengado: revisar autorizaciones y límites legales antes del pago.';
        }

        return [
            'version' => self::VERSION, 'effective_end_date' => $end, 'service_days_360' => $service360, 'service_days_actual' => $actual,
            'cesantias_days' => $cesDays, 'prima_days' => $primaDays, 'vacation_days_pending' => $vacDays,
            'vacation_days_accrued' => $vacAccrued, 'vacation_days_taken' => $vacTaken,
            'pending_salary_days' => $salaryDays, 'pending_transport_days' => $transportDays, 'auto_fields' => $auto,
            'prestational_base' => $base, 'vacation_base' => $vacBase, 'indemnification_base' => $indBase, 'settlement_method' => $method, 'min_wage' => $smlmv,
            'health_rate' => $healthRate, 'pension_rate' => $pensionRate, 'interest_rate' => $interestRate,
            'severance' => $severance, 'severance_interest' => $severanceInterest, 'premium' => $premium,
            'vacations' => $vacation, 'salary_due' => $salaryDue, 'transport_due' => $transportDue,
            'indemnification_days' => round($indDays, 3), 'indemnification' => $indemnification,
            'health' => $health, 'pension' => $pension, 'advances' => $advances, 'other_deductions' => $other,
            'gross' => $devengado, 'total_deductions' => $deductions, 'net' => $net, 'warnings' => $warnings,
            'formulas' => [
                'severance' => 'Base prestacional × días cesantías / 360',
                'severance_interest' => 'Cesantías × días cesantías × '.($interestRate * 100).'% / 360',
                'premium' => 'Base prestacional × días prima / 360',
                'vacations' => $template ? 'Base vacaciones × días / 720 (fórmula de la plantilla)' : 'Base vacaciones / 30 × días de descanso pendientes (auto: 15 días por 360 de servicio − disfrutados)',
                'salary_due' => 'Salario básico / 30 × días salario pendientes',
                'transport_due' => 'Auxilio transporte / 30 × días transporte pendientes',
                'health' => $template ? '(Salario pendiente + vacaciones) × '.($healthRate * 100).'% (plantilla)' : 'Salario pendiente × '.($healthRate * 100).'% (no incluye vacaciones compensadas)',
                'pension' => $template ? '(Salario pendiente + vacaciones) × '.($pensionRate * 100).'% (plantilla)' : 'Salario pendiente × '.($pensionRate * 100).'% (no incluye vacaciones compensadas)',
            ],
        ];
    }
}
