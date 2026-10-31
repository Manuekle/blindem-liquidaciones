<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerminationReason extends Model
{
    protected $table = 'termination_reasons';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['implies_indemnification' => 'boolean'];
    }
}
