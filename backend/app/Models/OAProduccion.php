<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OAProduccion extends Model {
    use HasFactory;

    protected $fillable = ['fecha', 'linea', 'modelo', 'plan', 'real', 'turno'];
}
