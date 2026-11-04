<?php

namespace Database\Seeders;

use App\Models\Proveedores;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProveedoresSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        Proveedores::create(['nombre' => 'SEIREN', 'interfaz_barra' => '']);
        Proveedores::create(['nombre' => 'AUNDE', 'interfaz_barra' => '']);
        Proveedores::create(['nombre' => 'LAMITEX', 'interfaz_barra' => '']);
        Proveedores::create(['nombre' => 'OBER', 'interfaz_barra' => '']);
        Proveedores::create(['nombre' => 'SAGE', 'interfaz_barra' => '']);
        Proveedores::create(['nombre' => 'SANSUY', 'interfaz_barra' => '']);
        Proveedores::create(['nombre' => 'TTA', 'interfaz_barra' => '']);
    }
}
