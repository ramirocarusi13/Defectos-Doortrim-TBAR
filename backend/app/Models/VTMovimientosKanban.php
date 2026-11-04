<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VTMovimientosKanban extends Model {
    use HasFactory;

    protected $table = 'VT_MOVIMIENTOS_KANBAN';
}
