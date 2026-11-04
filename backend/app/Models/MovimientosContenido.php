<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MovimientosContenido extends Model {
    use HasFactory;

    protected $table = 'wms_movimientos_contenidos';
    protected $fillable = ['ref', 'lote', 'cantidad', 'movimiento_id', 'ubicacion_id', 'unidad_id', 'ref_id'];

    public function unidad(): HasOne {
        return $this->hasOne(Unidades::class, 'id', 'unidad_id');
    }

    public function kanban(): HasOne {
        return $this->hasOne(Kanbans::class, 'codigo', 'ref');
    }

    public function ubicacion(): HasOne {
        return $this->hasOne(Ubicaciones::class, 'id', 'ubicacion_id');
    }

    public function movimiento(): BelongsTo {
        return $this->belongsTo(Movimientos::class, 'movimiento_id', 'id');
    }

    public function reservado(): HasOne {
        return $this->hasOne(DespachosItems::class, 'kanban', 'ref')
            ->whereHas('despacho', function ($q) {
                $q->where('pendiente', true);
            });
        // ->where('pickeado', false);
    }

    public function referencia(): HasOne {
        return $this->hasOne(RegistroIngresoRollo::class, 'id', 'ref_id');
    }
}
