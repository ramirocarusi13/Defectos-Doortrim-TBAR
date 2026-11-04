<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EpEtiqueta extends Model {
    use HasFactory;

    protected $fillable = ['airbag', 'kanban', 'linea', 'linea_real', 'user_validacion', 'hora_validacion', 'modelo_id', 'kanban_impresion', 'id_etiqueta', 'modelo', 'user_id_reimpresion', 'lado', 'user_id', 'codigo', 'secuencia', 'estado', 'qr', 'ubicacion', 'tipo', 'vehiculo'];

    public function modelod(): HasOne {
        return $this->hasOne(Modelos::class, 'nombre', 'modelo');
    }

    public function reparaciones(): HasMany {
        return $this->hasMany(FallasInformadas::class, 'qr', 'qr');
    }

    public function userImpresion(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function userValidacion(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_validacion');
    }

    public function userValidacionCaraB(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_validacion_carab');
    }

    public function historial(): HasMany {
        return $this->hasMany(EpEtiquetaLog::class, 'qr', 'qr')->orderBy('created_at', 'desc');
    }
}
