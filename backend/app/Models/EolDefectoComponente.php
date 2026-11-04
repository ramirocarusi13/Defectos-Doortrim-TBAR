<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EolDefectoComponente extends Model
{
    protected $table = 'eol_defecto_componentes';

    public $timestamps = false;

    protected $fillable = [
        'eol_defecto_id',
        'qr',
        'componente_codigo',
        'componente_nombre',
        'accion',
        'cantidad',
        'user_id',
        'user_nombre',
        'turno',
        'created_at',
    ];

    protected $casts = [
        'cantidad'   => 'integer',
        'created_at' => 'datetime',
    ];
}
