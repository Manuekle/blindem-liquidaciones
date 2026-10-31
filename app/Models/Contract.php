<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'agreed_end_date' => 'date', 'wrongful_dismissal' => 'boolean', 'salary_components_reviewed' => 'boolean', 'vacation_days_pending' => 'decimal:2', 'vacation_days_taken' => 'decimal:2'];
    }

    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function contractType()
    {
        return $this->belongsTo(ContractType::class);
    }

    public function terminationReason()
    {
        return $this->belongsTo(TerminationReason::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }

    public function getDisplayStatusAttribute(): string
    {
        return $this->status === 'terminated' ? 'Retirado' : 'Activo';
    }
}
