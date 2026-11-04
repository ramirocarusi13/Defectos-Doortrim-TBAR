<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;


/*

10 = MEMBER
20 = TL
30 = GL
40 = JEFE
50 = COORDINADOR
60 = GERENTE
70 = DEV
*/

class User extends Authenticatable implements OAuthenticatable {
    use HasApiTokens, HasFactory, Notifiable;

    // `users` puede vivir en otra base que el resto (deploy DOOR comparte los
    // usuarios de EOL_TBAR). Por defecto sqlsrv_users apunta a la misma base
    // que sqlsrv, asi que en el deploy actual no cambia nada.
    protected $connection = 'sqlsrv_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tl',
        'cod_autorizacion',
        'linea',
        'gl',
        'operacion',
        'departamento',
        'rol',
        'turno',
        'area',
        'proceso_critico',
        'activo',
        'imagen'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function rol(): HasOneThrough {
        return $this->hasOneThrough(Roles::class, RolesUsuarios::class, 'user_id', 'id', 'id', 'rol_id');
    }


    public function menu(): HasManyThrough {
        return $this->hasManyThrough(Menu::class, MenuUser::class, 'user_id', 'id', 'id', 'menu_id');
    }

    public function operacionTrabajoLinea(): HasOne {
        return $this->hasOne(UserOperacionLinea::class, 'user_id', 'id');
    }

    public function linea(): HasOne {
        return $this->hasOne(UserLinea::class, 'user_id', 'id');
    }

    public function operacion(): HasOne {
        return $this->hasOne(UserOperacionLinea::class, 'user_id', 'id');
    }

    public function polivalencias(): HasMany {
        return $this->hasMany(UserPolivalencias::class, 'user_id', 'id');
    }

    public function operacionLinea(): HasOneThrough {
        return $this->hasOneThrough(LineaOperaciones::class, UserOperacionLinea::class, 'user_id', 'id', 'id', 'operacion_id');
    }

    public function reemplazo(): HasOne {
        return $this->hasOne(UserOperacionLineaReplacement::class, 'titular_user_id', 'id')->where('hasta', '>=', date('Y-m-d'));
    }

    // public function menu(): HasMany {
    //     return $this->hasMany(MenuUser::class, 'user_id', 'id');
    // }
}
