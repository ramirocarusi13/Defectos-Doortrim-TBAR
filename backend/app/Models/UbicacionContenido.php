<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class UbicacionContenido extends Model {
    use HasFactory;

    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = ['movimiento_id', 'detalle', 'contenido', 'lote'];

    public function kanban(): HasOne {
        return $this->hasOne(Kanbans::class, 'codigo', 'contenido');
    }

    public function disponible(): HasOne {
        return $this->hasOne(UbicacionesMovimientos::class, 'id', 'movimiento_id')->where('egreso', null);
    }

    public function reservado(): HasOne {
        return $this->hasOne(DespachosItems::class, 'kanban', 'contenido')->where('pickeado', false);
    }

    public function cont(): HasOne {
        return $this->hasOne(Kanbans::class, 'codigo', 'contenido')->with('modelo');
    }

    public function contenido(): HasOne {
        // switch ($this->detalle) {
        //     case 'KANBAN':
        //         return $this->hasOne(Kanbans::class, 'codigo', 'contenido');
        //         break;

        //     default:
        //         return $this->
        //         break;
        // }
        return $this->hasOne(Kanbans::class, 'codigo', 'contenido')->with('modelo');
    }
}
