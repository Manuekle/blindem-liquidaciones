<?php
namespace App\Http\Controllers;
use App\Models\{Contract,Worker,Company,JobTitle,ContractType,TerminationReason,DocumentType};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ContractController {
 public function index(Request $request){
  $contracts=Contract::with(['worker.documentType','company','contractType','jobTitle'])
   ->when($request->filled('status'),fn($q)=>$q->where('status',$request->status))
   ->when($request->filled('document_type'),fn($q)=>$q->whereHas('worker',fn($w)=>$w->where('document_type_id',$request->document_type)))
   ->when($request->filled('document'),fn($q)=>$q->whereHas('worker',fn($w)=>$w->where('document_number','ilike','%'.$request->document.'%')))
   ->when($request->filled('company'),fn($q)=>$q->where('company_id',$request->company))
   ->when($request->filled('q'),fn($q)=>$q->whereHas('worker',fn($w)=>$w->where('full_name','ilike','%'.$request->q.'%')))
   ->latest()->paginate(12)->withQueryString();
  return view('contracts.index',['contracts'=>$contracts,'documentTypes'=>DocumentType::all(),'companies'=>Company::orderBy('name')->get()]);
 }
 private function lists():array{return ['workers'=>Worker::orderBy('full_name')->get(),'companies'=>Company::orderBy('name')->get(),'jobs'=>JobTitle::orderBy('name')->get(),'types'=>ContractType::orderBy('name')->get(),'reasons'=>TerminationReason::orderBy('name')->get()];}
 public function create(){return view('contracts.form',['contract'=>new Contract(),...$this->lists()]);}
 public function edit(Contract $contract){return view('contracts.form',['contract'=>$contract,...$this->lists()]);}
 private function data(Request $r):array{
  $data=$r->validate([
   'worker_id'=>'required|exists:workers,id','company_id'=>'required|exists:companies,id','job_title_id'=>'required|exists:job_titles,id','contract_type_id'=>'required|exists:contract_types,id',
   'termination_reason_id'=>'nullable|exists:termination_reasons,id','start_date'=>'required|date','end_date'=>'nullable|date|after_or_equal:start_date','agreed_end_date'=>'nullable|date|after_or_equal:start_date',
   'basic_salary'=>'required|integer|min:1|max:99999999999','transport_allowance'=>'required|integer|min:0','average_extras'=>'required|integer|min:0',
   'cesantias_days'=>'nullable|integer|between:0,360','prima_days'=>'nullable|integer|between:0,180','vacation_days_pending'=>'required|numeric|min:0|max:9999',
   'pending_salary_days'=>'required|integer|between:0,30','pending_transport_days'=>'required|integer|between:0,30','advances'=>'required|integer|min:0','other_deductions'=>'required|integer|min:0',
   'manual_indemnification'=>'nullable|integer|min:0','status'=>['required',Rule::in(['active','terminated'])],'notes'=>'nullable|string|max:1000',
  ]);
  $data['wrongful_dismissal']=$r->boolean('wrongful_dismissal');
  if($data['status']==='terminated'&&!$data['end_date'])throw \Illuminate\Validation\ValidationException::withMessages(['end_date'=>'La fecha de retiro es obligatoria para contratos retirados.']);
  $type=ContractType::find($data['contract_type_id']);
  if($data['wrongful_dismissal']&&$type?->code==='fijo'&&!$data['agreed_end_date']&&$data['manual_indemnification']===null)throw \Illuminate\Validation\ValidationException::withMessages(['agreed_end_date'=>'Indique fecha pactada en contrato fijo o indemnización manual.']);
  return $data;
 }
 public function store(Request $request){$contract=Contract::create([...$this->data($request),'created_by'=>$request->user()->id]);return redirect()->route('contracts.show',$contract)->with('success','Contrato registrado.');}
 public function update(Request $request,Contract $contract){$contract->update($this->data($request));return redirect()->route('contracts.show',$contract)->with('success','Contrato actualizado. Los cálculos históricos guardados se conservan.');}
 public function show(Contract $contract){$contract->load(['worker.documentType','company','jobTitle','contractType','terminationReason','settlements'=>fn($q)=>$q->latest()->limit(5)]);return view('contracts.show',compact('contract'));}
 public function destroy(Contract $contract){$contract->delete();return redirect()->route('contracts.index')->with('success','Contrato y sus liquidaciones eliminados.');}
}
