<?php

namespace App\Models\Sar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PKanbanProceso extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 'p_kanban_proceso';

    // protected $hidden = ['created_at', 'updated_at'];
    // protected $fillable = [''];
}
