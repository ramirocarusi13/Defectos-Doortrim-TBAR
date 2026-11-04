<?php

namespace App\Models;

use App\Http\Depositos;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\Log;

class Piezas extends Model {
    use HasFactory;

    protected $fillable = ['codigo', 'material_pieza_id', 'pto_optimo', 'area', 'kanban_reposicion', 'parte_id', 'material_id', 'color_id', 'lado', 'dado', 'minimo', 'maximo', 'p_width', 'p_left', 'p_top', 'p_height', 'p2_width', 'p2_left', 'p2_top', 'p2_height'];

    public function dadoReposicion(): HasOne {
        return $this->hasOne(DadosPieza::class, 'pieza_id', 'id');
    }

    public function material(): HasMany {
        return $this->hasMany(PiezasMateriales::class, 'pieza_id', 'id');
    }

    public function material_pieza(): HasOne {
        return $this->hasOne(MaterialesPiezas::class, 'id', 'material_pieza_id');
    }

    public function parte(): HasOne {
        return $this->hasOne(Partes::class, 'id', 'parte_id')->where('activo', 1);
    }

    // public function lado(): HasOne {
    //     return $this->hasOne(LadoPartes::class, 'id', 'parte_id')->where('activo', 1);
    // }

    public function stockTienda(): HasMany {
        return $this->hasMany(StockPiezas::class, 'pieza_id', 'id')->where('deposito_id', Depositos::TIENDA);
    }

    public function modelo(): HasOneThrough {
        return $this->hasOneThrough(Modelos::class, Partes::class, 'id', 'id', 'parte_id', 'modelo_id');
    }

    public function kanbanReemplazo(): HasOne {
        return $this->hasOne(KanbansReemplazo::class, 'pieza_id', 'id');
    }
}
