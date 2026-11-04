<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepositoLayout extends Model {
    use HasFactory;

    protected $table = 'wms_deposito_layouts';


    protected $fillable = ['deposito_id', 'calles', 'racks', 'posiciones', 'niveles', 'calle_letra', 'rack_letra', 'posicion_letra', 'nivel_letra', 'formato_codigo'];
}
