<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TiendaPedido extends Model {
    use HasFactory;

    protected $fillable = ['user_id', 'user_autorizante_id', 'pendiente', 'falla_id', 'kanban_id', 'linea_id'];


    public function items(): HasMany {
        return $this->hasMany(TiendaPedidoItems::class, 'pedido_id', 'id');
    }

    public function kanban(): HasOne {
        return $this->hasOne(Kanbans::class, 'id', 'kanban_id');
    }

    public function user(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function linea(): HasOne {
        return $this->hasOne(Lineas::class, 'id', 'linea_id');
    }
}
