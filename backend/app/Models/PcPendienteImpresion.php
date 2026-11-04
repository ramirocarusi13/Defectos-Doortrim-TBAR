<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PcPendienteImpresion extends Model {
    use HasFactory;

    protected $fillable = ['tipo', 'kanban_id', 'motivo', 'pendiente', 'fecha_impresion'];


    public function kanban(): HasOne {
        return $this->hasOne(Kanbans::class, 'id', 'kanban_id');
    }
}
