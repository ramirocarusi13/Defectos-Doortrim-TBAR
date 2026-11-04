<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ModelosCompartidos extends Model {
    use HasFactory;

    protected $fillable = ['name', 'modelo1_id', 'modelo2_id', 'modelo3_id',];

    public function modelo1(): HasOne {
        return $this->hasOne(Modelos::class, 'id', 'modelo1_id');
    }

    public function modelo2(): HasOne {
        return $this->hasOne(Modelos::class, 'id', 'modelo2_id');
    }

    public function modelo3(): HasOne {
        return $this->hasOne(Modelos::class, 'id', 'modelo3_id');
    }
}
