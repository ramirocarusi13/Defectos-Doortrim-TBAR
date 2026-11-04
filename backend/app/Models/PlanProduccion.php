<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanProduccion extends Model {
    use HasFactory;

    protected $fillable = ['linea', 'modelo', 'cantidad', 'fecha', 'modelo_id', 'orden', 'hora_esperada'];
}
