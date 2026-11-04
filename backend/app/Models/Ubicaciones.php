<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ubicaciones extends Model {
    use HasFactory;

    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = ['deposito_id', 'orden', 'capacidad', 'nombre', 'habilitada'];

    public function ocupacion(): HasOne {
        return $this->hasOne(UbicacionesMovimientos::class, 'ubicacion_id', 'id')->where('egreso', null);
    }

    public function deposito(): HasOne {
        return $this->hasOne(Depositos::class, 'id', 'deposito_id');
    }

    public function unidades(): HasMany {
        // return $this->belongsTo(UnidadesUbicaciones::class, 'id', 'ubicacion_id');
        return $this->hasMany(UnidadesUbicaciones::class, 'ubicacion_id', 'id');
    }

    public function contenido(): HasMany {
        return $this->hasMany(Movimientos::class, 'ubicacion_id', 'id')->where('finalizado', false);
    }

    public function contenido2(): HasMany {
        return $this->hasMany(MovimientosContenido::class, 'ubicacion_id', 'id');
    }
}
