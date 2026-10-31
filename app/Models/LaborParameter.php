<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;

class LaborParameter extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['year' => 'integer', 'min_wage' => 'integer', 'transport_allowance' => 'integer',
            'health_rate' => 'float', 'pension_rate' => 'float', 'interest_rate' => 'float'];
    }

    public static function forDate(string $date): self
    {
        $year = (int) substr($date, 0, 4);

        return static::where('year', $year)->first()
            ?? throw new DomainException("No hay parámetros laborales para {$year}. Solicita al administrador registrar esa vigencia.");
    }
}
