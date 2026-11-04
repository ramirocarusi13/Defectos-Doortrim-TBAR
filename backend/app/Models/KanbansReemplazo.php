<?php

namespace App\Models;

use App\Http\Estados;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class KanbansReemplazo extends Model {
    use HasFactory;

    protected $fillable = ['kanban_id', 'capas', 'pieza_id', 'fecha_impresion', 'impresora', 'fecha_ingreso', 'fecha_plan'];


    public function pieza(): HasOne {
        return $this->hasOne(Piezas::class, 'id', 'pieza_id');
    }

    public function estado(): HasOne {
        return $this->hasOne(EstadoKanban::class, 'kanban_id', 'kanban_id');
    }

    public function kanban(): HasOne {
        return $this->hasOne(Kanbans::class, 'id', 'kanban_id');
    }

    public function abierto(): HasOne {
        // Log::alert($this->kanban_id);
        return $this->hasOne(EstadoKanban::class, 'kanban_id', 'kanban_id')->where('estado_id', '<>', Estados::FINALIZADO);
    }
}
