<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialesPiezas extends Model {
    use HasFactory;

    protected $fillable = ['orden', 'densidad', 'codigo_interno', 'pto_pedido', 'minimo', 'lote', 'maximo', 'modelo', 'tipo', 'codigo', 'color', 'nombre', 'codigo_proveedor', 'ancho', 'um', 'proveedor_id'];
    protected $hidden = ['created_at', 'updated_at'];

    public function inventario() {
        return $this->hasMany(InventarioMaterialesPiezas::class, 'material_id', 'id');
    }

    public function aprobacion_calidad() {
        return $this->hasOne(MaterialesAprobacionCalidad::class, 'material_id', 'id');
    }

    public function proveedor() {
        return $this->belongsTo(Proveedores::class);
    }

    public function stock() {
        return $this->hasMany(StockMateriales::class, 'ref', 'codigo');
    }
}
