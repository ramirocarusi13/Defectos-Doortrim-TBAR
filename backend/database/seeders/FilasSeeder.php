<?php

namespace Database\Seeders;

use App\Models\Filas;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FilasSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        Filas::create(['fila' => 'FRONT']);
        Filas::create(['fila' => 'REAR 1']);
        Filas::create(['fila' => 'REAR 2']);
        Filas::create(['fila' => 'REAR 3']);
        Filas::create(['fila' => 'SUP']);
    }
}
