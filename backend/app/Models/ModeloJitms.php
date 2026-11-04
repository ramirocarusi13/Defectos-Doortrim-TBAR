<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModeloJitms extends Model
{
    protected $table = 'modelos_jitms';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'id_producto' => 'integer',
        'sd_id'       => 'integer',
        'id_familia'  => 'integer',
        'airbag'      => 'integer',
        'posicion'    => 'integer',
        'fecha_sync'  => 'datetime',
    ];
}
