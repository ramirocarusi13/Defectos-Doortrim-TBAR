<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scrap extends Model {
    use HasFactory;

    protected $fillable = ['user_id', 'defecto_id', 'falla_id', 'material_id', 'kg', 'precio', 'motivo', 'linea_id', 'tipo_linea', 'sector', 'turno'];

    public function material(): BelongsTo {
        return $this->belongsTo(MaterialesPiezas::class, 'material_id', 'id');
    }
}
