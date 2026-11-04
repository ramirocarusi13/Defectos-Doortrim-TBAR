<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PcPlanProduccion extends Model {
    use HasFactory;

    protected $fillable = ['modelo_id', 'kanban_id', 'orden', 'consumo', 'vigencia_desde', 'vigencia_hasta'];

    public function modelo(): HasOne {
        return $this->hasOne(Modelos::class, 'id', 'modelo_id');
    }

    public function kanban(): HasOne {
        return $this->hasOne(Kanbans::class, 'id', 'kanban_id');
    }
}
