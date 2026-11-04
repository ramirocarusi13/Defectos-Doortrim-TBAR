<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AusentismoDiario extends Model {
    use HasFactory;

    protected $fillable = ['fecha', 'ausentismo_id'];
}
