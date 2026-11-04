<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogPlanCostura extends Model {
    use HasFactory;

    protected $fillable = ['fecha', 'orden', 'modelo', 'cortes_requeridos', 'cant_buffer', 'cant_assy', 'cant_corte', 'cortes_ejecutados'];
}
