<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InicioLectra extends Model {
    use HasFactory;

    protected $fillable = ['lectra', 'fecha', 'hora'];
}
