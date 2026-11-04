<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LogEstadosKanbans extends Model {
    use HasFactory;

    protected $fillable = ['kanban_id', 'estado_id', 'user_id', 'estado_previo_id'];

    public function estado(): HasOne {
        return $this->hasOne(Estados::class, 'id', 'estado_id');
    }
}
