<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DadosPieza extends Model {
    use HasFactory;

    protected $fillable = ['otro', 't_lectra1', 't_lectra2', 't_lectra3', 't_lectra4', 'fecha_actualizacion2', 'fecha_actualizacion', 't_posicionamiento', 'modelo_id', 'corte', 'consumo', 'dado', 'material_id', 'pieza_id'];

    public function material(): HasOne {
        return $this->hasOne(MaterialesPiezas::class, 'id', 'material_id');
    }

    public function modelo(): HasOne {
        return $this->hasOne(Modelos::class, 'id', 'modelo_id');
    }

    public function modeloDado(): HasOne {
        return $this->hasOne(ModeloDado::class, 'dado_id', 'id');
    }

    public function pieza(): HasOne {
        return $this->hasOne(Piezas::class, 'id', 'pieza_id');
    }

    public function kanbanReemplazo(): HasOne {
        return $this->hasOne(KanbansReemplazo::class, 'pieza_id', 'pieza_id');
    }
}
