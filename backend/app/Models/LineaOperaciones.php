<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LineaOperaciones extends Model {
    use HasFactory;

    protected $fillable = ['nombre', 'linea', 'orden', 'habilitado', 'nivel', 'sublinea'];

    public function operarioAmarillo(): HasOne {
        return $this->hasOne(UserOperacionLinea::class, 'operacion_id', 'id')->where('turno', 'A');
    }

    public function operarioBlanco(): HasOne {
        return $this->hasOne(UserOperacionLinea::class, 'operacion_id', 'id')->where('turno', 'B');
    }

    public function operario(): HasOne {
        return $this->hasOne(UserOperacionLinea::class, 'operacion_id', 'id');
    }
}
