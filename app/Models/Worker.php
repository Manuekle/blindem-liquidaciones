<?php

namespace App\Models;

use App\Models\Concerns\HasPublicRouteKey;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    use HasPublicRouteKey;

    protected $table = 'workers';

    protected $guarded = [];

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }
}
