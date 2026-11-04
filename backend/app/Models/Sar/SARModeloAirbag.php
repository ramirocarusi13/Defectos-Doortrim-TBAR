<?php

namespace App\Models\Sar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SARModeloAirbag extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 'T_MODELO_AIRBAG';

    // protected $hidden = ['created_at', 'updated_at'];
    // protected $fillable = [''];
}
