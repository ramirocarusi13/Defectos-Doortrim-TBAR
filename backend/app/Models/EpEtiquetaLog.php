<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EpEtiquetaLog extends Model {
    use HasFactory;
    protected $table = 'ep_etiquetas_log';

    protected $fillable = ['qr', 'kanban', 'hora_validacion', 'user_validacion', 'hora_validacion_carab', 'user_validacion_carab', 'kanban_carab', 'linea'];

    public function userValidacion(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_validacion');
    }

    public function userValidacionCaraB(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_validacion_carab');
    }

    public function linea(): HasOne {
        return $this->hasOne(Lineas::class, 'id', 'linea');
    }
}
