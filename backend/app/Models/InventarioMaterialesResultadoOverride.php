<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventarioMaterialesResultadoOverride extends Model
{
    use HasFactory;

    protected $table = 'inventario_materiales_resultado_overrides';

    protected $fillable = [
        'material_id',
        'fecha',
        'subtotal_kg',
        'densidad',
        'total_m2',
        'ancho',
        'total_ml',
        'updated_by_user_id',
    ];

    public function material(): HasOne
    {
        return $this->hasOne(MaterialesPiezas::class, 'id', 'material_id');
    }

    public function updatedBy(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'updated_by_user_id');
    }
}
