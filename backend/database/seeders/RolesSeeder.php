<?php

namespace Database\Seeders;

use App\Models\Roles;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        Roles::create(['nombre' => 'DESARROLLO']);
        Roles::create(['nombre' => 'ADMINISTRADOR']);
        Roles::create(['nombre' => 'KANBAN']);
        Roles::create(['nombre' => 'BUFFER']);
        Roles::create(['nombre' => 'TIENDA']);
        Roles::create(['nombre' => 'SUBASSY']);
        Roles::create(['nombre' => 'COSTURA']);
        Roles::create(['nombre' => 'LOGISTICA']);
        Roles::create(['nombre' => 'CALIDAD']);
        Roles::create(['nombre' => 'IT']);
        Roles::create(['nombre' => 'INVENTARIO']);
    }
}
