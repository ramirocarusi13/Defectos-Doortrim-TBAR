<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Historial de eventos (trazabilidad) de una pieza serializada. Clon
 * estructural de EolDefectoEvento: log append-only, solo created_at.
 */
class EolPiezaEvento extends Model
{
    protected $table = 'eol_pieza_eventos';

    public $timestamps = false;

    protected $fillable = [
        'pieza_id',
        'pieza_qr',
        'evento',
        'defecto_id',
        'door_id',
        'estado_desde',
        'estado_hasta',
        'buffer_id',
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
