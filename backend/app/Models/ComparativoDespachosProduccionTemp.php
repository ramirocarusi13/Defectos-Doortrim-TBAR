<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComparativoDespachosProduccionTemp extends Model {
    use HasFactory;

    protected $fillable = ['fecha', 'titulo', 'orden', 'vehiculos', 'cuero', 'bcab', 'tup', 'm7', 'm9', 'prod_vehiculos', 'prod_cuero', 'prod_bcab', 'prod_tup', 'prod_m7', 'prod_m9', 'dif_vehiculos', 'dif_cuero', 'dif_bcab', 'dif_tup', 'dif_m7', 'dif_m9', 'hiace', 'prod_hiace', 'dif_hiace'];
}
