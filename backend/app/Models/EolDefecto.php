<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EolDefecto extends Model
{
    protected $table = 'eol_defectos';

    protected $guarded = [];

    protected $casts = [
        'cantidad'    => 'integer',
        'es_critico'  => 'boolean',
        'es_leve'     => 'boolean',
        'pos_x'       => 'float',
        'pos_y'       => 'float',
        'screen_x'    => 'float',
        'screen_y'    => 'float',
        'marcado_at'   => 'datetime',
        'reparado_at'  => 'datetime',
        'validado_at'  => 'datetime',
        'cambiado_at'  => 'datetime',
    ];
}
