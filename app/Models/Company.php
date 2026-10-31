<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'companies';

    protected $guarded = [];

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }
}
