<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;


class DbSeeder extends Seeder {

    public function run() {
        $this->call([
            UserSeeder::class,
            RolesSeeder::class,
            RolesUsuariosSeeder::class,
            DepositosSeeder::class,
            LineasSeeder::class,
            EstadosSeeder::class,
            ProveedoresSeeder::class,
            TipoVehiculoSeeder::class,
            MaterialesSeeder::class,
            ColoresSeeder::class,
            VehiculosSeeder::class,
            FilasSeeder::class,
            ModelosSeeder::class,
            TipoPartesSeeder::class,
            LadoPartesSeeder::class,
            // PartesSeeder::class,
            // PiezasSeeder::class
        ]);
    }
}
