<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {

        DB::table('users')->insert([
            'name'      => "dev",
            'email'     => 'cristian.torres@sewtech.com',
            'password'  => Hash::make('1'),
        ]);

        DB::table('users')->insert([
            'name'      => "logistica",
            'email'     => 'logistica@sewtech.com',
            'password'  => Hash::make('1'),
        ]);

        DB::table('users')->insert([
            'name'      => "calidad",
            'email'     => 'calidad@sewtech.com',
            'password'  => Hash::make('1'),
        ]);

        DB::table('users')->insert([
            'name'      => "inventario",
            'email'     => 'inventario@sewtech.com',
            'password'  => Hash::make('1'),
        ]);

        DB::table('users')->insert([
            'name'      => "cristian",
            'email'     => 'cristian@sewtech.com',
            'password'  => Hash::make('1'),
        ]);
    }
}
