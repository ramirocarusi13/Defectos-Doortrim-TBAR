<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ControlStrap extends Model {
    use HasFactory;

    protected $fillable = ['codigo_barra', 'remanente', 'cantidad', 'kanban', 'anulado', 'fecha_entrega', 'fifo', 'lote', 'user_id_in', 'part_number', 'posicion', 'modelo', 'user_id', 'user_id_pedido', 'entregado'];

    public function parts(): HasOne {
        return $this->hasOne(StrapPartNumber::class, 'part_number', 'part_number');
    }

    public function userIn(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_id_in');
    }

    public function userOut(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function eventosOut(): HasMany {
        return $this->hasMany(StrapEvento::class, 'codigo_barra', 'codigo_escaneado');
    }
}
