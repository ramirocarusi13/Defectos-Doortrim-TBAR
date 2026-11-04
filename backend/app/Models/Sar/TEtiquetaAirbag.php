<?php

namespace App\Models\Sar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TEtiquetaAirbag extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 'T_ETIQUETA_AIRBAG';

    public function mod(): HasOne {
        return $this->hasOne(TModelosAirbag::class, 'LETRA', 'MODELO');
    }
}
