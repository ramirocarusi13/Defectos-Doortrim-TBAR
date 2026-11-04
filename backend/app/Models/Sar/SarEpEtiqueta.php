<?php

namespace App\Models\Sar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SarEpEtiqueta extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 'EP_ETIQUETA';
}
