<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HoraHoraProduccion extends Model {
    use HasFactory;

    protected $fillable = [
        'fecha',
        'turno',
        'turno_nombre',
        'linea',
        'intervalo',
        'plan',
        'plan_acumulado',
        'modelo',
        'real',
        'acumulado',
        'diferencia',
        'diferencia_acumulado',
        'piezas_reparadas',
        'piezas_scrap',
        'real_tiempo',
        'minutos_atraso',
        'tipo'
    ];

    // public function paradas(): HasMany {
    //     return $this->hasMany(ProduccionParada::class,'turno','turno')
    //     ->where()
    // }
}
