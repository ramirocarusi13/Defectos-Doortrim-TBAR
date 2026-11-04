<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TmpLectra extends Model {
    use HasFactory;

    protected $fillable = ['lectra', 'group', 'modelo', 'dado', 'inicio', 'horaInicio', 'horaFin', 'fin', 'demora'];
}
