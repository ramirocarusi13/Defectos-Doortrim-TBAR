<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PiezasMateriales extends Model {
    use HasFactory;

    protected $fillable = ['pieza_id', 'material_pieza_id', 'secuencia', 'dado'];

    public function material(): HasOne {
        return $this->hasOne(MaterialesPiezas::class, 'id', 'material_pieza_id')->orderBy('codigo_interno', 'ASC');
    }

    public function pieza(): HasOne {
        return $this->hasOne(Piezas::class, 'id', 'pieza_id');
    }
}
