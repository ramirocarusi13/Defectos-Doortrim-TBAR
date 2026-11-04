<?php

namespace App\Models\Sar;

use App\Http\EventosStrap;
use App\Models\StrapEvento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SAREtiquetaAirbag extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 'T_ETIQUETA_AIRBAG';

    // protected $hidden = ['created_at', 'updated_at'];
    // protected $fillable = [''];

    public function modelo(): HasOne {
        return $this->hasOne(SARModeloAirbag::class, 'LETRA', 'MODELO');
    }
}
