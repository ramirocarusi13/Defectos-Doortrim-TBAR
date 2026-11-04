<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recepcion extends Model {
    use HasFactory;

    protected $fillable = ['proveedor_id', 'fecha', 'remito', 'packing_list', 'pendiente', 'en_darsena', 'sap'];

    public function proveedor(): HasOne {
        return $this->hasOne(Proveedores::class, 'id', 'proveedor_id');
    }

    public function packing(): HasMany {
        return $this->hasMany(RecepcionPackingList::class, 'operacion', 'packing_list');
    }

    public function packing_pendiente(): HasMany {
        return $this->hasMany(RecepcionPackingList::class, 'operacion', 'packing_list')->where('ingreso_id', null);
    }
}
