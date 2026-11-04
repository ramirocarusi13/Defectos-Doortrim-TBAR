<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VTDefectosInternos extends Model {
    use HasFactory;

    protected $table = 'VT_DEFECTOS_INTERNOS';

    public function scrap(): HasMany {
        return $this->hasMany(Scrap::class, 'defecto_id', 'id');
    }

    public function tipo(): HasOne {
        return $this->hasOne(TipoPartes::class, 'id', 'tipo_id');
    }

    public function lado(): HasOne {
        return $this->hasOne(LadoPartes::class, 'id', 'lado_id');
    }

    public function falla(): HasOne {
        return $this->hasOne(CodigoFalla::class, 'id', 'falla_id');
    }

    public function imagen(): HasOne {
        return $this->hasOne(ModeloFalla::class, 'id', 'image_id');
    }

    // public function referencia(): HasOne {
    //     return $this->hasOne(RegistroIngresoRollo::class, 'id', 'ref_id');
    // }

    // public function FgData(): HasOne {
    //     return $this->hasOne(LogFg::class, 'kanban', 'ref');
    // }

    // public function FgDataSar(): HasOne {
    //     return $this->hasOne(TRegistrosKanban::class, 'N_KANBAN', 'ref')->where('ACCION', 'FINISH GOOD SEAT');
    // }
}
