<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroIngresoRollo extends Model {
    use HasFactory;

    protected $fillable = ['proveedor_id', 'operacion', 'material_id', 'codigo_escaneado', 'user_id', 'codigo_sar', 'lote', 'peso_bruto', 'peso_liquido', 'mt_bruto', 'mt_liquido', 'otros'];

    public function material() {
        return $this->hasOne(MaterialesPiezas::class, 'id', 'material_id');
    }
}
