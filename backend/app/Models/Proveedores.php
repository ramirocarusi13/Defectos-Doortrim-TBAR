<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedores extends Model {
    use HasFactory;

    protected $fillable = ['interfaz_barra', 'nombre', 'email', 'email2', 'email3'];

    public function interface() {
        return $this->hasOne(InterfazProveedor::class, 'proveedor_id', 'id');
    }
}
