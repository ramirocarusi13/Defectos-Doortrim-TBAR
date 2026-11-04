<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EpKanbanLote extends Model {
    use HasFactory;

    protected $fillable = ['kanban1', 'kanban2'];
}
