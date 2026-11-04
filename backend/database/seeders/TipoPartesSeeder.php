<?php

namespace Database\Seeders;

use App\Models\TipoPartes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TipoPartesSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        TipoPartes::create(['tipo' => 'BACK']);
        TipoPartes::create(['tipo' => 'CUSHION']);
        TipoPartes::create(['tipo' => 'A/R']);
        TipoPartes::create(['tipo' => 'H/R']);
        TipoPartes::create(['tipo' => 'DOOR ORNAMENT']);
        TipoPartes::create(['tipo' => 'DOOR ARM REST']);
        TipoPartes::create(['tipo' => 'SET']);
        TipoPartes::create(['tipo' => 'PATCH']);
        TipoPartes::create(['tipo' => 'T-UP']);
    }
}
