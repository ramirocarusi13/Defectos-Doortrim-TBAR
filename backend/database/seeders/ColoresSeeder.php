<?php

namespace Database\Seeders;

use App\Models\Colores;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ColoresSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        Colores::create(['color' => 'BLACK']);
        Colores::create(['color' => 'BROWN']);
        Colores::create(['color' => 'WHITE']);
        Colores::create(['color' => 'GRAY']);
    }
}
