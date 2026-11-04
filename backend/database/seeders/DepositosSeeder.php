<?php

namespace Database\Seeders;

use App\Models\Depositos;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepositosSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {

        Depositos::create(['descripcion' => 'Almacén']);
        Depositos::create(['descripcion' => 'Buffer']);
        Depositos::create(['descripcion' => 'Sub Assy']);
        Depositos::create(['descripcion' => 'Costura']);
        Depositos::create(['descripcion' => 'Tienda']);
        Depositos::create(['descripcion' => 'Scrap']);
        Depositos::create(['descripcion' => 'Corte']);
        Depositos::create(['descripcion' => 'Racks']);
        Depositos::create(['descripcion' => 'Dollys']);
        Depositos::create(['descripcion' => 'Temporal A']);
        Depositos::create(['descripcion' => 'Temporal B']);
    }
}
