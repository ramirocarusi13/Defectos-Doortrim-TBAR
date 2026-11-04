<?php

namespace App\Models;

use App\Models\Sar\TRegistrosKanban;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Despachos extends Model {
    use HasFactory;

    protected $fillable = ['pendiente', 'user_id', 'run', 'fecha_salida_camion', 'fecha', 'fecha_llegada_camion'];

    public function items(): HasMany {
        return $this->hasMany(DespachosItems::class, 'despacho_id', 'id')->orderBy('deposito_id')->orderBy('posicion');
    }

    public function itemsCover(): HasMany {
        return $this->hasMany(DespachosItems::class, 'despacho_id', 'id')->where('tipo','COVER')->orderBy('deposito_id')->orderBy('posicion');
    }

    public function itemsEnCuarentena(): HasMany {
        return $this->hasMany(DespachosItems::class, 'despacho_id', 'id')->where('cuarentena', true);
    }

    public function pedido(): HasMany {
        return $this->hasMany(DespachosPedido::class, 'despacho_id', 'id');
    }

    public function pedidoCover(): HasMany {
        return $this->hasMany(DespachosPedido::class, 'despacho_id', 'id')->where('tipo','COVER');
    }

    public function user(): HasOne {
        return $this->HasOne(User::class, 'id', 'user_id');
    }
}
