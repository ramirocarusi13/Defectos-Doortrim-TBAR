<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogFg extends Model {
    use HasFactory;

    protected $table = 'LOG_FG';


    protected $fillable = ['kanban', 'fecha'];
}
