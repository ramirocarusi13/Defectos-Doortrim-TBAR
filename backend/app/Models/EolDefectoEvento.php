<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EolDefectoEvento extends Model
{
    protected $table = 'eol_defecto_eventos';

    public $timestamps = false;

    protected $fillable = [
        'eol_defecto_id',
        'qr',
        'evento',
        'user_id',
        'user_nombre',
        'departamento',
        'turno',
        'puesto_id',
        'puesto_nombre',
        'observaciones',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
