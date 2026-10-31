<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\LaborParameter;
use DomainException;

class SettlementInputs
{
    public function fromContract(Contract $contract): array
    {
        $contract->loadMissing('worker.documentType', 'company', 'jobTitle', 'contractType', 'terminationReason');
        if (! $contract->salary_components_reviewed) {
            throw new DomainException('Revisa y confirma la separación de horas extras y recargos en el contrato antes de calcular.');
        }
        if ($contract->status === 'terminated' && ! $contract->terminationReason) {
            throw new DomainException('Registra el motivo de retiro antes de calcular.');
        }
        $end = $contract->end_date?->toDateString() ?? now()->toDateString();
        $parameters = LaborParameter::forDate($end);

        return [
            'start_date' => $contract->start_date->toDateString(), 'end_date' => $end,
            'agreed_end_date' => $contract->agreed_end_date?->toDateString(),
            'basic_salary' => $contract->basic_salary, 'average_extras' => $contract->average_extras,
            'average_surcharges' => $contract->average_surcharges, 'transport_allowance' => $contract->transport_allowance,
            'parameter_year' => $parameters->year, 'min_wage' => $parameters->min_wage,
            'reference_transport_allowance' => $parameters->transport_allowance,
            'health_rate' => $parameters->health_rate, 'pension_rate' => $parameters->pension_rate,
            'interest_rate' => $parameters->interest_rate,
            'wage_source' => $parameters->wage_source, 'transport_source' => $parameters->transport_source,
            'cesantias_days' => $contract->cesantias_days, 'prima_days' => $contract->prima_days,
            'vacation_days_pending' => $contract->vacation_days_pending, 'vacation_days_taken' => $contract->vacation_days_taken,
            'pending_salary_days' => $contract->pending_salary_days, 'pending_transport_days' => $contract->pending_transport_days,
            'advances' => $contract->advances, 'other_deductions' => $contract->other_deductions,
            'wrongful_dismissal' => $contract->status === 'terminated' && (bool) $contract->terminationReason?->implies_indemnification,
            'manual_indemnification' => $contract->manual_indemnification,
            'contract_type' => $contract->contractType->calculation_method,
            'context' => [
                'worker_name' => $contract->worker->full_name, 'document_type' => $contract->worker->documentType->code,
                'document_number' => $contract->worker->document_number, 'company_name' => $contract->company->name,
                'company_nit' => $contract->company->nit, 'job_title' => $contract->jobTitle->name,
                'contract_type_name' => $contract->contractType->name,
                'termination_reason' => $contract->terminationReason?->name ?? 'No aplica',
            ],
        ];
    }
}
