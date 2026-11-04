<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockMateriales extends Model {
    use HasFactory;

    protected $table = 'vt_stock_materiales';

    public function referencia(): HasOne {
        return $this->hasOne(RegistroIngresoRollo::class, 'id', 'ref_id');
    }

    // public function FgData(): HasOne {
    //     return $this->hasOne(LogFg::class, 'kanban', 'ref');
    // }

    // public function FgDataSar(): HasOne {
    //     return $this->hasOne(TRegistrosKanban::class, 'N_KANBAN', 'ref')->where('ACCION', 'FINISH GOOD SEAT');
    // }
}
