<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserPolivalencias extends Model {
    use HasFactory;

    protected $fillable = ['user_id', 'operacion_id', 'polivalencia'];

    public function operacion(): HasOne {
        return $this->hasOne(LineaOperaciones::class, 'id', 'operacion_id');
    }

    public function user(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
