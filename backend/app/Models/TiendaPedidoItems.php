<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TiendaPedidoItems extends Model {
    use HasFactory;
    protected $fillable = ['pedido_id', 'pieza_id', 'cantidad', 'qr'];

    public function pieza(): HasOne {
        return $this->hasOne(Piezas::class, 'id', 'pieza_id');
    }
}
