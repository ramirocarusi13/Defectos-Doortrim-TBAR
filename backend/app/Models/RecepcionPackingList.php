<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RecepcionPackingList extends Model {
    use HasFactory;

    protected $fillable = ['codigo', 'lote', 'cantidad', 'remito', 'proveedor_id', 'operacion', 'ingreso_id'];

    public function material(): HasOne {
        return $this->hasOne(MaterialesPiezas::class, 'codigo_proveedor', 'codigo');
    }
}
