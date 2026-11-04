<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Depositos extends Model {
    use HasFactory;

    protected $fillable = ['descripcion', 'posiciones_piso', 'visible'];

    protected $hidden = ['created_at', 'updated_at'];

    public function ubicaciones(): HasMany {
        return $this->hasMany(Ubicaciones::class, 'deposito_id', 'id')->where('habilitada', true)->orderBy('orden');
    }

    public function ubicacionesTodas(): HasMany {
        return $this->hasMany(Ubicaciones::class, 'deposito_id', 'id')->orderBy('orden');
    }

    public function ubicacionesOcupadas(): HasManyThrough {
        return $this->hasManyThrough(UbicacionesMovimientos::class, Ubicaciones::class, 'deposito_id', 'ubicacion_id', 'id', 'id')->where('egreso', null)->groupBy('ubicaciones_movimientos.ubicacion_id');
    }

    public function layout(): HasOne {
        return $this->hasOne(DepositoLayout::class, 'deposito_id', 'id');
    }
}
