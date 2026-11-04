<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StrapEvento extends Model {
    use HasFactory;

    protected $fillable = ['evento', 'user_id', 'autoriza_user_id', 'codigo_escaneado', 'kanban', 'posicion', 'modelo', 'lote', 'cantidad', 'tipo', 'linea', 'user_solicitante'];

    public function strap(): HasOne {
        return $this->hasOne(ControlStrap::class, 'codigo_barra', 'codigo_escaneado');
    }

    public function user(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function solicitante(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_solicitante');
    }

    public function autorizante(): HasOne {
        return $this->hasOne(User::class, 'id', 'autoriza_user_id');
    }
}
