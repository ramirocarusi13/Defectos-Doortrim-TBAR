<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Movimientos extends Model {
    use HasFactory;

    protected $table = 'wms_movimientos';
    protected $fillable = ['unidad_id', 'ubicacion_id', 'finalizado', 'user_id', 'user_id2', 'motivo'];

    public function detalle(): HasMany {
        return $this->hasMany(MovimientosContenido::class, 'movimiento_id', 'id');
    }

    public function ubicacion(): HasOne {
        return $this->hasOne(Ubicaciones::class, 'id', 'ubicacion_id');
    }
    // public function disponibleM3() {
    //     return '';
    // }
}
