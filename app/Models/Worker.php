<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Worker extends Model {protected $table='workers';protected $guarded=[];
 public function documentType(){return $this->belongsTo(DocumentType::class);}public function contracts(){return $this->hasMany(Contract::class);}
}
