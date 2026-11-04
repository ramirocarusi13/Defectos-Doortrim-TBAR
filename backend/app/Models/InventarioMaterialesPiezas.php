<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventarioMaterialesPiezas extends Model {
    use HasFactory;

    protected $fillable = ['material_id', 'user_id', 'cantidad', 'confirmado', 'sector', 'created_at'];

    public function material(): HasOne {
        return $this->hasOne(MaterialesPiezas::class, 'id', 'material_id');
    }

    public function user(): HasOne {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
