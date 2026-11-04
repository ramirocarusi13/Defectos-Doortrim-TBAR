<?php

namespace App\Models;

use App\Models\Sar\TRegistrosKanban;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class DespachosItems extends Model {
    use HasFactory;
    protected $fillable = ['tipo', 'despacho_id', 'user_id', 'temporal', 'user_id2', 'pedido', 'pickeado', 'cl2', 'kanban', 'posicion', 'modelo', 'deposito_id', 'kanban_envio', 'cuarentena', 'estado_calidad', 'desbalanceo', 'user_id_calidad', 'fecha_calidad', 'produccion'];

    public function kanban(): HasOne {
        return $this->hasOne(Kanbans::class, 'codigo', 'kanban');
    }

    public function ubicacion(): HasOne {
        return $this->hasOne(Ubicaciones::class, 'nombre', 'posicion');
    }

    public function despacho(): HasOne {
        return $this->hasOne(Despachos::class, 'id', 'despacho_id');
    }

    public function FgData(): HasOne {
        return $this->hasOne(LogFg::class, 'kanban', 'kanban');
    }

    public function FgDataSar(): HasOne {
        return $this->hasOne(TRegistrosKanban::class, 'N_KANBAN', 'kanban')->where('ACCION', 'FINISH GOOD SEAT');
    }
}
