<?php

namespace App\Models\Sar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SARModelo extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 't_modelos';

    // protected $hidden = ['created_at', 'updated_at'];
    // protected $fillable = [''];
}
