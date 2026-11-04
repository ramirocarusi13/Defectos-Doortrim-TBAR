<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EpEtiquetaMid extends Model {
    use HasFactory;
    protected $table = 'ep_etiqueta_mid';

    protected $fillable = ['qr', 'modelo_id', 'linea_real'];

    // public function modelod(): HasOne {
    //     return $this->hasOne(Modelos::class, 'nombre', 'modelo');
    // }

    // public function reparaciones(): HasMany {
    //     return $this->hasMany(FallasInformadas::class, 'qr', 'qr');
    // }

    // public function userImpresion(): HasOne {
    //     return $this->hasOne(User::class, 'id', 'user_id');
    // }

    // public function userValidacion(): HasOne {
    //     return $this->hasOne(User::class, 'id', 'user_validacion');
    // }

    // public function userValidacionCaraB(): HasOne {
    //     return $this->hasOne(User::class, 'id', 'user_validacion_carab');
    // }
}
