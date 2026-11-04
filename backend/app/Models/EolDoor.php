<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un registro por swap fisico en la linea de door (pieza defectuosa <->
 * pieza de reemplazo del buffer). Ver
 * database/migrations/2026_08_27_000210_create_eol_doors_table.php.
 */
class EolDoor extends Model
{
    protected $table = 'eol_doors';

    protected $guarded = [];

    protected $casts = [
        'defectos_count'            => 'integer',
        'alta_automatica_retirada'  => 'boolean',
        'cambiado_at'               => 'datetime',
    ];
}
