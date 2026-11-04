<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dados extends Model {
    use HasFactory;

    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = ['codigo', 'modelo_id', 'material_id', 'tiempo_corte', 't_pos', 'habilitado'];

    public function modelo(): HasOne {
        return $this->hasOne(Modelos::class, 'id', 'modelo_id');
    }
}
