<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UnidadesUbicaciones extends Model {
    use HasFactory;

    protected $table = 'wms_unidades_ubicaciones';

    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = ['ubicacion_id', 'unidad_id', 'capacidad'];

    public function unidad(): HasOne {
        return $this->hasOne(Unidades::class, 'id', 'unidad_id');
    }
}
