<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class rrhhAusentismo extends Model {
    use HasFactory;
    protected $table = 'rrhh_ausentismos';

    protected $fillable = ['legajo_id', 'causa_id',  'detalle_id', 'inicio', 'fecha_probable', 'fecha_real', 'observaciones', 'activo', 'fecha_inicio'];

    public function causa(): HasOne {
        return $this->hasOne(rrhhCausaAusentismo::class, 'id', 'causa_id');
    }

    public function detalle(): HasOne {
        return $this->hasOne(rrhhDetalleAusentismo::class, 'id', 'detalle_id');
    }

    // public function area(): HasOne {
    //     return $this->hasOne(rrhhAreas::class, 'id', 'area_id');
    // }

    public function legajo(): HasOne {
        return $this->hasOne(RrhhLegajos::class, 'id', 'legajo_id');
    }
}
