<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EpKanbanEstado extends Model {
    use HasFactory;

    protected $fillable = ['user_impresion', 'user_costura', 'user_qc', 'kanban'];
}
