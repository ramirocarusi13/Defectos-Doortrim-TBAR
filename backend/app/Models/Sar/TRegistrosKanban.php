<?php

namespace App\Models\Sar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TRegistrosKanban extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 'T_REGISTROS_KANBAN';
}
