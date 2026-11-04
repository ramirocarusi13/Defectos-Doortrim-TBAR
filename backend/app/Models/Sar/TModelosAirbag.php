<?php

namespace App\Models\Sar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TModelosAirbag extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 'T_MODELO_AIRBAG';
}
