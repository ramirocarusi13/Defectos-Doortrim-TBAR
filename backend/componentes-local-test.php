<?php
/**
 * PRUEBA LOCAL del registro de COMPONENTES consumidos en retrabajos, contra la
 * base local (EOL_TBAR).
 *
 *   1) reparar con 3 componentes (1 DESARMADO x1, 1 CAMBIADO x2, 1 SCRAP x1) ->
 *      filas correctas en eol_defecto_componentes + evento RETRABAJADO con el
 *      resumen legible (incluye "(desarme)")
 *   2) reparar sin componentes -> cero filas, comportamiento identico al actual
 *   3) accion invalida ('FOO') -> cae a CAMBIADO
 *   4) reparar un defecto PRACTICA -> registra filas, pero el reporte las excluye
 *   5) GET /api/reporte-componentes agrega bien (totales + porComponente + porDia,
 *      incluyendo desarmados)
 *
 *   php componentes-local-test.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\EolDefectoController;
use App\Http\Controllers\ReporteComponentesController;
use App\Models\EolDefecto;
use App\Models\EolDefectoComponente;
use App\Models\EolDefectoEvento;
use App\Models\User;
use Illuminate\Http\Request;

function ok($b) { return $b ? "\e[32mOK\e[0m" : "\e[31mFALLA\e[0m"; }

$fallos = 0;
function chk(bool $cond, string $label): void {
    global $fallos;
    echo $label . ' [' . ok($cond) . "]\n";
    if (!$cond) $fallos++;
}

echo "=============================================================\n";
echo "  PRUEBA LOCAL COMPONENTES CONSUMIDOS EN RETRABAJOS\n";
echo "=============================================================\n";

$PREFIX = 'TEST-COMPONENTES-260909';
$HOY    = date('Y-m-d');

// ---------- limpieza previa ----------
$viejos = EolDefecto::where('qr', 'like', "$PREFIX%")->pluck('id');
EolDefectoComponente::whereIn('eol_defecto_id', $viejos)->delete();
EolDefectoEvento::whereIn('eol_defecto_id', $viejos)->delete();
EolDefecto::whereIn('id', $viejos)->delete();

// ---------- usuarios ----------
$calidad = User::where('cod_autorizacion', '9001')->first();
if (!$calidad) {
    echo "\e[31mFalta el usuario de prueba cod_autorizacion=9001 (ver memoria: usuarios-prueba).\e[0m\n";
    exit(1);
}
$practica = User::where('cod_autorizacion', '9005')->first();
if (!$practica) {
    echo "\e[31mFalta el usuario de practica cod_autorizacion=9005: correr primero 'php seed-usuario-practica.php'.\e[0m\n";
    exit(1);
}

$controller = new EolDefectoController();
$reporte    = new ReporteComponentesController();

function storeDefecto(EolDefectoController $controller, string $qr, User $user, string $flujoInput = 'EOL'): int
{
    $req = Request::create('/api/eol-defecto', 'POST', [
        'qr'      => $qr,
        'user'    => $user->id,
        'linea'   => 'TEST-COMPONENTES-LINEA',
        'flujo'   => $flujoInput,
        'circles' => [[
            'falla'     => ['id' => 1, 'code' => 'TST', 'name' => 'Falla de prueba', 'caracteristica' => 'C'],
            'cantidad'  => 1,
            'esCritico' => false,
            'esLeve'    => false,
        ]],
    ]);
    $resp = json_decode($controller->store($req)->getContent(), true);
    $id = $resp['data']['ids'][0] ?? null;
    if (!$id) {
        echo "\e[31mNo se pudo crear el defecto de prueba ($qr): " . json_encode($resp) . "\e[0m\n";
        exit(1);
    }
    return (int) $id;
}

try {
    // =====================================================================
    // 1) reparar con 2 componentes (1 CAMBIADO x2, 1 SCRAP x1)
    // =====================================================================
    echo "\n-- reparar con 3 componentes --\n";
    $QR1 = "$PREFIX-A-0001";
    $id1 = storeDefecto($controller, $QR1, $calidad);

    $req = Request::create("/api/eol-defecto/$id1/reparar", 'PUT', [
        'user'          => $calidad->id,
        'estado'        => 'RETRABAJADO',
        'observaciones' => 'obs original',
        'componentes'   => [
            ['codigo' => 'FUNDA', 'nombre' => 'Funda', 'accion' => 'CAMBIADO', 'cantidad' => 2],
            ['codigo' => 'CIERRE', 'nombre' => 'Cierre', 'accion' => 'SCRAP', 'cantidad' => 1],
            ['codigo' => 'BROCHE', 'nombre' => 'Broche', 'accion' => 'DESARMADO', 'cantidad' => 1],
        ],
    ]);
    $resp = $controller->reparar($req, $id1);
    chk($resp->getStatusCode() === 200, 'PASO reparar con componentes · status 200');

    $filas1 = EolDefectoComponente::where('eol_defecto_id', $id1)->orderBy('id')->get();
    chk($filas1->count() === 3, 'PASO reparar con componentes · 3 filas en eol_defecto_componentes');

    $funda = $filas1->firstWhere('componente_codigo', 'FUNDA');
    chk($funda && $funda->accion === 'CAMBIADO' && (int) $funda->cantidad === 2, "PASO reparar con componentes · FUNDA CAMBIADO x2");
    chk($funda && $funda->qr === $QR1, 'PASO reparar con componentes · fila FUNDA con el qr del defecto');
    chk($funda && (int) $funda->user_id === (int) $calidad->id && $funda->user_nombre === $calidad->name, 'PASO reparar con componentes · fila FUNDA con el user resuelto');

    $cierre = $filas1->firstWhere('componente_codigo', 'CIERRE');
    chk($cierre && $cierre->accion === 'SCRAP' && (int) $cierre->cantidad === 1, 'PASO reparar con componentes · CIERRE SCRAP x1');

    $broche = $filas1->firstWhere('componente_codigo', 'BROCHE');
    chk($broche && $broche->accion === 'DESARMADO' && (int) $broche->cantidad === 1, 'PASO reparar con componentes · BROCHE DESARMADO x1');

    $defecto1 = EolDefecto::find($id1);
    $resumenEsperado = 'Componentes: 2x FUNDA (cambio), 1x CIERRE (scrap), 1x BROCHE (desarme)';
    chk(str_contains((string) $defecto1->reparacion_obs, $resumenEsperado), "PASO reparar con componentes · reparacion_obs trae el resumen ('$resumenEsperado')");
    chk(str_contains((string) $defecto1->reparacion_obs, 'obs original'), 'PASO reparar con componentes · reparacion_obs conserva la obs original');

    $eventoRetrabajado = EolDefectoEvento::where('eol_defecto_id', $id1)->where('evento', 'RETRABAJADO')->first();
    chk($eventoRetrabajado && str_contains((string) $eventoRetrabajado->observaciones, $resumenEsperado), 'PASO reparar con componentes · evento RETRABAJADO trae el resumen en observaciones');

    // =====================================================================
    // 2) reparar sin componentes -> cero filas, comportamiento identico al actual
    // =====================================================================
    echo "\n-- reparar sin componentes (control) --\n";
    $QR2 = "$PREFIX-B-0001";
    $id2 = storeDefecto($controller, $QR2, $calidad);

    $req = Request::create("/api/eol-defecto/$id2/reparar", 'PUT', [
        'user'          => $calidad->id,
        'estado'        => 'RETRABAJADO',
        'observaciones' => 'obs sin componentes',
    ]);
    $resp = $controller->reparar($req, $id2);
    chk($resp->getStatusCode() === 200, 'PASO reparar sin componentes · status 200');

    $filas2 = EolDefectoComponente::where('eol_defecto_id', $id2)->count();
    chk($filas2 === 0, 'PASO reparar sin componentes · cero filas en eol_defecto_componentes');

    $defecto2 = EolDefecto::find($id2);
    chk($defecto2->reparacion_obs === 'obs sin componentes', 'PASO reparar sin componentes · reparacion_obs identica a la enviada (sin resumen)');

    // Idem, pero mandando explicitamente un array vacio.
    $QR2B = "$PREFIX-B-0002";
    $id2b = storeDefecto($controller, $QR2B, $calidad);
    $req = Request::create("/api/eol-defecto/$id2b/reparar", 'PUT', [
        'user'          => $calidad->id,
        'estado'        => 'RETRABAJADO',
        'observaciones' => 'obs con array vacio',
        'componentes'   => [],
    ]);
    $resp = $controller->reparar($req, $id2b);
    $defecto2b = EolDefecto::find($id2b);
    chk(EolDefectoComponente::where('eol_defecto_id', $id2b)->count() === 0, 'PASO reparar con componentes=[] · cero filas');
    chk($defecto2b->reparacion_obs === 'obs con array vacio', 'PASO reparar con componentes=[] · reparacion_obs identica a la enviada');

    // =====================================================================
    // 3) accion invalida -> cae a CAMBIADO
    // =====================================================================
    echo "\n-- accion invalida cae a CAMBIADO --\n";
    $QR3 = "$PREFIX-C-0001";
    $id3 = storeDefecto($controller, $QR3, $calidad);

    $req = Request::create("/api/eol-defecto/$id3/reparar", 'PUT', [
        'user'        => $calidad->id,
        'estado'      => 'RETRABAJADO',
        'componentes' => [
            ['codigo' => 'ARNES', 'accion' => 'FOO', 'cantidad' => 500], // accion invalida + cantidad fuera de rango
            ['codigo' => '  ', 'accion' => 'CAMBIADO'], // sin codigo -> se ignora
        ],
    ]);
    $resp = $controller->reparar($req, $id3);
    chk($resp->getStatusCode() === 200, 'PASO accion invalida · status 200');

    $filas3 = EolDefectoComponente::where('eol_defecto_id', $id3)->get();
    chk($filas3->count() === 1, 'PASO accion invalida · el item sin codigo se ignoro (1 sola fila)');
    $arnes = $filas3->first();
    chk($arnes && $arnes->accion === 'CAMBIADO', "PASO accion invalida · 'FOO' cayo a CAMBIADO");
    chk($arnes && (int) $arnes->cantidad === 99, 'PASO accion invalida · cantidad 500 se acota a 99');

    // =====================================================================
    // 4) defecto PRACTICA: registra filas, pero el reporte las excluye
    // =====================================================================
    echo "\n-- defecto PRACTICA (registra filas, reporte las excluye) --\n";
    $QR4 = "$PREFIX-D-0001";
    $id4 = storeDefecto($controller, $QR4, $practica, 'EOL'); // el server lo sella a PRACTICA igual
    $defecto4 = EolDefecto::find($id4);
    chk($defecto4->flujo === 'PRACTICA', 'PASO practica · flujo sellado a PRACTICA');

    $req = Request::create("/api/eol-defecto/$id4/reparar", 'PUT', [
        'user'        => $calidad->id,
        'estado'      => 'RETRABAJADO',
        'componentes' => [
            ['codigo' => 'FUNDA', 'accion' => 'CAMBIADO', 'cantidad' => 3],
        ],
    ]);
    $resp = $controller->reparar($req, $id4);
    chk($resp->getStatusCode() === 200, 'PASO practica · reparar status 200');
    chk(EolDefectoComponente::where('eol_defecto_id', $id4)->count() === 1, 'PASO practica · SI registra la fila del componente');

    // =====================================================================
    // 5) reporte-componentes agrega bien
    // =====================================================================
    echo "\n-- GET /api/reporte-componentes --\n";
    $req = Request::create('/api/reporte-componentes', 'GET', ['desde' => $HOY, 'hasta' => $HOY]);
    $resp = $reporte->index($req);
    chk($resp->getStatusCode() === 200, 'PASO reporte · status 200');
    $body = json_decode($resp->getContent(), true);
    $data = $body['data'] ?? [];

    // Cambiados esperados (sin practica): FUNDA 2 (test1) + ARNES 99 (test3, cantidad
    // 500 acotada a 99). Scrap esperado: CIERRE 1 (test1). Desarmados: BROCHE 1 (test1).
    // El componente FUNDA de PRACTICA (3, CAMBIADO) NO debe sumar.
    $porComponente = collect($data['porComponente'] ?? []);
    $fundaRep = $porComponente->firstWhere('codigo', 'FUNDA');
    chk($fundaRep !== null && (int) $fundaRep['cambiados'] === 2 && (int) $fundaRep['scrap'] === 0 && (int) $fundaRep['desarmados'] === 0, "PASO reporte · FUNDA: cambiados=2 (excluye los 3 de practica), scrap=0, desarmados=0");

    $cierreRep = $porComponente->firstWhere('codigo', 'CIERRE');
    chk($cierreRep !== null && (int) $cierreRep['scrap'] === 1 && (int) $cierreRep['cambiados'] === 0 && (int) $cierreRep['desarmados'] === 0, 'PASO reporte · CIERRE: scrap=1, cambiados=0, desarmados=0');

    $broceRep = $porComponente->firstWhere('codigo', 'BROCHE');
    chk($broceRep !== null && (int) $broceRep['desarmados'] === 1 && (int) $broceRep['cambiados'] === 0 && (int) $broceRep['scrap'] === 0, 'PASO reporte · BROCHE: desarmados=1, cambiados=0, scrap=0');

    $arnesRep = $porComponente->firstWhere('codigo', 'ARNES');
    chk($arnesRep !== null && (int) $arnesRep['cambiados'] === 99, 'PASO reporte · ARNES: cambiados=99 (accion invalida normalizada a CAMBIADO, cantidad acotada a 99)');

    // total = desarmados + cambiados + scrap por fila.
    chk($fundaRep !== null && (int) $fundaRep['total'] === 2, 'PASO reporte · total de FUNDA = desarmados+cambiados+scrap = 2');
    chk($broceRep !== null && (int) $broceRep['total'] === 1, 'PASO reporte · total de BROCHE = 1');

    // orden desc por total: el primero de la lista tiene el total mas alto (ARNES=99).
    $totales = $porComponente->pluck('total')->all();
    $ordenado = $totales === collect($totales)->sortDesc()->values()->all();
    chk($ordenado, 'PASO reporte · porComponente ordenado por total desc');
    chk(($porComponente->first()['codigo'] ?? null) === 'ARNES', 'PASO reporte · el primero de la lista es ARNES (mayor total)');

    // totales globales: cambiados = FUNDA(2) + ARNES(99) = 101 (excluye practica); scrap = CIERRE(1) = 1;
    // desarmados = BROCHE(1) = 1.
    chk((int) ($data['totalCambiados'] ?? -1) === 101, 'PASO reporte · totalCambiados=101 (excluye los 3 de practica)');
    chk((int) ($data['totalScrap'] ?? -1) === 1, 'PASO reporte · totalScrap=1');
    chk((int) ($data['totalDesarmados'] ?? -1) === 1, 'PASO reporte · totalDesarmados=1');

    // porDia: el dia de hoy tiene que sumar lo mismo que los totales globales.
    $diaHoy = (string) (int) date('j');
    $porDiaHoy = $data['porDia'][$diaHoy] ?? null;
    chk($porDiaHoy !== null && (int) $porDiaHoy['cambiados'] === 101 && (int) $porDiaHoy['scrap'] === 1 && (int) $porDiaHoy['desarmados'] === 1, "PASO reporte · porDia['$diaHoy'] coincide con los totales (cambiados=101, scrap=1, desarmados=1)");

    // Filtro de fecha fuera de rango (ayer/ayer) -> no debe traer nada de hoy.
    $ayer = date('Y-m-d', strtotime('-1 day'));
    $req = Request::create('/api/reporte-componentes', 'GET', ['desde' => $ayer, 'hasta' => $ayer]);
    $resp = $reporte->index($req);
    $body = json_decode($resp->getContent(), true);
    chk((int) ($body['data']['totalCambiados'] ?? -1) === 0 && (int) ($body['data']['totalScrap'] ?? -1) === 0 && (int) ($body['data']['totalDesarmados'] ?? -1) === 0, 'PASO reporte · rango desde=hasta=ayer no trae nada de hoy (filtro de fecha funciona)');

} finally {
    // ---------- limpieza ----------
    $ids = EolDefecto::where('qr', 'like', "$PREFIX%")->pluck('id');
    EolDefectoComponente::whereIn('eol_defecto_id', $ids)->delete();
    EolDefectoEvento::whereIn('eol_defecto_id', $ids)->delete();
    EolDefecto::whereIn('id', $ids)->delete();
    echo "\n[datos de prueba eliminados]\n";
}

echo "\n" . ($fallos === 0 ? "\e[32mTODO OK\e[0m" : "\e[31m$fallos fallo(s)\e[0m") . "\n";
