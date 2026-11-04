<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ModeloFalla extends Model {
    use HasFactory;

    protected $fillable = ['modelo_id', 'imagen', 'tipo_id', 'lado_id', 'nombre', 'orientacion'];

    // public function falla(): HasOne {
    //     return $this->hasOne(CodigoFalla::class, 'id', 'falla_id');
    // }

    public function tipo(): HasOne {
        return $this->hasOne(TipoPartes::class, 'id', 'tipo_id');
    }

    public function lado(): HasOne {
        return $this->hasOne(LadoPartes::class, 'id', 'lado_id');
    }
}
