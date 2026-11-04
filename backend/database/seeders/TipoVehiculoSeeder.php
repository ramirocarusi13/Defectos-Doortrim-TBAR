<?php

namespace Database\Seeders;

use App\Models\TipoVehiculo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TipoVehiculoSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        TipoVehiculo::create(['tipo' => 'B-CAB']);
        TipoVehiculo::create(['tipo' => 'D-CAB']);
        TipoVehiculo::create(['tipo' => 'SUV']);
    }
}
