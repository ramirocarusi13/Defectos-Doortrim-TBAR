<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RrhhLegajos extends Model {
    use HasFactory;

    protected $fillable = ['nombre', 'turno',  'area_id', 'activo', 'motivo_baja'];

    public function area(): HasOne {
        return $this->hasOne(rrhhAreas::class, 'id', 'area_id');
    }
}
