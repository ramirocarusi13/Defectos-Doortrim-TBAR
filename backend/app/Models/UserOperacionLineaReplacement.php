<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOperacionLineaReplacement extends Model {
    use HasFactory;

    protected $table = 'user_operacion_linea_replacements';

    protected $fillable = [
        'operacion_id',
        'titular_user_id',
        'replacement_user_id',
        'turno',
        'desde',
        'hasta',
        'motivo'
    ];

    public function operacion(): BelongsTo {
        return $this->belongsTo(LineaOperaciones::class, 'operacion_id', 'id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'replacement_user_id', 'id');
    }

    // public function titular(): BelongsTo {
    //     return $this->belongsTo(Employees::class, 'titular_user_id', 'id');
    // }

    // public function replacement(): BelongsTo {
    //     return $this->belongsTo(Employees::class, 'replacement_user_id', 'id');
    // }
}
