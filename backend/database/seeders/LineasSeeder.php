<?php

namespace Database\Seeders;

use App\Models\Lineas;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class LineasSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        Lineas::create(['codigo' => 'M1', 'capacidad' => 16, 'posicion' => 1, 'columnas' => 4]);
        Lineas::create(['codigo' => 'M2', 'capacidad' => 16, 'posicion' => 2, 'columnas' => 4]);
        Lineas::create(['codigo' => 'M3', 'capacidad' => 8, 'posicion' => 3, 'columnas' => 2]);
        Lineas::create(['codigo' => 'M4', 'capacidad' => 12, 'posicion' => 4, 'columnas' => 3]);
        Lineas::create(['codigo' => 'M5', 'capacidad' => 8, 'posicion' => 5, 'columnas' => 2]);
        Lineas::create(['codigo' => 'M6', 'capacidad' => 8, 'posicion' => 6, 'columnas' => 2]);
        Lineas::create(['codigo' => 'M7', 'capacidad' => 9, 'posicion' => 7, 'columnas' => 3]);
        Lineas::create(['codigo' => 'M8', 'capacidad' => 9, 'posicion' => 8, 'columnas' => 3]);
        Lineas::create(['codigo' => 'M9', 'capacidad' => 9, 'posicion' => 9, 'columnas' => 3]);
        Lineas::create(['codigo' => 'M10', 'capacidad' => 9, 'posicion' => 10, 'columnas' => 3]);
        Lineas::create(['codigo' => 'M11', 'capacidad' => 16, 'posicion' => 11, 'columnas' => 4]);
    }
}
