<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class UserOperacionLinea extends Model {
    use HasFactory;

    protected $fillable = ['user_id', 'operacion_id', 'autorizante', 'turno'];

    // public static function boot() {
    //     parent::boot();

    //     static::updated(function (UserOperacionLinea $u) {
    //         UserMovimientos::create([
    //             'user_id'       => $u->user_id,
    //             'operacion_id'  => $u->operacion_id,
    //             'autorizante'   => $u->autorizante
    //         ]);
    //     });

    //     static::created(function (UserOperacionLinea $u) {

    //         UserMovimientos::create([
    //             'user_id'       => $u->user_id,
    //             'operacion_id'  => $u->operacion_id,
    //             'autorizante'   => $u->autorizante
    //         ]);
    //     });
    // }

    public function polivalencia(): HasOne {
        return $this->hasOne(UserPolivalencias::class, 'operacion_id', 'operacion_id');
    }

    public function user(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
