<?php

namespace Database\Seeders;

use App\Models\Partes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PartesSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {

        Partes::create(['modelo_id' => 12,    'vehiculo_id' => 1, 'codigo' =>    '71071-0KP50',       'tipo_id' => 2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 12,    'vehiculo_id' => 1, 'codigo' =>    '71072-0KM50',       'tipo_id' => 2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 12,    'vehiculo_id' => 1, 'codigo' =>    '71073-0KP90',       'tipo_id' => 1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 12,    'vehiculo_id' => 1, 'codigo' =>    '71074-0KB00',       'tipo_id' => 1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 13, 'vehiculo_id' =>    2, 'codigo' =>    '71071-0KP71-C3', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 13, 'vehiculo_id' =>    2, 'codigo' =>    '71072-0KM62-C4', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 13, 'vehiculo_id' =>    2, 'codigo' =>    '71073-0KQ11-C4', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 13, 'vehiculo_id' =>    2, 'codigo' =>    '71074-0KB11-C5', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 14, 'vehiculo_id' =>    3, 'codigo' =>    '71071-0KP62-C4', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 14, 'vehiculo_id' =>    3, 'codigo' =>    '71072-0KM62-C4', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 14, 'vehiculo_id' =>    3, 'codigo' =>    '71073-0KQ01-C4', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 14, 'vehiculo_id' =>    3, 'codigo' =>    '71074-0KB11-C5', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 22, 'vehiculo_id' =>    4, 'codigo' =>    '71027-0K031-C3', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 22, 'vehiculo_id' =>    4, 'codigo' =>    '71072-0KX62-C1', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 22, 'vehiculo_id' =>    4, 'codigo' =>    '71074-0KM91-C3', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 22, 'vehiculo_id' =>    4, 'codigo' =>    '79011-0KJ82-C1', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 29, 'vehiculo_id' =>    5, 'codigo' =>    '71071-F0090', 'tipo_id' =>        2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 29, 'vehiculo_id' =>    5, 'codigo' =>    '71072-0KM50', 'tipo_id' =>        2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 29, 'vehiculo_id' =>    5, 'codigo' =>    '71073-0KT90', 'tipo_id' =>        1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 29, 'vehiculo_id' =>    5, 'codigo' =>    '71074-0KB00', 'tipo_id' =>        1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 5, 'vehiculo_id' =>    36, 'codigo' =>    '71027-0KD50-C0', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 5, 'vehiculo_id' =>    36, 'codigo' =>    '71071-F0D90-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 5, 'vehiculo_id' =>    36, 'codigo' =>    '71072-F0D10-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 5, 'vehiculo_id' =>    36, 'codigo' =>    '71074-F0040-C0', 'tipo_id' =>    1, 'lado_id' =>    3]);

        // Partes::create(['modelo_id' => 12, 'vehiculo_id' =>    10, 'codigo' =>    '71071-0KP50', 'tipo_id' =>        2, 'lado_id' =>    2, 'activo' => 0]);
        // Partes::create(['modelo_id' => 12, 'vehiculo_id' =>    10, 'codigo' =>    '71072-0KM50', 'tipo_id' =>        2, 'lado_id' =>    3, 'activo' => 0]);
        // Partes::create(['modelo_id' => 12, 'vehiculo_id' =>    10, 'codigo' =>    '71073-0KP90', 'tipo_id' =>        1, 'lado_id' =>    2, 'activo' => 0]);
        // Partes::create(['modelo_id' => 12, 'vehiculo_id' =>    10, 'codigo' =>    '71074-0KB00', 'tipo_id' =>        1, 'lado_id' =>    3, 'activo' => 0]);

        Partes::create(['modelo_id' => 52, 'vehiculo_id' =>    10, 'codigo' =>    '71909-X7A08-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' =>    10, 'codigo' =>    '71909-X7A08-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 52, 'vehiculo_id' =>    10, 'codigo' =>    '71951-X7A17-C0', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' =>    10, 'codigo' =>    '71951-X7A17-C0', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 52, 'vehiculo_id' =>    10, 'codigo' =>    '79011-0KD60-C1', 'tipo_id' =>    2, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' =>    10, 'codigo' =>    '79011-0KD60-C1', 'tipo_id' =>    2, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 52, 'vehiculo_id' =>    10, 'codigo' =>    '79013-0KJ11-C2', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' =>    10, 'codigo' =>    '79013-0KJ11-C2', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 13, 'vehiculo_id' =>    11, 'codigo' =>    '71071-0KP71-C3', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 13, 'vehiculo_id' =>    11, 'codigo' =>    '71072-0KM62-C4', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 13, 'vehiculo_id' =>    11, 'codigo' =>    '71073-0KQ11-C4', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 13, 'vehiculo_id' =>    11, 'codigo' =>    '71074-0KB11-C5', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    11, 'codigo' =>    '71909-X7A28-C6', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    11, 'codigo' =>    '71951-X7A48-C6', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    11, 'codigo' =>    '79011-0KD72-C1', 'tipo_id' =>    2, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    11, 'codigo' =>    '79013-0KE92-C0', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    12, 'codigo' =>    '71909-X7A28-C6', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    12, 'codigo' =>    '71951-X7A48-C6', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    12, 'codigo' =>    '79011-0KD72-C1', 'tipo_id' =>    2, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    12, 'codigo' =>    '79013-0KE92-C0', 'tipo_id' =>    1, 'lado_id' =>    4]);


        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    11, 'codigo' =>    '71951-X7A47', 'tipo_id' =>        4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 15, 'vehiculo_id' =>    12, 'codigo' =>    '71071-0KP61', 'tipo_id' =>        2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 15, 'vehiculo_id' =>    12, 'codigo' =>    '71072-0KM61', 'tipo_id' =>        2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 15, 'vehiculo_id' =>    12, 'codigo' =>    '71073-0KQ31', 'tipo_id' =>        1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 15, 'vehiculo_id' =>    12, 'codigo' =>    '71074-0KB31', 'tipo_id' =>        1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 15, 'vehiculo_id' =>    12, 'codigo' =>    '71921-X7A71', 'tipo_id' =>        4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    12, 'codigo' =>    '71951-X7A47', 'tipo_id' =>        4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 16, 'vehiculo_id' =>    13, 'codigo' =>    '71071-0KQ00', 'tipo_id' =>        2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 16, 'vehiculo_id' =>    13, 'codigo' =>    '71072-0KM90', 'tipo_id' =>        2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 16, 'vehiculo_id' =>    13, 'codigo' =>    '71073-0KQ60', 'tipo_id' =>        1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 16, 'vehiculo_id' =>    13, 'codigo' =>    '71074-0KB60', 'tipo_id' =>        1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 16, 'vehiculo_id' =>    13, 'codigo' =>    '71921-X7A18', 'tipo_id' =>        4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    13, 'codigo' =>    '71909-X7A23', 'tipo_id' =>        4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    13, 'codigo' =>    '71951-X7A42', 'tipo_id' =>        4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    13, 'codigo' =>    '72087-X7A15', 'tipo_id' =>        3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    13, 'codigo' =>    '79011-0KD80', 'tipo_id' =>        2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    13, 'codigo' =>    '79012-0KB50', 'tipo_id' =>        2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    13, 'codigo' =>    '79013-0KG01', 'tipo_id' =>        1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 17, 'vehiculo_id' =>    14, 'codigo' =>    '71071-0KQ30', 'tipo_id' =>        2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 17, 'vehiculo_id' =>    14, 'codigo' =>    '71072-0KN20', 'tipo_id' =>        2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 17, 'vehiculo_id' =>    14, 'codigo' =>    '71073-0KR21', 'tipo_id' =>        1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 17, 'vehiculo_id' =>    14, 'codigo' =>    '71074-0KC21', 'tipo_id' =>        1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 17, 'vehiculo_id' =>    14, 'codigo' =>    '71921-X7A21-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 17, 'vehiculo_id' =>    14, 'codigo' =>    '71921-X7A21-C2', 'tipo_id' =>    4, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    14, 'codigo' =>    '71909-X7A16-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    14, 'codigo' =>    '79011-0KD91-C2', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    14, 'codigo' =>    '79012-0KB61-C2', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    14, 'codigo' =>    '79013-0KF12-C9', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 18, 'vehiculo_id' =>    15, 'codigo' =>    '71071-0KY41-C1', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 18, 'vehiculo_id' =>    15, 'codigo' =>    '71072-0KV71-C1', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 18, 'vehiculo_id' =>    15, 'codigo' =>    '71072-YP681-C0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 18, 'vehiculo_id' =>    15, 'codigo' =>    '71072-yp681-c0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 18, 'vehiculo_id' =>    15, 'codigo' =>    '71073-0KR32-20', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 18, 'vehiculo_id' =>    15, 'codigo' =>    '71921-X7A21-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 18, 'vehiculo_id' =>    15, 'codigo' =>    '71921-X7A21-C2', 'tipo_id' =>    4, 'lado_id' =>    4]);

        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    14, 'codigo' =>    'A-71909-X7A16', 'tipo_id' => 4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    15, 'codigo' =>    '71909-X7A16-C0', 'tipo_id' => 4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    15, 'codigo' =>    '79011-0KD91-C2', 'tipo_id' => 2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    15, 'codigo' =>    '79012-0KB61-C2', 'tipo_id' => 2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    15, 'codigo' =>    '79013-0KF12-C9', 'tipo_id' => 1, 'lado_id' =>    4]);

        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    14, 'codigo' =>    '71909-X7A16', 'tipo_id' =>        4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    14, 'codigo' =>    '71951-X7A28', 'tipo_id' =>        4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    14, 'codigo' =>    '72087-X7A16', 'tipo_id' =>        3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    15, 'codigo' =>    '71909-X7A16', 'tipo_id' =>        4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    15, 'codigo' =>    '71951-X7A28', 'tipo_id' =>        4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    15, 'codigo' =>    '72087-X7A16', 'tipo_id' =>        3, 'lado_id' =>    1]);

        Partes::create(['modelo_id' => 49, 'vehiculo_id' =>    15, 'codigo' =>    'A-71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => 23, 'vehiculo_id' =>    16, 'codigo' => '71027-0K042-C4', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 23, 'vehiculo_id' =>    16, 'codigo' => '71072-0KX62-C1', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 23, 'vehiculo_id' =>    16, 'codigo' => '71074-0KN22-C5', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 23, 'vehiculo_id' =>    16, 'codigo' => '79011-0KJ82-C1', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    16, 'codigo' => '71909-X7A28-C6', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    16, 'codigo' => '71951-X7A48-C6', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    16, 'codigo' => '79011-0KD72-C1', 'tipo_id' =>    2, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    16, 'codigo' => '79013-0KE92-C0', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 4, 'vehiculo_id' => 42, 'codigo' => '71027-0KB00-C1', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 4, 'vehiculo_id' => 42, 'codigo' => '71071-F0B30-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 4, 'vehiculo_id' => 42, 'codigo' => '71072-F0B30-C1', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 4, 'vehiculo_id' => 42, 'codigo' => '71074-0KX80-C1', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 23, 'vehiculo_id' =>    16, 'codigo' => '71921-X7A71', 'tipo_id' => 4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 50, 'vehiculo_id' =>    16, 'codigo' => '71951-X7A47', 'tipo_id' => 4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 24, 'vehiculo_id' =>    17, 'codigo' => '71027-0K070', 'tipo_id' => 1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 24, 'vehiculo_id' =>    17, 'codigo' => '71072-0KX70', 'tipo_id' => 2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 24, 'vehiculo_id' =>    17, 'codigo' => '71074-0KN30', 'tipo_id' => 1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 24, 'vehiculo_id' =>    17, 'codigo' => '71921-X7A18', 'tipo_id' => 4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 24, 'vehiculo_id' =>    17, 'codigo' => '79011-0KJ90', 'tipo_id' => 2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    17, 'codigo' => '71909-X7A23', 'tipo_id' => 4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    17, 'codigo' => '71951-X7A42', 'tipo_id' => 4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    17, 'codigo' => '72087-X7A15', 'tipo_id' => 3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    17, 'codigo' => '79011-0KD80', 'tipo_id' => 2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    17, 'codigo' => '79012-0KB50', 'tipo_id' => 2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 51, 'vehiculo_id' =>    17, 'codigo' => '79013-0KG01', 'tipo_id' => 1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 4, 'vehiculo_id' => 42, 'codigo' => '71921-X7A18', 'tipo_id' => 4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 30, 'vehiculo_id' =>    18, 'codigo' => '71071-PHQ00', 'tipo_id' => 2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 30, 'vehiculo_id' =>    18, 'codigo' => '71072-PHM90', 'tipo_id' => 2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 30, 'vehiculo_id' =>    18, 'codigo' => '71073-PHQ60', 'tipo_id' => 1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 30, 'vehiculo_id' =>    18, 'codigo' => '71074-PHB60', 'tipo_id' => 1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 30, 'vehiculo_id' =>    18, 'codigo' => '71921-X7A18', 'tipo_id' => 4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 55, 'vehiculo_id' =>    18, 'codigo' => '71909-X7A23', 'tipo_id' => 4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 55, 'vehiculo_id' =>    18, 'codigo' => '71951-X7A42', 'tipo_id' => 4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 55, 'vehiculo_id' =>    18, 'codigo' => '72087-X7A15', 'tipo_id' => 3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 55, 'vehiculo_id' =>    18, 'codigo' => '79011-PHD80', 'tipo_id' => 2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 55, 'vehiculo_id' =>    18, 'codigo' => '79012-PHB50', 'tipo_id' => 2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 55, 'vehiculo_id' =>    18, 'codigo' => '79013-PHG00', 'tipo_id' => 1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 32, 'vehiculo_id' =>    20, 'codigo' => '71071-YP110', 'tipo_id' => 2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 32, 'vehiculo_id' =>    20, 'codigo' => '71072-YP110', 'tipo_id' => 2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 32, 'vehiculo_id' =>    20, 'codigo' => '71073-YP080', 'tipo_id' => 1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 32, 'vehiculo_id' =>    20, 'codigo' => '71074-YP080', 'tipo_id' => 1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 32, 'vehiculo_id' => 20, 'codigo' =>    '71921-X7A21-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 32, 'vehiculo_id' => 20, 'codigo' =>    '71921-X7A21-C2', 'tipo_id' =>    4, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 56, 'vehiculo_id' => 20, 'codigo' =>    '71909-X7A16-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 1, 'vehiculo_id' => 39, 'codigo' =>    '71027-0KA42-C3', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 1, 'vehiculo_id' => 39, 'codigo' =>    '71071-F0A91-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 1, 'vehiculo_id' => 39, 'codigo' =>    '71072-F0821-C1', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 1, 'vehiculo_id' => 39, 'codigo' =>    '71074-0KW12-C1', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 56, 'vehiculo_id' =>    20, 'codigo' =>    '71909-X7A16', 'tipo_id' =>        4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 56, 'vehiculo_id' =>    20, 'codigo' =>    '71951-X7A28', 'tipo_id' =>        4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 56, 'vehiculo_id' =>    20, 'codigo' =>    '72087-X7A16', 'tipo_id' =>        3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 56, 'vehiculo_id' =>    20, 'codigo' =>    '79011-YP030', 'tipo_id' =>        2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 56, 'vehiculo_id' =>    20, 'codigo' =>    '79012-YP030', 'tipo_id' =>        2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 56, 'vehiculo_id' =>    20, 'codigo' =>    '79013-YP030', 'tipo_id' =>        1, 'lado_id' =>    4]);

        Partes::create(['modelo_id' => 56, 'vehiculo_id' =>    20, 'codigo' =>    'A-71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => NULL, 'vehiculo_id' => 32, 'codigo' =>    '71071-X7A22', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' => 32, 'codigo' =>    '71072-X7A21', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' => 32, 'codigo' =>    '71073-X7A05', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' => 32, 'codigo' =>    '71074-X7A06', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' => 32, 'codigo' =>    '71921-X7A68', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => 6, 'vehiculo_id' => 32, 'codigo' =>    '71921-X7A68', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => 6,    'vehiculo_id' => 32, 'codigo' =>    '71071-X7A22-D0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 6,    'vehiculo_id' => 32, 'codigo' =>    '71072-X7A21-D0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 6,    'vehiculo_id' => 32, 'codigo' =>    '71073-X7A05-D0', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 6,    'vehiculo_id' => 32, 'codigo' =>    '71074-X7A06-D0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 8,    'vehiculo_id' => 32, 'codigo' =>    '71071-YPA21-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 8,    'vehiculo_id' => 32, 'codigo' =>    '71072-YP681-C0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 8,    'vehiculo_id' => 32, 'codigo' =>    '71072-yp681-c0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 8,    'vehiculo_id' => 32, 'codigo' =>    '71073-YP670-C1', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 8,    'vehiculo_id' => 32, 'codigo' =>    '71074-YP690-C1', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 8,    'vehiculo_id' => 32, 'codigo' =>    '71921-X7A88-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    32, 'codigo' =>    '71909-X7A16-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    32, 'codigo' =>    'A-71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    32, 'codigo' =>    '71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    32, 'codigo' =>    '71951-X7A28', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    32, 'codigo' =>    '72087-X7A16', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    32, 'codigo' =>    '79011-X7A00', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    32, 'codigo' =>    '79012-X7A00', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    32, 'codigo' =>    '79013-X7A00', 'tipo_id' =>    1, 'lado_id' =>    4]);

        Partes::create(['modelo_id' => 57, 'vehiculo_id' =>    32, 'codigo' =>    '71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 57, 'vehiculo_id' =>    32, 'codigo' =>    '71951-X7A28', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 57, 'vehiculo_id' =>    32, 'codigo' =>    '72087-X7A16', 'tipo_id' =>    3, 'lado_id' =>    1]);

        Partes::create(['modelo_id' => 57, 'vehiculo_id' =>    32, 'codigo' =>    'A-71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 59, 'vehiculo_id' =>    32, 'codigo' =>    'A-71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => 57, 'vehiculo_id' =>    32, 'codigo' =>    '71909-X7A16-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 57, 'vehiculo_id' =>    32, 'codigo' =>    '79011-X7A00-D0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 57, 'vehiculo_id' =>    32, 'codigo' =>    '79012-X7A00-D0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 57, 'vehiculo_id' =>    32, 'codigo' =>    '79013-X7A00-D0', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 59, 'vehiculo_id' =>    32, 'codigo' =>    '71909-X7A16-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 59, 'vehiculo_id' =>    32, 'codigo' =>    '79011-YP370-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 59, 'vehiculo_id' =>    32, 'codigo' =>    '79012-YP390-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 59, 'vehiculo_id' =>    32, 'codigo' =>    '79013-YP320-C0', 'tipo_id' =>    1, 'lado_id' =>    4]);

        Partes::create(['modelo_id' => 59, 'vehiculo_id' =>    32, 'codigo' =>    '71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 59, 'vehiculo_id' =>    32, 'codigo' =>    '71951-X7A28', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 59, 'vehiculo_id' =>    32, 'codigo' =>    '72087-X7A16', 'tipo_id' =>    3, 'lado_id' =>    1]);

        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    33, 'codigo' =>    '71027-0KA51-C2', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    45, 'codigo' =>    '71027-0KA51-C2', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    33, 'codigo' =>    '71071-F0B00-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    45, 'codigo' =>    '71071-F0B00-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    33, 'codigo' =>    '71072-F0830-C1', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    45, 'codigo' =>    '71072-F0830-C1', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    33, 'codigo' =>    '71074-0KW21-C1', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    45, 'codigo' =>    '71074-0KW21-C1', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    33, 'codigo' =>    '71921-X7A21-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    45, 'codigo' =>    '71921-X7A21-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    33, 'codigo' =>    '71921-X7A21-C2', 'tipo_id' =>    4, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 31, 'vehiculo_id' =>    45, 'codigo' =>    '71921-X7A21-C2', 'tipo_id' =>    4, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    33, 'codigo' =>    '71909-X7A16-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    45, 'codigo' =>    '71909-X7A16-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    33, 'codigo' =>    '71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    45, 'codigo' =>    '71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    33, 'codigo' =>    '71951-X7A28', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    45, 'codigo' =>    '71951-X7A28', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    33, 'codigo' =>    '72087-X7A16', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    45, 'codigo' =>    '72087-X7A16', 'tipo_id' =>    3, 'lado_id' =>    1]);

        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    33, 'codigo' =>    '79011-0KQ20-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    45, 'codigo' =>    '79011-0KQ20-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    33, 'codigo' =>    '79012-0KK00-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    45, 'codigo' =>    '79012-0KK00-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    33, 'codigo' =>    '79013-0KP70-C2', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    45, 'codigo' =>    '79013-0KP70-C2', 'tipo_id' =>    1, 'lado_id' =>    4]);

        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    33, 'codigo' =>    'A-71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 54, 'vehiculo_id' =>    45, 'codigo' =>    'A-71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => 9, 'vehiculo_id' => 40, 'codigo' =>    '71071-YPC60-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 9, 'vehiculo_id' => 40, 'codigo' =>    '71072-YPD20-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 9, 'vehiculo_id' => 40, 'codigo' =>    '71073-YPC40-C0', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 9, 'vehiculo_id' => 40, 'codigo' =>    '71074-YPD90-C0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 9, 'vehiculo_id' => 40, 'codigo' =>    '71921-X7A21-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 9, 'vehiculo_id' => 40, 'codigo' =>    '71921-X7A21-C2', 'tipo_id' =>    4, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' => 40, 'codigo' =>    '71909-X7A16-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' => 40, 'codigo' =>    '79011-0KQ20-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' => 40, 'codigo' =>    '79012-0KK00-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' => 40, 'codigo' =>    '79013-YP340-C1', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 45, 'vehiculo_id' => 41, 'codigo' =>    '71071-YPA30-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 45, 'vehiculo_id' => 41, 'codigo' =>    '71072-YP140-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 45, 'vehiculo_id' => 41, 'codigo' =>    '71073-YPA20-C0', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 45, 'vehiculo_id' => 41, 'codigo' =>    '71074-YPA40-C0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 45, 'vehiculo_id' => 41, 'codigo' =>    '71921-X7A21-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 45, 'vehiculo_id' => 41, 'codigo' =>    '71921-X7A21-C2', 'tipo_id' =>    4, 'lado_id' =>    4]);

        Partes::create(['modelo_id' => 60, 'vehiculo_id' =>    40, 'codigo' =>    '71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' =>    40, 'codigo' =>    '71951-X7A28', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' =>    40, 'codigo' =>    '72087-X7A16', 'tipo_id' =>    3, 'lado_id' =>    1]);

        Partes::create(['modelo_id' => 60, 'vehiculo_id' =>    40, 'codigo' =>    'A-71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' =>    41, 'codigo' =>    'A-71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => 60, 'vehiculo_id' =>    41, 'codigo' =>    '71909-X7A16', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' =>    41, 'codigo' =>    '71951-X7A28', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' =>    41, 'codigo' =>    '72087-X7A16', 'tipo_id' =>    3, 'lado_id' =>    1]);

        Partes::create(['modelo_id' => 60, 'vehiculo_id' => 41, 'codigo' =>    '71909-X7A16-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' => 41, 'codigo' =>    '79011-0KQ20-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' => 41, 'codigo' =>    '79012-0KK00-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 60, 'vehiculo_id' => 41, 'codigo' =>    '79013-YP340-C1', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 5, 'vehiculo_id' => 37, 'codigo' =>    '71027-0KD50-C0', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 5, 'vehiculo_id' => 46, 'codigo' =>    '71027-0KD50-C0', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 5, 'vehiculo_id' => 37, 'codigo' =>    '71071-F0D90-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 5, 'vehiculo_id' => 46, 'codigo' =>    '71071-F0D90-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 5, 'vehiculo_id' => 37, 'codigo' =>    '71072-F0D10-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 5, 'vehiculo_id' => 46, 'codigo' =>    '71072-F0D10-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 5, 'vehiculo_id' => 37, 'codigo' =>    '71074-F0040-C0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 5, 'vehiculo_id' => 46, 'codigo' =>    '71074-F0040-C0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 52, 'vehiculo_id' => 37, 'codigo' =>    '71909-X7A08-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 52, 'vehiculo_id' => 46, 'codigo' =>    '71909-X7A08-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' => 37, 'codigo' =>    '71909-X7A08-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' => 46, 'codigo' =>    '71909-X7A08-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 52, 'vehiculo_id' => 37, 'codigo' =>    '71951-X7A17-C0', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 52, 'vehiculo_id' => 46, 'codigo' =>    '71951-X7A17-C0', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' => 37, 'codigo' =>    '71951-X7A17-C0', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' => 46, 'codigo' =>    '71951-X7A17-C0', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 52, 'vehiculo_id' => 37, 'codigo' =>    '79011-0KD60-C1', 'tipo_id' =>    2, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 52, 'vehiculo_id' => 46, 'codigo' =>    '79011-0KD60-C1', 'tipo_id' =>    2, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' => 37, 'codigo' =>    '79011-0KD60-C1', 'tipo_id' =>    2, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' => 46, 'codigo' =>    '79011-0KD60-C1', 'tipo_id' =>    2, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 52, 'vehiculo_id' => 37, 'codigo' =>    '79013-0KJ11-C2', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 52, 'vehiculo_id' => 46, 'codigo' =>    '79013-0KJ11-C2', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' => 37, 'codigo' =>    '79013-0KJ11-C2', 'tipo_id' =>    1, 'lado_id' =>    4]);
        Partes::create(['modelo_id' => 53, 'vehiculo_id' => 46, 'codigo' =>    '79013-0KJ11-C2', 'tipo_id' =>    1, 'lado_id' =>    4]);

        Partes::create(['modelo_id' => 41, 'vehiculo_id' =>    6, 'codigo' =>    '71981-X7A21', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 41, 'vehiculo_id' =>    6, 'codigo' =>    '79021-0K710', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 41, 'vehiculo_id' =>    6, 'codigo' =>    '79022-0K710', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 41, 'vehiculo_id' =>    6, 'codigo' =>    '79023-0K680', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 41, 'vehiculo_id' =>    6, 'codigo' =>    '79024-0K640', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 40, 'vehiculo_id' =>    7, 'codigo' =>    '71981-X7A19', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 40, 'vehiculo_id' =>    7, 'codigo' =>    '79021-0K690', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 40, 'vehiculo_id' =>    7, 'codigo' =>    '79022-0K690', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 40, 'vehiculo_id' =>    7, 'codigo' =>    '79023-0K660', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 40, 'vehiculo_id' =>    7, 'codigo' =>    '79024-0K620', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 42, 'vehiculo_id' =>    8, 'codigo' =>    '71981-X7A19-A1', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 42, 'vehiculo_id' =>    8, 'codigo' =>    '79021-0K340-A2', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 42, 'vehiculo_id' =>    8, 'codigo' =>    '79022-0K340-A2', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 42, 'vehiculo_id' =>    8, 'codigo' =>    '79023-0K290-A2', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 42, 'vehiculo_id' =>    8, 'codigo' =>    '79024-0K290-A2', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 40, 'vehiculo_id' =>    9, 'codigo' =>    '71981-X7A19', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 40, 'vehiculo_id' =>    9, 'codigo' =>    '79021-0K690', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 40, 'vehiculo_id' =>    9, 'codigo' =>    '79022-0K690', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 40, 'vehiculo_id' =>    9, 'codigo' =>    '79023-0K660', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 40, 'vehiculo_id' =>    9, 'codigo' =>    '79024-0K620', 'tipo_id' =>    1, 'lado_id' =>    3]);

        // Partes::create(['modelo_id' => 43, 'vehiculo_id' =>    47, 'codigo' =>    '71981-X7A19-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        // Partes::create(['modelo_id' => 43, 'vehiculo_id' =>    47, 'codigo' =>    '79021-0K690-C1', 'tipo_id' =>    2, 'lado_id' =>    2]);
        // Partes::create(['modelo_id' => 43, 'vehiculo_id' =>    47, 'codigo' =>    '79022-0K690-C1', 'tipo_id' =>    2, 'lado_id' =>    3]);
        // Partes::create(['modelo_id' => 43, 'vehiculo_id' =>    47, 'codigo' =>    '79023-0K660-C1', 'tipo_id' =>    1, 'lado_id' =>    2]);
        // Partes::create(['modelo_id' => 43, 'vehiculo_id' =>    47, 'codigo' =>    '79024-0K620-C1', 'tipo_id' =>    1, 'lado_id' =>    3]);
        // Partes::create(['modelo_id' => 44, 'vehiculo_id' =>    48, 'codigo' =>    '71981-X7A21-C1', 'tipo_id' =>    4, 'lado_id' =>    5]);
        // Partes::create(['modelo_id' => 44, 'vehiculo_id' =>    48, 'codigo' =>    '79021-0K710-C2', 'tipo_id' =>    2, 'lado_id' =>    2]);
        // Partes::create(['modelo_id' => 44, 'vehiculo_id' =>    48, 'codigo' =>    '79022-0K710-C2', 'tipo_id' =>    2, 'lado_id' =>    3]);
        // Partes::create(['modelo_id' => 44, 'vehiculo_id' =>    48, 'codigo' =>    '79023-0K680-C2', 'tipo_id' =>    1, 'lado_id' =>    2]);
        // Partes::create(['modelo_id' => 44, 'vehiculo_id' =>    48, 'codigo' =>    '79024-0K640-C2', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 20, 'vehiculo_id' =>    21, 'codigo' =>    '71071-0KY50', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 20, 'vehiculo_id' =>    21, 'codigo' =>    '71072-0KV80', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 20, 'vehiculo_id' =>    21, 'codigo' =>    '71073-0KR10', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 20, 'vehiculo_id' =>    21, 'codigo' =>    '71074-0KC10', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 20, 'vehiculo_id' =>    21, 'codigo' =>    '71921-X7A20', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    21, 'codigo' =>    '71952-X7A07', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    21, 'codigo' =>    '71981-X7A22', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    21, 'codigo' =>    '72087-X7A18', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    21, 'codigo' =>    '79011-0KC20', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    21, 'codigo' =>    '79012-0KA40', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    21, 'codigo' =>    '79013-0KC90', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    21, 'codigo' =>    '79014-0K810', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 19, 'vehiculo_id' =>    22, 'codigo' =>    '71071-0KY60', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 19, 'vehiculo_id' =>    22, 'codigo' =>    '71072-0KV90', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 19, 'vehiculo_id' =>    22, 'codigo' =>    '71073-0KR51', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 19, 'vehiculo_id' =>    22, 'codigo' =>    '71074-0KC51', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 19, 'vehiculo_id' =>    22, 'codigo' =>    '71921-X7A22', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    22, 'codigo' =>    '71952-X7A08', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    22, 'codigo' =>    '71981-X7A23', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    22, 'codigo' =>    '72087-X7A19', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    22, 'codigo' =>    '79011-0KC32', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    22, 'codigo' =>    '79012-0KA62', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    22, 'codigo' =>    '79013-0KD02', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    22, 'codigo' =>    '79014-0K832', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 10, 'vehiculo_id' =>    23, 'codigo' =>    '71027-0K170', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 10, 'vehiculo_id' =>    23, 'codigo' =>    '71072-0KY60', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 10, 'vehiculo_id' =>    23, 'codigo' =>    '71074-0KP50', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 10, 'vehiculo_id' =>    23, 'codigo' =>    '71921-X7A20', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 10, 'vehiculo_id' =>    23, 'codigo' =>    '79011-0KK90', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    23, 'codigo' =>    '71952-X7A07', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    23, 'codigo' =>    '71981-X7A22', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    23, 'codigo' =>    '72087-X7A18', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    23, 'codigo' =>    '79011-0KC20', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    23, 'codigo' =>    '79012-0KA40', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    23, 'codigo' =>    '79013-0KC90', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    23, 'codigo' =>    '79014-0K810', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 11, 'vehiculo_id' =>    24, 'codigo' =>    '71027-0K180', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 11, 'vehiculo_id' =>    24, 'codigo' =>    '71072-0KY70', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 11, 'vehiculo_id' =>    24, 'codigo' =>    '71074-0KP60', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 11, 'vehiculo_id' =>    24, 'codigo' =>    '71921-X7A22', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 11, 'vehiculo_id' =>    24, 'codigo' =>    '79011-0KL00', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    24, 'codigo' =>    '71952-X7A08', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    24, 'codigo' =>    '71981-X7A23', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    24, 'codigo' =>    '72087-X7A19', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    24, 'codigo' =>    '79011-0KC32', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    24, 'codigo' =>    '79012-0KA62', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    24, 'codigo' =>    '79013-0KD02', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    24, 'codigo' =>    '79014-0K832', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    25, 'codigo' =>    '71027-0K270', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    25, 'codigo' =>    '71071-F0160', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    25, 'codigo' =>    '71072-F0110', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    25, 'codigo' =>    '71074-0KQ70', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    25, 'codigo' =>    '71921-X7A20', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    25, 'codigo' =>    '71952-X7A07', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    25, 'codigo' =>    '71981-X7A22', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    25, 'codigo' =>    '72087-X7A18', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    25, 'codigo' =>    '79011-0KC20', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    25, 'codigo' =>    '79012-0KA40', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    25, 'codigo' =>    '79013-0KC90', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    25, 'codigo' =>    '79014-0K810', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    26, 'codigo' =>    '71027-0K280', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    26, 'codigo' =>    '71071-F0170', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    26, 'codigo' =>    '71072-F0120', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    26, 'codigo' =>    '71074-0KQ80', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    26, 'codigo' =>    '71921-X7A20', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    26, 'codigo' =>    '71952-X7A07', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    26, 'codigo' =>    '71981-X7A22', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    26, 'codigo' =>    '72087-X7A18', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    26, 'codigo' =>    '79011-0KC20', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    26, 'codigo' =>    '79012-0KA40', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    26, 'codigo' =>    '79013-0KC90', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 35, 'vehiculo_id' =>    26, 'codigo' =>    '79014-0K810', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 2, 'vehiculo_id' =>    44, 'codigo' =>    '71027-0K262-C2', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 2, 'vehiculo_id' =>    44, 'codigo' =>    '71071-F0B61-C1', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 2, 'vehiculo_id' =>    44, 'codigo' =>    '71072-F0101-C1', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 2, 'vehiculo_id' =>    44, 'codigo' =>    '71074-0KQ62-C2', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 2, 'vehiculo_id' =>    44, 'codigo' =>    '71921-X7A22-C1', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    44, 'codigo' =>    '71952-X7A08-C1', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    44, 'codigo' =>    '71981-X7A23-C0', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    44, 'codigo' =>    '72087-X7A19-C1', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    44, 'codigo' =>    '79011-0KC33-C2', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    44, 'codigo' =>    '79012-0KA63-C2', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    44, 'codigo' =>    '79013-0KD04-C3', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    44, 'codigo' =>    '79014-0K834-C3', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    27, 'codigo' =>    '71027-0K250', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    27, 'codigo' =>    '71071-F0140', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    27, 'codigo' =>    '71072-F0090', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    27, 'codigo' =>    '71074-0KQ50', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => NULL, 'vehiculo_id' =>    27, 'codigo' =>    '71921-X7A22', 'tipo_id' =>    4, 'lado_id' =>    5]);

        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    27, 'codigo' =>    '71952-X7A08', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    27, 'codigo' =>    '71981-X7A23', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    27, 'codigo' =>    '72087-X7A19', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    27, 'codigo' =>    '79011-0KC32', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    27, 'codigo' =>    '79012-0KA62', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    27, 'codigo' =>    '79013-0KD02', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    27, 'codigo' =>    '79014-0K832', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 27, 'vehiculo_id' =>    28, 'codigo' =>    '71027-0K261', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 27, 'vehiculo_id' =>    28, 'codigo' =>    '71071-F0150', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 27, 'vehiculo_id' =>    28, 'codigo' =>    '71072-F0100', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 27, 'vehiculo_id' =>    28, 'codigo' =>    '71074-0KQ61', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 27, 'vehiculo_id' =>    28, 'codigo' =>    '71921-X7A22', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    28, 'codigo' =>    '71952-X7A08', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    28, 'codigo' =>    '71981-X7A23', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    28, 'codigo' =>    '72087-X7A19', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    28, 'codigo' =>    '79011-0KC32', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    28, 'codigo' =>    '79012-0KA62', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    28, 'codigo' =>    '79013-0KD02', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    28, 'codigo' =>    '79014-0K832', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 28, 'vehiculo_id' =>    29, 'codigo' =>    '71071-YP790-A0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 28, 'vehiculo_id' =>    29, 'codigo' =>    '71072-YP760-A0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 28, 'vehiculo_id' =>    29, 'codigo' =>    '71073-YP750-A0', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 28, 'vehiculo_id' =>    29, 'codigo' =>    '71074-YP770-A0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 28, 'vehiculo_id' =>    29, 'codigo' =>    '71921-X7A22-A0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    29, 'codigo' =>    '71952-X7A08-A2', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    29, 'codigo' =>    '71981-X7A23-A1', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    29, 'codigo' =>    '72087-X7A19-A1', 'tipo_id' =>    3, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    29, 'codigo' =>    '79011-0KL80-A5', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    29, 'codigo' =>    '79012-0KF40-A5', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    29, 'codigo' =>    '79013-0KG10-A4', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    29, 'codigo' =>    '79014-0KC80-A4', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    30, 'codigo' =>    '71952-X7A08-A2', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    30, 'codigo' =>    '71981-X7A23-A1', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    30, 'codigo' =>    '72087-X7A19-A1', 'tipo_id' =>    3, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    30, 'codigo' =>    '79011-0KL80-A5', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    30, 'codigo' =>    '79012-0KF40-A5', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    30, 'codigo' =>    '79013-0KG10-A4', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 37, 'vehiculo_id' =>    30, 'codigo' =>    '79014-0KC80-A4', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 25, 'vehiculo_id' =>    31, 'codigo' =>    '71071-F0180', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 25, 'vehiculo_id' =>    31, 'codigo' =>    '71072-0KV90', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 25, 'vehiculo_id' =>    31, 'codigo' =>    '71073-0KR51', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 25, 'vehiculo_id' =>    31, 'codigo' =>    '71074-0KC51', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 25, 'vehiculo_id' =>    31, 'codigo' =>    '71921-X7A22', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    31, 'codigo' =>    '71952-X7A08', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    31, 'codigo' =>    '71981-X7A23', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    31, 'codigo' =>    '72087-X7A19', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    31, 'codigo' =>    '79011-0KC32', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    31, 'codigo' =>    '79012-0KA62', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    31, 'codigo' =>    '79013-0KD02', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 33, 'vehiculo_id' =>    31, 'codigo' =>    '79014-0K832', 'tipo_id' =>    1, 'lado_id' =>    3]);

        Partes::create(['modelo_id' => 7, 'vehiculo_id' => 38, 'codigo' => '71071-YP711-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 7, 'vehiculo_id' => 38, 'codigo' => '71072-YP681-C0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 7, 'vehiculo_id' => 38, 'codigo' => '71072-yp681-c0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 7, 'vehiculo_id' => 38, 'codigo' => '71073-YP670-C0', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 7, 'vehiculo_id' => 38, 'codigo' => '71074-YP690-C0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 7, 'vehiculo_id' => 38, 'codigo' => '71921-X7A88-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 39, 'vehiculo_id' =>    38, 'codigo' => '71952-X7A08-C1', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 39, 'vehiculo_id' =>    38, 'codigo' => '71981-X7A23-C0', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 39, 'vehiculo_id' =>    38, 'codigo' => '72087-X7A19-C1', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 39, 'vehiculo_id' =>    38, 'codigo' => '79011-YP240-C1', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 39, 'vehiculo_id' =>    38, 'codigo' => '79012-YP230-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 39, 'vehiculo_id' =>    38, 'codigo' => '79013-YP200-C1', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 39, 'vehiculo_id' =>    38, 'codigo' => '79014-YP090-C1', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 3, 'vehiculo_id' => 34, 'codigo' => '71027-0KB10-C0', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 3, 'vehiculo_id' => 34, 'codigo' => '71071-F0A80-C1', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 3, 'vehiculo_id' => 34, 'codigo' => '71072-F0A40-C1', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 3, 'vehiculo_id' => 34, 'codigo' => '71074-0KE20-C0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 3, 'vehiculo_id' => 34, 'codigo' => '71921-X7A22-C1', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 38, 'vehiculo_id' =>    34, 'codigo' => '71952-X7A08-C1', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 38, 'vehiculo_id' =>    34, 'codigo' => '71981-X7A23-C0', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 38, 'vehiculo_id' =>    34, 'codigo' => '72087-X7A19-C1', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 38, 'vehiculo_id' =>    34, 'codigo' => '79011-0KQ01-C2', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 38, 'vehiculo_id' =>    34, 'codigo' => '79012-0KJ81-C3', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 38, 'vehiculo_id' =>    34, 'codigo' => '79013-0KP31-C4', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 38, 'vehiculo_id' =>    34, 'codigo' => '79014-0KE01-C4', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 21, 'vehiculo_id' =>    35, 'codigo' => '71071-0KY50-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 21, 'vehiculo_id' =>    35, 'codigo' => '71072-0KV80-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 21, 'vehiculo_id' =>    35, 'codigo' => '71073-0KR10-C0', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 21, 'vehiculo_id' =>    35, 'codigo' => '71074-0KC10-C0', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 21, 'vehiculo_id' =>    35, 'codigo' => '71921-X7A20-C0', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 36, 'vehiculo_id' =>    35, 'codigo' => '71952-X7A07-C1', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 36, 'vehiculo_id' =>    35, 'codigo' => '71981-X7A22-C1', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 36, 'vehiculo_id' =>    35, 'codigo' => '72087-X7A18-C0', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 36, 'vehiculo_id' =>    35, 'codigo' => '79011-0KC21-C3', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 36, 'vehiculo_id' =>    35, 'codigo' => '79012-0KA41-C3', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 36, 'vehiculo_id' =>    35, 'codigo' => '79013-0KC91-C3', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 36, 'vehiculo_id' =>    35, 'codigo' => '79014-0K811-C3', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 26, 'vehiculo_id' =>    43, 'codigo' => '71071-F0181-C0', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 26, 'vehiculo_id' =>    43, 'codigo' => '71072-0KV91-C0', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 26, 'vehiculo_id' =>    43, 'codigo' => '71073-0KR52-C2', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 26, 'vehiculo_id' =>    43, 'codigo' => '71074-0KC52-C2', 'tipo_id' =>    1, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 26, 'vehiculo_id' =>    43, 'codigo' => '71921-X7A22-C1', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    43, 'codigo' => '71952-X7A08-C1', 'tipo_id' =>    4, 'lado_id' =>    5]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    43, 'codigo' => '71981-X7A23-C0', 'tipo_id' =>    4, 'lado_id' =>    6]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    43, 'codigo' => '72087-X7A19-C1', 'tipo_id' =>    3, 'lado_id' =>    1]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    43, 'codigo' => '79011-0KC33-C2', 'tipo_id' =>    2, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    43, 'codigo' => '79012-0KA63-C2', 'tipo_id' =>    2, 'lado_id' =>    3]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    43, 'codigo' => '79013-0KD04-C3', 'tipo_id' =>    1, 'lado_id' =>    2]);
        Partes::create(['modelo_id' => 34, 'vehiculo_id' =>    43, 'codigo' => '79014-0K834-C3', 'tipo_id' =>    1, 'lado_id' =>    3]);
    }
}
