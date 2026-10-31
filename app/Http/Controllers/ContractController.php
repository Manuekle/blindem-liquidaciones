<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\JobTitle;
use App\Models\LaborParameter;
use App\Models\TerminationReason;
use App\Models\Worker;
use App\Services\LaborSettlementCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContractController
{
    public function index(Request $request)
    {
        $contracts = Contract::with(['worker.documentType', 'company', 'contractType', 'jobTitle'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('document_type'), fn ($q) => $q->whereHas('worker', fn ($w) => $w->where('document_type_id', $request->document_type)))
            ->when($request->filled('document'), fn ($q) => $q->whereHas('worker', fn ($w) => $w->whereLike('document_number', '%'.$request->document.'%')))
            ->when($request->filled('company'), fn ($q) => $q->where('company_id', $request->company))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('worker', fn ($w) => $w->whereLike('full_name', '%'.$request->q.'%')))
            ->latest()->paginate(12)->withQueryString();

        return view('contracts.index', ['contracts' => $contracts, 'documentTypes' => DocumentType::all(), 'companies' => Company::orderBy('name')->get()]);
    }

    private function lists(): array
    {
        return ['workers' => Worker::orderBy('full_name')->get(), 'companies' => Company::orderBy('name')->get(), 'jobs' => JobTitle::orderBy('name')->get(), 'types' => ContractType::orderBy('name')->get(), 'reasons' => TerminationReason::orderBy('name')->get(), 'laborParameters' => LaborParameter::orderBy('year')->get()->keyBy('year')];
    }

    public function create(Request $request)
    {
        return view('contracts.form', ['contract' => new Contract(['worker_id' => $request->integer('worker') ?: null]), ...$this->lists()]);
    }

    public function edit(Contract $contract)
    {
        return view('contracts.form', ['contract' => $contract, ...$this->lists()]);
    }

    private function data(Request $r, ?Contract $contract = null): array
    {
        $data = $r->validate([
            'worker_id' => 'required|exists:workers,id', 'company_id' => 'required|exists:companies,id', 'job_title_id' => 'required|exists:job_titles,id', 'contract_type_id' => 'required|exists:contract_types,id',
            'termination_reason_id' => ['nullable', Rule::requiredIf($r->input('status') === 'terminated'), 'exists:termination_reasons,id'], 'start_date' => 'required|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date', 'agreed_end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'basic_salary' => 'required|integer|min:1|max:99999999999', 'transport_allowance' => 'required|integer|min:0|max:99999999999', 'average_extras' => 'required|integer|min:0|max:99999999999', 'average_surcharges' => 'required|integer|min:0|max:99999999999',
            'cesantias_days' => 'nullable|integer|between:0,360', 'prima_days' => 'nullable|integer|between:0,180', 'vacation_days_pending' => 'nullable|numeric|min:0|max:9999', 'vacation_days_taken' => 'nullable|numeric|min:0|max:9999',
            'pending_salary_days' => 'nullable|integer|between:0,30', 'pending_transport_days' => 'nullable|integer|between:0,30', 'advances' => 'required|integer|min:0', 'other_deductions' => 'required|integer|min:0',
            'manual_indemnification' => 'nullable|integer|min:0', 'status' => ['required', Rule::in(['active', 'terminated'])], 'notes' => 'nullable|string|max:1000',
        ]);
        $data = array_replace(['end_date' => null, 'agreed_end_date' => null, 'termination_reason_id' => null, 'manual_indemnification' => null, 'cesantias_days' => null, 'prima_days' => null, 'vacation_days_pending' => null, 'pending_salary_days' => null, 'pending_transport_days' => null], $data);
        $data['vacation_days_taken'] ??= 0;
        if ($contract && ! $contract->salary_components_reviewed && ! $r->boolean('salary_components_reviewed')) {
            throw ValidationException::withMessages(['salary_components_reviewed' => 'Confirma la revisión de los importes de horas extras y recargos.']);
        }
        $data['salary_components_reviewed'] = true;
        $reason = TerminationReason::find($data['termination_reason_id']);
        $data['wrongful_dismissal'] = $data['status'] === 'terminated' && (bool) $reason?->implies_indemnification;
        if ($data['status'] === 'terminated' && ! $data['end_date']) {
            throw ValidationException::withMessages(['end_date' => 'La fecha de retiro es obligatoria para contratos retirados.']);
        }
        $type = ContractType::find($data['contract_type_id']);
        if ($data['status'] === 'active' && ($data['end_date'] || $data['termination_reason_id'])) {
            throw ValidationException::withMessages(['status' => 'Un contrato activo no debe tener fecha ni motivo de retiro.']);
        }
        if ($type?->calculation_method === 'fijo' && ! $data['agreed_end_date']) {
            throw ValidationException::withMessages(['agreed_end_date' => 'Indique la fecha de finalización pactada del contrato fijo.']);
        }
        if (! $data['wrongful_dismissal'] && $data['manual_indemnification'] !== null) {
            throw ValidationException::withMessages(['manual_indemnification' => 'El motivo de retiro seleccionado no da lugar a indemnización.']);
        }
        if ($data['status'] === 'terminated') {
            try {
                $parameters = LaborParameter::forDate($data['end_date']);
                (new LaborSettlementCalculator)->calculate([...$data, 'min_wage' => $parameters->min_wage, 'contract_type' => $type->calculation_method]);
            } catch (\DomainException $e) {
                throw ValidationException::withMessages(['liquidacion' => $e->getMessage()]);
            }
        }

        return $data;
    }

    public function store(Request $request)
    {
        $contract = Contract::create([...$this->data($request), 'created_by' => $request->user()->id]);

        return redirect()->route('contracts.show', $contract)->with('success', 'Contrato registrado.');
    }

    public function update(Request $request, Contract $contract)
    {
        $contract->update($this->data($request, $contract));

        return redirect()->route('contracts.show', $contract)->with('success', 'Contrato actualizado. Los cálculos históricos guardados se conservan.');
    }

    public function show(Contract $contract)
    {
        $contract->load(['worker.documentType', 'company', 'jobTitle', 'contractType', 'terminationReason', 'settlements' => fn ($q) => $q->latest()->limit(5)]);

        return view('contracts.show', compact('contract'));
    }

    public function destroy(Contract $contract)
    {
        if ($contract->settlements()->exists()) {
            return back()->with('error', 'El contrato tiene liquidaciones históricas y debe conservarse.');
        }$contract->delete();

        return redirect()->route('contracts.index')->with('success', 'Contrato eliminado.');
    }
}
