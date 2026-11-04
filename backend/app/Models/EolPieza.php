<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maestro de piezas serializadas (DOOR / ARM_REST) del mini buffer de la
 * linea de door. Ver database/migrations/2026_08_27_000200_create_eol_piezas_table.php.
 */
class EolPieza extends Model
{
    protected $table = 'eol_piezas';

    protected $guarded = [];

    protected $casts = [
        'disponible_desde'  => 'datetime',
        'alta_at'           => 'datetime',
        'tomado_at'         => 'datetime',
        'ultimo_evento_at'  => 'datetime',
        'ciclos_cambio'     => 'integer',
        'ciclos_retrabajo'  => 'integer',
    ];
}
