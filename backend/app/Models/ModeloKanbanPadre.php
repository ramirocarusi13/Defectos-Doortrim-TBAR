<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ModeloKanbanPadre extends Model {
    use HasFactory;

    protected $fillable = ['otro', 't_lectra1', 't_lectra2', 't_lectra3', 't_lectra4', 'fecha_actualizacion2', 'fecha_actualizacion', 't_posicionamiento', 'modelo_id', 'corte', 'consumo', 'dado', 'material_id', 'compartido_id'];

    public function material(): HasOne {
        return $this->hasOne(MaterialesPiezas::class, 'id', 'material_id');
    }

    public function modelo(): HasOne {
        return $this->hasOne(Modelos::class, 'id', 'modelo_id');
    }

    public function modeloDado(): HasOne {
        return $this->hasOne(ModeloDado::class, 'dado_id', 'id');
    }

    public function compartido(): HasOne {
        return $this->hasOne(ModelosCompartidos::class, 'id', 'compartido_id');
    }

    public function corteCurso(): HasOne {
        return $this->hasOne(LectraEstado::class, 'dado_id', 'id')
            ->where('inicio', '<>', null)->where('fin', null);
    }
}
