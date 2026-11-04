<?php
/**
 * PRUEBA LOCAL del flujo de la tablet de door trims (marcar + cambiar en un paso),
 * contra la DB local. Cubre los requisitos del reemplazo:
 *
 *   1) GARNISH ok: marca + cambio en una transaccion -> defecto CAMBIADO, door, piezas
 *   2) reemplazo de otro MODELO -> rechazado, sin defectos ni cambios guardados
 *   3) reemplazo del otro LADO -> rechazado, sin efectos
 *   4) pieza elegida (ARM_REST) distinta del reemplazo (GARNISH) -> rechazado
 *   5) reemplazo todavia curando -> rechazado
 *   6) panel viejo cargado como DOOR se cambia por un PANEL nuevo (equivalentes)
 *   7) reintento con el mismo client_uuid -> no duplica defectos ni cambios
 *   8) pieza que sale sin registrar -> alta automatica con el tipo elegido
 *
 * Al final borra todo lo que creo.
 *
 *   php doortrim-local-test.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EolDoor;
use App\Models\EolDefecto;
use App\Models\EolDefectoEvento;
use App\Models\EolPieza;
use App\Models\EolPiezaEvento;
use App\Models\User;
use App\Services\DoorService;

$fallos = 0;
function chequear(&$fallos, string $desc, bool $cond) {
    echo 'PASO ' . $desc . '  [' . ($cond ? "\e[32mOK\e[0m" : "\e[31mFALLA\e[0m") . "]\n";
    if (!$cond) $fallos++;
}

echo "=============================================================\n";
echo "  PRUEBA LOCAL TABLET DOOR TRIMS (marcar + cambiar)\n";
echo "=============================================================\n";

$P = 'TEST-DT-261030-';
$SET     = $P . 'SET-1';
$MODELO  = 'DT-MODELO-A';
$user    = User::query()->first();

$qrsPiezas = [];
$qrsSets   = [$SET, $P . 'SET-2', $P . 'SET-3', $P . 'SET-4', $P . 'SET-5', $P . 'SET-6', $P . 'SET-7', $P . 'SET-8', $P . 'SET-9', $P . 'SET-10', $P . 'SET-11'];

function limpiar(array $qrsPiezas, array $qrsSets): void {
    $piezaIds = EolPieza::whereIn('pieza_qr', $qrsPiezas)->pluck('id');
    EolPiezaEvento::whereIn('pieza_id', $piezaIds)->delete();
    EolDoor::whereIn('qr', $qrsSets)->delete();
    $defIds = EolDefecto::whereIn('qr', $qrsSets)->pluck('id');
    EolDefectoEvento::whereIn('eol_defecto_id', $defIds)->delete();
    EolDefecto::whereIn('id', $defIds)->delete();
    EolPieza::whereIn('pieza_qr', $qrsPiezas)->delete();
}

function pieza(array &$qrs, string $qr, string $tipo, string $estado, array $extra = []): EolPieza {
    $qrs[] = $qr;
    return EolPieza::create(array_merge([
        'pieza_qr' => $qr, 'tipo_pieza' => $tipo, 'modelo' => $GLOBALS['MODELO'], 'lado' => 'LH',
        'estado' => $estado, 'linea' => 'DT-TEST', 'buffer_id' => 'buf-dt', 'buffer_nombre' => 'Buffer DT',
        'alta_at' => now(), 'ultimo_evento_at' => now(),
    ], $extra));
}

function payload(string $set, string $tipo, string $sale, string $entra, array $extra = []): array {
    return array_merge([
        'qr' => $set, 'tipo_pieza' => $tipo, 'modelo' => $GLOBALS['MODELO'], 'lado' => 'LH',
        'pieza_defectuosa_qr' => $sale, 'pieza_reemplazo_qr' => $entra,
        'buffer_id' => 'buf-dt', 'buffer_nombre' => 'Buffer DT', 'user' => $GLOBALS['user']?->id,
        'puesto_id' => 'p-dt', 'puesto_nombre' => 'Puesto DT',
        'circles' => [[
            'x' => 0.4, 'y' => 0.6, 'color' => 'EF4444', 'imageId' => 'v1', 'viewName' => 'Frente',
            'falla' => ['id' => 99, 'codigo' => 'RAY', 'nombre' => 'Rayado'], 'cantidad' => 1,
        ]],
    ], $extra);
}

/** Corre marcarYCambiar esperando un error; devuelve el mensaje (o null si no fallo). */
function esperarError(array $payload): ?string {
    try {
        DoorService::marcarYCambiar($payload);
        return null;
    } catch (\RuntimeException $e) {
        return $e->getMessage();
    }
}

function sinEfectos(string $set): bool {
    return EolDefecto::where('qr', $set)->count() === 0 && EolDoor::where('qr', $set)->count() === 0;
}

$todosQr = [];
foreach (['G-SALE', 'G-ENTRA', 'X-MOD', 'X-LADO', 'A-SALE', 'CURA', 'P-OLD', 'P-NEW', 'I-SALE', 'I-ENTRA', 'N-SALE', 'N-ENTRA'] as $s) {
    $todosQr[] = $P . $s;
}
limpiar($todosQr, $qrsSets);

try {
    // 1) GARNISH ok
    echo "\n-- 1) garnish: marcar + cambiar --\n";
    $gSale  = pieza($qrsPiezas, $P . 'G-SALE', 'GARNISH', 'EN_ASIENTO');
    $gEntra = pieza($qrsPiezas, $P . 'G-ENTRA', 'GARNISH', 'DISPONIBLE');
    $r = DoorService::marcarYCambiar(payload($SET, 'GARNISH', $gSale->pieza_qr, $gEntra->pieza_qr));
    $def = EolDefecto::where('qr', $SET)->first();
    chequear($fallos, '1 · se creo el defecto flujo DOOR', $def && $def->flujo === 'DOOR');
    chequear($fallos, '1 · defecto CAMBIADO y ligado al cambio', $def && $def->estado === 'CAMBIADO' && (int) $def->door_id === (int) $r['door']->id);
    chequear($fallos, '1 · cambio guardado con tipo GARNISH', $r['door']->tipo_pieza === 'GARNISH' && $r['door']->qr === $SET);
    chequear($fallos, '1 · la que sale queda DEFECTUOSA en el buffer', EolPieza::find($gSale->id)->estado === 'DEFECTUOSA');
    chequear($fallos, '1 · la que entra queda EN_ASIENTO', EolPieza::find($gEntra->id)->estado === 'EN_ASIENTO');
    chequear($fallos, '1 · marca guardada con posicion y vista', (float) $def->pos_x === 0.4 && $def->vista_id === 'v1');
    chequear($fallos, '1 · eventos ABIERTO y CAMBIADO del defecto',
        EolDefectoEvento::where('eol_defecto_id', $def->id)->pluck('evento')->sort()->values()->all() === ['ABIERTO', 'CAMBIADO']);

    // 2) otro modelo
    echo "\n-- 2) reemplazo de otro modelo --\n";
    $aSale = pieza($qrsPiezas, $P . 'A-SALE', 'ARM_REST', 'EN_ASIENTO');
    $xMod  = pieza($qrsPiezas, $P . 'X-MOD', 'ARM_REST', 'DISPONIBLE', ['modelo' => 'DT-MODELO-B']);
    $msg = esperarError(payload($P . 'SET-2', 'ARM_REST', $aSale->pieza_qr, $xMod->pieza_qr));
    echo "   -> $msg\n";
    chequear($fallos, '2 · rechazado por modelo', $msg !== null && str_contains($msg, 'modelo'));
    chequear($fallos, '2 · sin defectos ni cambio guardados', sinEfectos($P . 'SET-2'));
    chequear($fallos, '2 · piezas sin tocar', EolPieza::find($aSale->id)->estado === 'EN_ASIENTO' && EolPieza::find($xMod->id)->estado === 'DISPONIBLE');

    // 3) otro lado
    echo "\n-- 3) reemplazo del otro lado --\n";
    $xLado = pieza($qrsPiezas, $P . 'X-LADO', 'ARM_REST', 'DISPONIBLE', ['lado' => 'RH']);
    $msg = esperarError(payload($P . 'SET-3', 'ARM_REST', $aSale->pieza_qr, $xLado->pieza_qr));
    echo "   -> $msg\n";
    chequear($fallos, '3 · rechazado por lado', $msg !== null && str_contains($msg, 'lado'));
    chequear($fallos, '3 · sin efectos', sinEfectos($P . 'SET-3'));

    // 3b) aunque el set diga DER (= RH), la pieza que sale es LH -> rechaza igual
    $msg = esperarError(payload($P . 'SET-3', 'ARM_REST', $aSale->pieza_qr, $xLado->pieza_qr, ['lado' => 'DER']));
    chequear($fallos, '3b · sigue rechazando: la que sale es LH aunque el set diga DER', $msg !== null && str_contains($msg, 'lado'));

    // 4) tipo elegido distinto al del reemplazo
    echo "\n-- 4) eligio ARM_REST pero escaneo un GARNISH --\n";
    $gOtro = pieza($qrsPiezas, $P . 'I-ENTRA', 'GARNISH', 'DISPONIBLE');
    $msg = esperarError(payload($P . 'SET-4', 'ARM_REST', $aSale->pieza_qr, $gOtro->pieza_qr));
    echo "   -> $msg\n";
    chequear($fallos, '4 · rechazado por tipo', $msg !== null && str_contains($msg, 'GARNISH'));
    chequear($fallos, '4 · sin efectos', sinEfectos($P . 'SET-4'));

    // 5) curando
    echo "\n-- 5) reemplazo todavia curando --\n";
    $cura = pieza($qrsPiezas, $P . 'CURA', 'ARM_REST', 'DISPONIBLE', ['disponible_desde' => now()->addHours(2)]);
    $msg = esperarError(payload($P . 'SET-5', 'ARM_REST', $aSale->pieza_qr, $cura->pieza_qr));
    echo "   -> $msg\n";
    chequear($fallos, '5 · rechazado por curado', $msg !== null && str_contains($msg, 'curando'));
    chequear($fallos, '5 · sin efectos', sinEfectos($P . 'SET-5'));

    // 6) DOOR viejo == PANEL
    echo "\n-- 6) panel viejo (DOOR) por panel nuevo --\n";
    $pOld = pieza($qrsPiezas, $P . 'P-OLD', 'DOOR', 'EN_ASIENTO');
    $pNew = pieza($qrsPiezas, $P . 'P-NEW', 'PANEL', 'DISPONIBLE');
    $msg = esperarError(payload($P . 'SET-6', 'PANEL', $pOld->pieza_qr, $pNew->pieza_qr));
    chequear($fallos, '6 · DOOR y PANEL se aceptan como el mismo tipo', $msg === null);

    // 7) idempotencia
    echo "\n-- 7) reintento con el mismo client_uuid --\n";
    $iSale  = pieza($qrsPiezas, $P . 'I-SALE', 'GARNISH', 'EN_ASIENTO');
    $gOtro->refresh();
    $uuid = 'dt-test-' . uniqid();
    $r1 = DoorService::marcarYCambiar(payload($P . 'SET-7', 'GARNISH', $iSale->pieza_qr, $gOtro->pieza_qr, ['client_uuid' => $uuid]));
    $r2 = DoorService::marcarYCambiar(payload($P . 'SET-7', 'GARNISH', $iSale->pieza_qr, $gOtro->pieza_qr, ['client_uuid' => $uuid]));
    chequear($fallos, '7 · segundo envio marcado como idempotente', $r2['idempotent'] === true && (int) $r2['door']->id === (int) $r1['door']->id);
    chequear($fallos, '7 · un solo defecto y un solo cambio', EolDefecto::where('qr', $P . 'SET-7')->count() === 1 && EolDoor::where('qr', $P . 'SET-7')->count() === 1);

    // 8) alta automatica de la que sale
    echo "\n-- 8) la pieza que sale no estaba registrada --\n";
    $qrsPiezas[] = $P . 'N-SALE';
    $nEntra = pieza($qrsPiezas, $P . 'N-ENTRA', 'ARM_REST', 'DISPONIBLE');
    // El door trim trae el numero de parte del PANEL: no tiene que frenar un ARM_REST.
    $r8 = DoorService::marcarYCambiar(payload($P . 'SET-8', 'ARM_REST', $P . 'N-SALE', $nEntra->pieza_qr, ['modelo' => '67620-PANEL']));
    $nSale = EolPieza::where('pieza_qr', $P . 'N-SALE')->first();
    chequear($fallos, '8 · alta automatica como ARM_REST DEFECTUOSA', $nSale && $nSale->tipo_pieza === 'ARM_REST' && $nSale->estado === 'DEFECTUOSA');
    chequear($fallos, '8 · cambio marcado con alta automatica', (bool) $r8['door']->alta_automatica_retirada);
    chequear($fallos, '8 · la que sale toma el numero de parte de la que entra', $nSale && $nSale->modelo === $MODELO);

    // 9) validaciones de entrada
    echo "\n-- 9) datos faltantes --\n";
    chequear($fallos, '9 · sin marcas -> rechazado', esperarError(payload($P . 'SET-8', 'ARM_REST', 'a', 'b', ['circles' => []])) !== null);
    chequear($fallos, '9 · tipo invalido -> rechazado', esperarError(payload($P . 'SET-8', 'ASIENTO', 'a', 'b')) !== null);

    // 10) Calidad manda el defecto a retrabajo (sin cambiar la pieza) y el
    //     retrabajador lo cierra desde su usuario
    echo "\n-- 10) mandar a retrabajo + retrabajador --\n";
    $stockAntes = EolPieza::where('estado', 'DISPONIBLE')->count();
    DoorService::marcarRetrabajo(payload($P . 'SET-9', 'PANEL', '', ''));
    $def10 = EolDefecto::where('qr', $P . 'SET-9')->first();
    chequear($fallos, '10 · queda PENDIENTE para el retrabajador, tipo PANEL', $def10 && $def10->estado === 'PENDIENTE' && $def10->tipo === 'PANEL');
    $retrabajador = User::query()->skip(1)->first() ?? $user;
    $cerrado = DoorService::retrabajar($def10->id, $retrabajador);
    chequear($fallos, '10 · retrabajador lo cierra: RETRABAJADO con su usuario',
        $cerrado->estado === 'RETRABAJADO' && (int) $cerrado->reparado_por === (int) $retrabajador->id && $cerrado->reparado_at !== null);
    chequear($fallos, '10 · eventos ABIERTO y RETRABAJADO',
        EolDefectoEvento::where('eol_defecto_id', $def10->id)->pluck('evento')->sort()->values()->all() === ['ABIERTO', 'RETRABAJADO']);
    chequear($fallos, '10 · retrabajar dos veces -> rechazado', (function () use ($def10, $retrabajador) {
        try { DoorService::retrabajar($def10->id, $retrabajador); return false; }
        catch (\RuntimeException $e) { return $e->getCode() === 409; }
    })());
    chequear($fallos, '10 · no crea cambio de pieza ni toca el buffer',
        EolDoor::where('qr', $P . 'SET-9')->count() === 0 && EolPieza::where('estado', 'DISPONIBLE')->count() === $stockAntes);
    chequear($fallos, '10 · sin marcas -> rechazado', (function () use ($P) {
        try { DoorService::marcarRetrabajo(payload($P . 'SET-9', 'PANEL', '', '', ['circles' => []])); return false; }
        catch (\RuntimeException $e) { return true; }
    })());

    // 11) Calidad marca -> produccion cambia la pieza -> Calidad valida
    echo "
-- 11) marca Calidad, cambia produccion, valida Calidad --
";
    $vSale  = pieza($qrsPiezas, $P . 'V-SALE', 'PANEL', 'EN_ASIENTO');
    $vEntra = pieza($qrsPiezas, $P . 'V-ENTRA', 'PANEL', 'DISPONIBLE');
    DoorService::marcarRetrabajo(payload($P . 'SET-10', 'PANEL', '', ''));
    $d11 = EolDefecto::where('qr', $P . 'SET-10')->first();
    $c11 = DoorService::cambiar([
        'qr' => $P . 'SET-10', 'tipo_pieza' => 'PANEL', 'defecto_ids' => [$d11->id],
        'pieza_defectuosa_qr' => $vSale->pieza_qr, 'pieza_reemplazo_qr' => $vEntra->pieza_qr,
        'user' => $retrabajador->id, 'modelo' => $MODELO, 'lado' => 'LH',
    ]);
    chequear($fallos, '11 · produccion cambia la pieza: defecto CAMBIADO', EolDefecto::find($d11->id)->estado === 'CAMBIADO' && $c11['door']->pieza_entregada_qr === $vEntra->pieza_qr);
    $v11 = DoorService::validarDefecto($d11->id, $user, 'validar');
    chequear($fallos, '11 · Calidad valida: VALIDADO con su usuario', $v11->estado === 'VALIDADO' && $v11->validado_at !== null);

    // 12) Calidad marca -> retrabajo -> Calidad rechaza -> retrabajo -> valida
    echo "
-- 12) rechazo de Calidad y nuevo retrabajo --
";
    DoorService::marcarRetrabajo(payload($P . 'SET-11', 'GARNISH', '', ''));
    $d12 = EolDefecto::where('qr', $P . 'SET-11')->first();
    DoorService::retrabajar($d12->id, $retrabajador);
    $r12 = DoorService::validarDefecto($d12->id, $user, 'rechazar', 'sigue la mancha');
    chequear($fallos, '12 · rechazo: vuelve a PENDIENTE con 1 reapertura', $r12->estado === 'PENDIENTE' && (int) $r12->reaperturas === 1 && $r12->reparado_at === null);
    chequear($fallos, '12 · validar algo PENDIENTE -> rechazado', (function () use ($d12, $user) {
        try { DoorService::validarDefecto($d12->id, $user, 'validar'); return false; }
        catch (RuntimeException $e) { return $e->getCode() === 409; }
    })());
    DoorService::retrabajar($d12->id, $retrabajador);
    chequear($fallos, '12 · segundo retrabajo y validacion OK', DoorService::validarDefecto($d12->id, $user, 'validar')->estado === 'VALIDADO');
} finally {
    echo "\n-- limpieza --\n";
    limpiar(array_unique(array_merge($todosQr, $qrsPiezas)), $qrsSets);
    echo "[datos de prueba eliminados]\n";
}

echo "\n" . ($fallos === 0 ? "\e[32mTODO OK\e[0m" : "\e[31m$fallos fallo(s)\e[0m") . "\n";
exit($fallos === 0 ? 0 : 1);
