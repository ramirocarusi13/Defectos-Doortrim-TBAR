<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenIssues extends Model {
    use HasFactory;

    protected $fillable = ['user_id', 'titulo', 'descripcion', 'abierto'];

    public function imagenes(): HasMany {
        return $this->hasMany(OpenIssuesImages::class, 'issue_id', 'id');
    }
}
