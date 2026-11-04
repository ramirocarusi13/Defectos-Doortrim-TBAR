<?php

namespace Database\Seeders;

use App\Models\LineaOperaciones;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OperacionesLineaSeeder extends Seeder {

    public function run() {
        $lineas = ['0', 'M1', 'M2', 'M3', 'S1', 'S2', 'M4'];

        foreach ($lineas as $linea) {
            for ($i = 0; $i < 10; $i++) {
                LineaOperaciones::create(['nombre' => 'OP#' . strval($i + 1), 'linea' => $linea, 'orden' => $i + 1, 'habilitado' => true]);
            }
        }
    }
}
