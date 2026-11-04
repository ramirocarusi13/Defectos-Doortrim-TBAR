<?php

namespace App\Models\Sar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SARDoorTrim extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 'T_DOOR_TRIM';

    // protected $hidden = ['created_at', 'updated_at'];
    // protected $fillable = [''];
}
