<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanLinea extends Model {
    use HasFactory;

    protected $fillable = [
        'linea_id',
        'turno',
        'orden',
        'hora_desde',
        'hora_hasta',
        'plan',
        'plan_acumulado',
    ];
}
