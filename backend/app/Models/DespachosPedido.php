<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DespachosPedido extends Model {
    use HasFactory;

    protected $fillable = ['tipo', 'produccion', 'temporal', 'despacho_id', 'pedido', 'modelo', 'pendiente', 'desbalanceo', 'cl2'];

    public function despacho(): HasOne {
        return $this->hasOne(Despachos::class, 'id', 'despacho_id');
    }
}
