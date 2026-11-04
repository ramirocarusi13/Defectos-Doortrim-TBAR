<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodigoFalla extends Model {
    use HasFactory;

    protected $fillable = ['codigo', 'nombre'];
    protected $hidden = ['created_at', 'updated_at'];

    public function enTienda(): HasMany {
        return $this->hasMany(TiendaPedido::class, 'falla_id', 'id');
    }

    public function defectosCobot(): HasMany {
        return $this->hasMany(DefectoCobot::class, 'falla_id', 'id');
    }
}
