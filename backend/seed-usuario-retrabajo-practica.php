<?php
/**
 * SEMILLA: usuario de practica de PRODUCCION (retrabajador) para probar la tablet
 * DOORTRIM DEFECTOS del lado de produccion (cod_autorizacion=9006). El de Calidad
 * es el 9005 (seed-usuario-practica.php).
 *
 * Idempotente: si ya existe (por cod_autorizacion) lo actualiza.
 *
 *   php seed-usuario-retrabajo-practica.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$COD = '9006';

$user = User::updateOrCreate(
    ['cod_autorizacion' => $COD],
    [
        'name'         => 'RETRABAJO PRACTICA',
        'email'        => 'retrabajo.practica.9006@eol-tbar.local',
        'password'     => bcrypt(bin2hex(random_bytes(16))),
        'departamento' => 'PRODUCCION',
        'rol'          => '10',
        'activo'       => 1,
    ]
);

echo "Usuario de practica (produccion) listo: id={$user->id} cod_autorizacion={$user->cod_autorizacion} departamento={$user->departamento}\n";
