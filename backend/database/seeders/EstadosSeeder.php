<?php

namespace Database\Seeders;

use App\Models\Estados;
use Illuminate\Database\Seeder;


class EstadosSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        Estados::create(['descripcion' => 'EN CORTE', 'color' => '']);
        Estados::create(['descripcion' => 'EN BUFFER', 'color' => '']);
        Estados::create(['descripcion' => 'SUB ASSY', 'color' => '']);
        Estados::create(['descripcion' => 'COSTURA', 'color' => '']);
        Estados::create(['descripcion' => 'CALIDAD', 'color' => '']);
        Estados::create(['descripcion' => 'FINALIZADO', 'color' => '']);
        Estados::create(['descripcion' => 'GENERADO', 'color' => '']);
        Estados::create(['descripcion' => 'DEFECTO', 'color' => '']);
        Estados::create(['descripcion' => 'RECHAZADO', 'color' => '']);
        Estados::create(['descripcion' => 'APROBADO', 'color' => '']);
        Estados::create(['descripcion' => 'REVISION', 'color' => '']);
        Estados::create(['descripcion' => 'PLANIFICADO', 'color' => '']);
        Estados::create(['descripcion' => 'EN REVISION CORTE', 'color' => '']);
        Estados::create(['descripcion' => 'EN PLANIFICACION', 'color' => '']);
        Estados::create(['descripcion' => 'EN BUFFER CORTE', 'color' => '']);
    }
}
