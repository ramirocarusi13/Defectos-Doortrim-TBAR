<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UbicacionesMovimientos extends Model {
    use HasFactory;

    protected $fillable = ['ubicacion_id', 'ingreso', 'egreso', 'contenido'];
    protected $hidden = ['created_at', 'updated_at'];

    public function contenido(): HasMany {
        return $this->hasMany(UbicacionContenido::class, 'movimiento_id', 'id');
    }

    public function cont(): HasMany {
        return $this->hasMany(UbicacionContenido::class, 'movimiento_id', 'id');
    }

    public function ubicacion(): HasOne {
        return $this->hasOne(Ubicaciones::class, 'id', 'ubicacion_id');
    }

    // public function deposito(): HasOneThrough {
    //     return $this->hasOneThrough(Ubicaciones::class, Depositos::class, 'id', 'deposito_id', 'ubicacion_id', 'id');
    // }
}
