<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModeloDado extends Model {
    use HasFactory;

    protected $fillable = ['modelo_id', 'dado_id', 'tipo', 'esA', 'esB', 'ordenA', 'ordenB', 'ordenCompleto'];

    public function dado() {
        return $this->hasMany(ModeloKanbanPadre::class, 'id', 'dado_id');
    }

    // public function dadosB() {
    //     return $this->hasMany(ModeloKanbanPadre::class, 'dado_id', 'id')->where('tipo', 'A');
    // }
}
