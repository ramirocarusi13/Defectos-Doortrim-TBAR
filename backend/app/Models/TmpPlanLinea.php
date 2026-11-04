<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TmpPlanLinea extends Model {
    use HasFactory;

    protected $fillable = ['linea_id', 'cantidad', 'fecha', 'orden', 'diferencia', 'diferencia_acumulada', 'andon_hora'];
}
