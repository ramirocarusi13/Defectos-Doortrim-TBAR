<?php

namespace Database\Seeders;

use App\Models\Materiales;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MaterialesSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        Materiales::create(['material' => 'HI-FAB']);
        Materiales::create(['material' => 'LOW-FAB']);
        Materiales::create(['material' => 'FAB']);
        Materiales::create(['material' => 'PVC']);
        Materiales::create(['material' => 'LEATHER']);
    }
}
