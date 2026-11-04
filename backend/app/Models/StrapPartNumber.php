<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StrapPartNumber extends Model {
    use HasFactory;

    protected $fillable = ['modelo', 'part_number', 'posicion', 'nro_fifo'];
}
