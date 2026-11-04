<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FallasInformadas extends Model {
    use HasFactory;

    protected $fillable = ['qr', 'cantidad', 'screenX', 'screenY', 'tipo_falla', 'origen', 'linea', 'tipo_linea', 'falla_id', 'user_autorizante', 'observaciones', 'tipo_id', 'image_id', 'imagen_marcada', 'x', 'y', 'color', 'lado_id', 'estado', 'operacion', 'turno', 'user_id', 'fecha_retrabajo', 'user_retrabajo_id', 'user_operacion_id', 'es_critico'];

    public function scrap(): HasMany {
        return $this->hasMany(Scrap::class, 'defecto_id', 'id');
    }

    public function tipo(): HasOne {
        return $this->hasOne(TipoPartes::class, 'id', 'tipo_id');
    }

    public function lado(): HasOne {
        return $this->hasOne(LadoPartes::class, 'id', 'lado_id');
    }

    public function falla(): HasOne {
        return $this->hasOne(CodigoFalla::class, 'id', 'falla_id');
    }

    public function imagen(): HasOne {
        return $this->hasOne(ModeloFalla::class, 'id', 'image_id');
    }

    // public function etiqueta(): HasOne {
    //     return $this->hasOne(EpEtiqueta::class, 'qr', 'qr');
    // }

    public function etiqueta(): BelongsTo {
        return $this->belongsTo(EpEtiqueta::class, 'qr', 'qr');
    }

    public function user(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function user_retrabajo(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_retrabajo_id');
    }

    public function user_tl(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_autorizante');
    }

    public function operador(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_operacion_id');
    }

    public function operacion(): HasOne {
        return $this->hasOne(LineaOperaciones::class, 'id', 'operacion');
    }

    // public function kanban(): HasOne {
    //     return $this->hasOne(Kanbans::class, 'id', 'kanban_id');
    // }

    public function scopeFiltroBase($query, $turno, $linea, $fecha, $fechaManana) {
        return $query->with(['falla', 'scrap'])->leftJoin('users', 'fallas_informadas.user_operacion_id', 'users.id')
            // ->whereNotNull('fallas_informadas.user_operacion_id')
            // ->where('users.turno', $turno)
            ->where('fallas_informadas.turno', $turno)
            ->where('fallas_informadas.linea', $linea)
            ->whereBetween('fallas_informadas.created_at', [
                $fecha->format('Y-m-d') . ' 06:00:00',
                $fechaManana->format('Y-m-d') . ' 01:00:00'
            ]);
    }

    public function scopeFiltroBaseSinLinea($query, $fecha, $fechaManana) {
        return $query->with(['falla', 'scrap'])->leftJoin('users', 'fallas_informadas.user_operacion_id', 'users.id')
            // ->where('fallas_informadas.turno', $turno)
            ->whereBetween('fallas_informadas.created_at', [
                $fecha->format('Y-m-d') . ' 06:00:00',
                $fechaManana->format('Y-m-d') . ' 01:00:00'
            ]);
    }
}
