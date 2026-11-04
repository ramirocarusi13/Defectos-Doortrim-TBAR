<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLinea extends Model {
    use HasFactory;

    protected $fillable = ['user_id', 'linea_id', 'sublinea', 'titular', 'operacion_id'];
}
