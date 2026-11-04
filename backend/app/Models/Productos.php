<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Productos extends Model {
    use HasFactory;

    protected $fillable = ['codigo', 'categoria_id', 'proveedor_id'];

    public function proveedor(): HasOne {
        return $this->hasOne(Proveedores::class, 'id', 'proveedor_id');
    }

    public function categoria(): HasOne {
        return $this->hasOne(Categorias::class, 'id', 'categoria_id');
    }
}
