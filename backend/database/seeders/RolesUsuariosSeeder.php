<?php

namespace Database\Seeders;

use App\Http\Roles;
use App\Models\RolesUsuarios;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesUsuariosSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        RolesUsuarios::create(['user_id' => 1, 'rol_id' => Roles::DESARROLLO]); //DEV
        RolesUsuarios::create(['user_id' => 2, 'rol_id' => Roles::LOGISTICA]); //LOGISTICA
        RolesUsuarios::create(['user_id' => 3, 'rol_id' => Roles::CALIDAD]); //CALIDAD
        RolesUsuarios::create(['user_id' => 4, 'rol_id' => Roles::INVENTARIO]); //INVENTARIO
        RolesUsuarios::create(['user_id' => 5, 'rol_id' => Roles::INVENTARIO]); //INVENTARIO
    }
}
