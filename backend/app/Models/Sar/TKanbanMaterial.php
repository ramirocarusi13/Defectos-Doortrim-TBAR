<?php

namespace App\Models\Sar;

use App\Http\Kanban;
use App\Models\Kanbans;
use App\Models\MaterialesPiezas;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TKanbanMaterial extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 't_kanban_material';
}
