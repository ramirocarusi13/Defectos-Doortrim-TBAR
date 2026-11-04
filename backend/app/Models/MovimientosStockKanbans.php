<?php

namespace App\Models;

use App\Models\Sar\TRegistrosKanban;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MovimientosStockKanbans extends Model {
    use HasFactory;

    protected $table = 'VT_MOVIMIENTOS_KANBAN_STOCK';

    // public function FgData(): HasOne {
    //     return $this->hasOne(LogFg::class, 'kanban', 'ref');
    // }

    // public function FgDataSar(): HasOne {
    //     return $this->hasOne(TRegistrosKanban::class, 'N_KANBAN', 'ref')->where('ACCION', 'FINISH GOOD SEAT');
    // }
}
