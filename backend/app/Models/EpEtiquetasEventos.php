<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EpEtiquetasEventos extends Model {
    use HasFactory;

    protected $fillable = ['kanban', 'qr_etiqueta', 'user_id', 'evento', 'info_adi'];
}
