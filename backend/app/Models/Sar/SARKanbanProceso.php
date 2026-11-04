<?php

namespace App\Models\Sar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SARKanbanProceso extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 'T_KANBAN_PROCESO';

    // protected $hidden = ['created_at', 'updated_at'];
    // protected $fillable = [''];
}
