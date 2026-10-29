<?php
namespace App\Http\Controllers;
use App\Models\{DocumentType,JobTitle,ContractType,TerminationReason};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class CatalogController {
 private const TYPES=['documentos'=>[DocumentType::class,'Tipos de documento'],'cargos'=>[JobTitle::class,'Cargos'],'contratos'=>[ContractType::class,'Tipos de contrato'],'retiros'=>[TerminationReason::class,'Motivos de retiro']];
 private function spec(string $type):array {abort_unless(isset(self::TYPES[$type]),404);return self::TYPES[$type];}
 public function index(string $type){[$model,$title]=$this->spec($type);$records=$model::orderBy('name')->paginate(15);return view('catalogs.index',compact('type','title','records'));}
 private function data(Request $r,string $type,?int $id=null):array {
  [$model]=$this->spec($type);$table=(new $model)->getTable();
  $rules=['name'=>['required','string','max:160',Rule::unique($table,'name')->ignore($id)]];
  if($type!=='cargos')$rules['code']=['required','string','max:50',Rule::unique($table,'code')->ignore($id)];
  if($type==='retiros')$rules['implies_indemnification']='sometimes|boolean';
  $data=$r->validate($rules);if($type==='retiros')$data['implies_indemnification']=$r->boolean('implies_indemnification');return $data;
 }
 public function store(Request $r,string $type){[$model]=$this->spec($type);$model::create($this->data($r,$type));return back()->with('success','Registro agregado.');}
 public function update(Request $r,string $type,int $id){[$model]=$this->spec($type);$model::findOrFail($id)->update($this->data($r,$type,$id));return back()->with('success','Registro actualizado.');}
 public function destroy(string $type,int $id){[$model]=$this->spec($type);try{$model::findOrFail($id)->delete();}catch(\Illuminate\Database\QueryException $e){return back()->with('error','No puede eliminarse un parámetro utilizado por contratos o trabajadores.');}return back()->with('success','Registro eliminado.');}
}
