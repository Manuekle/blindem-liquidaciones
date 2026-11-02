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

    /** The contract list filters (status, company, document_type, document, q), shared with the assistant. */
    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['document_type'] ?? null, fn ($q, $type) => $q->whereHas('worker', fn ($w) => $w->where('document_type_id', $type)))
            ->when($filters['document'] ?? null, fn ($q, $document) => $q->whereHas('worker', fn ($w) => $w->whereLike('document_number', '%'.$document.'%')))
            ->when($filters['company'] ?? null, fn ($q, $company) => $q->where('company_id', $company))
            ->when($filters['q'] ?? null, fn ($q, $name) => $q->whereHas('worker', fn ($w) => $w->whereLike('full_name', '%'.$name.'%')));
    }

    public function getDisplayStatusAttribute(): string
    {
        return $this->status === 'terminated' ? 'Retirado' : 'Activo';
    }
}
