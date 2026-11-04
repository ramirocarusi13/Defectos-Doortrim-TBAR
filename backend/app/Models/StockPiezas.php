<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockPiezas extends Model {
    use HasFactory;

    protected $fillable = ['pieza_id', 'motivo', 'kanban_reemplazo_id', 'cantidad', 'kanban_id', 'fecha', 'user_id', 'deposito_id'];

    public function pieza(): HasOne {
        return $this->hasOne(Piezas::class, 'id', 'pieza_id');
    }

    public function kanban(): HasOne {
        return $this->hasOne(Kanbans::class, 'id', 'kanban_id');
    }

    public function kanbanReemplazo(): HasOne {
        return $this->hasOne(KanbansReemplazo::class, 'id', 'kanban_reemplazo_id');
    }

    public function deposito(): HasOne {
        return $this->hasOne(Depositos::class, 'id', 'deposito_id');
    }
}
