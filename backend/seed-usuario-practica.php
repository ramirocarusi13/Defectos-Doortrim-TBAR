<?php
/**
 * SEMILLA: usuario de capacitacion/practica (cod_autorizacion=9005).
 *
 * Idempotente: si ya existe (por cod_autorizacion) lo actualiza en vez de
 * duplicarlo. Cualquier defecto marcado por este usuario cae en modo PRACTICA
 * (ver EolDefectoController::store): no toca JITMS y queda fuera de los
 * reportes/dashboard de produccion.
 *
 *   php seed-usuario-practica.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$COD = '9005';

$user = User::updateOrCreate(
    ['cod_autorizacion' => $COD],
    [
        'name'         => 'QC PRACTICA',
        'email'        => 'qc.practica.9005@eol-tbar.local',
        'password'     => bcrypt(bin2hex(random_bytes(16))),
        'departamento' => 'CAPACITACION',
        'rol'          => '10',
        'activo'       => 1,
    ]
);

echo "Usuario de practica listo: id={$user->id} cod_autorizacion={$user->cod_autorizacion} departamento={$user->departamento} rol={$user->rol}\n";
