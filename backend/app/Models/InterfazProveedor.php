<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterfazProveedor extends Model {
    use HasFactory;

    protected $fillable = ['proveedor_id', 'interfaz', 'delimitador', 'interfaz_p', 'delimitador_p'];
}
