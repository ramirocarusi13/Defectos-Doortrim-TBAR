<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanCostura extends Model {
    use HasFactory;

    protected $fillable = ['fecha', 'modelo', 'linea', 'tm', 'tt'];
}
