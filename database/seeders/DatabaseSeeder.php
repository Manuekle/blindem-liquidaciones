<?php
namespace Database\Seeders;
use App\Models\{User,Company,Worker,DocumentType,JobTitle,ContractType,TerminationReason,Contract};
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder {
 public function run():void {
  User::firstOrCreate(['email'=>'admin@blindem.test'],['name'=>'Administrador Blindem','password'=>'Cambiar123!','role'=>'admin']);
  User::firstOrCreate(['email'=>'colaborador@blindem.test'],['name'=>'Colaborador Blindem','password'=>'Cambiar123!','role'=>'collaborator']);
  foreach(['CC'=>'Cédula de ciudadanía','CE'=>'Cédula de extranjería','TI'=>'Tarjeta de identidad','PAS'=>'Pasaporte','PPT'=>'Permiso por Protección Temporal'] as $code=>$name) DocumentType::firstOrCreate(['code'=>$code],['name'=>$name]);
  foreach(['Analista operativo','Auxiliar administrativo','Contador','Ingeniero de sistemas','Asesor comercial','Coordinador'] as $name) JobTitle::firstOrCreate(['name'=>$name]);
  foreach(['indefinido'=>'Término indefinido','fijo'=>'Término fijo','obra'=>'Obra o labor'] as $code=>$name) ContractType::firstOrCreate(['code'=>$code],['name'=>$name]);
  foreach(['mutuo_acuerdo'=>['Mutuo acuerdo',false],'renuncia'=>['Renuncia voluntaria',false],'sin_justa_causa'=>['Despido sin justa causa',true],'justa_causa'=>['Despido con justa causa',false],'vencimiento'=>['Vencimiento del término',false]] as $code=>[$name,$flag]) TerminationReason::firstOrCreate(['code'=>$code],['name'=>$name,'implies_indemnification'=>$flag]);
  $company=Company::firstOrCreate(['nit'=>'900123456-7'],['name'=>'Blindem Demo S.A.S.','email'=>'demo@blindem.test','address'=>'Popayán, Colombia']);
  $worker=Worker::firstOrCreate(['document_type_id'=>DocumentType::where('code','CC')->value('id'),'document_number'=>'1098765432'],['full_name'=>'Juan Carlos Pérez','email'=>'juan.perez@example.test']);
  Contract::firstOrCreate(['worker_id'=>$worker->id,'company_id'=>$company->id,'start_date'=>'2023-01-15'],['job_title_id'=>JobTitle::first()->id,'contract_type_id'=>ContractType::where('code','indefinido')->value('id'),'termination_reason_id'=>TerminationReason::where('code','sin_justa_causa')->value('id'),'end_date'=>'2026-06-30','basic_salary'=>1750905,'transport_allowance'=>249095,'average_extras'=>150000,'vacation_days_pending'=>15,'pending_salary_days'=>15,'pending_transport_days'=>15,'cesantias_days'=>180,'prima_days'=>180,'wrongful_dismissal'=>true,'status'=>'terminated','created_by'=>User::first()->id]);
 }
}
