<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPartes extends Model {
    use HasFactory;
    protected $fillable = ['parte_id', 'cantidad', 'kanban_id', 'user_id', 'deposito_id'];
}
