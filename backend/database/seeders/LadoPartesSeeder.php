<?php

namespace Database\Seeders;

use App\Models\LadoPartes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LadoPartesSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        LadoPartes::create(['lado' => '-']);
        LadoPartes::create(['lado' => 'RH']);
        LadoPartes::create(['lado' => 'LH']);
        LadoPartes::create(['lado' => '100%']);
        LadoPartes::create(['lado' => 'LATERAL']);
        LadoPartes::create(['lado' => 'CENTRAL']);
    }
}
