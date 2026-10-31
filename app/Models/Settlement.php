<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['inputs' => 'array', 'result' => 'array'];
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function calculatedBy()
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }
}
