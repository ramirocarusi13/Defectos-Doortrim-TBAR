<?php
/**
 * PRUEBA LOCAL de la clasificacion A/B/C con autorizacion de GL + desglose por
 * puesto del reporte QC. Corre contra la base local (EOL_TBAR), sin tocar JITMS
 * de verdad (los QR de prueba no matchean ninguna clave real, asi que
 * InterlockService::cargarPendiente no inserta nada).
 *
 *   1) marcar defecto A (store)        -> caracteristica='A' sellada
 *   2) validar sin gl_codigo           -> 422 {requiereGl:true}
 *   3) validar con codigo de rol < GL  -> 403 {requiereGl:true}
 *   4) validar con GL valido (rol>=30) -> 200 VALIDADO, validado_gl_por sellado
 *   5) defecto C valida sin GL         -> 200 VALIDADO (como siempre)
 *   6) mapToLegacy (reporte por QR)    -> incluye 'caracteristica'
 *   7) reporte-qc/mensual              -> incluye 'puestos' (mismo shape que 'lineas')
 *   8) reporte-qc/top                  -> cada item trae 'caracteristica'
 *
 *   php caracteristica-local-test.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\EolDefectoController;
use App\Http\Controllers\ReporteQcController;
use App\Models\EolDefecto;
use App\Models\EolDefectoEvento;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function ok($b) { return $b ? "\e[32mOK\e[0m" : "\e[31mFALLA\e[0m"; }

$fallos = 0;
function chk(bool $cond, string $label): void {
    global $fallos;
    echo $label . ' [' . ok($cond) . "]\n";
    if (!$cond) $fallos++;
}

echo "=============================================================\n";
echo "  PRUEBA LOCAL A/B/C + AUTORIZACION GL + PUESTOS REPORTE QC\n";
echo "=============================================================\n";

$PREFIX = 'TEST-CAR-260831';
$MES    = date('Y-m'); // hoy cae en el mes actual: aparece en reporte-qc/mensual

// ---------- limpieza previa (por si quedo algo de una corrida anterior) ----------
$viejos = EolDefecto::where('qr', 'like', "$PREFIX%")->pluck('id');
EolDefectoEvento::whereIn('eol_defecto_id', $viejos)->delete();
EolDefecto::whereIn('id', $viejos)->delete();
User::where('cod_autorizacion', 'like', "$PREFIX%")->delete();

// ---------- usuarios de prueba ----------
$calidad = User::where('cod_autorizacion', '9001')->first(); // operario de calidad (marca/valida), ya existe
if (!$calidad) {
    echo "\e[31mFalta el usuario de prueba cod_autorizacion=9001 (ver memoria: usuarios-prueba).\e[0m\n";
    exit(1);
}

$gl = User::create([
    'name'             => 'GL DE PRUEBA',
    'email'            => "gl.prueba.$PREFIX@eol-tbar.local",
    'password'         => bcrypt('x'),
    'cod_autorizacion' => "$PREFIX-GL",
    'departamento'     => 'CALIDAD',
    'rol'              => 30, // GL
    'activo'           => 1,
]);
$noGl = User::create([
    'name'             => 'TL DE PRUEBA (SIN GL)',
    'email'            => "tl.prueba.$PREFIX@eol-tbar.local",
    'password'         => bcrypt('x'),
    'cod_autorizacion' => "$PREFIX-TL",
    'departamento'     => 'PRODUCCION',
    'rol'              => 20, // TL, no alcanza
    'activo'           => 1,
]);
// Validador de calidad de rol BAJO (<30): con el 9001 (rol 70) el backend
// autoriza solo por rol y nunca se ejercita el escaneo del GL.
$inspector = User::create([
    'name'             => 'INSPECTOR DE PRUEBA (ROL BAJO)',
    'email'            => "qc.prueba.$PREFIX@eol-tbar.local",
    'password'         => bcrypt('x'),
    'cod_autorizacion' => "$PREFIX-QC",
    'departamento'     => 'CALIDAD',
    'rol'              => 10,
    'activo'           => 1,
]);

$controller = new EolDefectoController();

function storeDefecto(EolDefectoController $controller, string $qr, string $puesto, string $caracteristica, $calidad): int
{
    $req = Request::create('/api/eol-defecto', 'POST', [
        'qr'            => $qr,
        'user'          => $calidad->id,
        'linea'         => 'EOL-TBAR',
        'puesto_id'     => "puesto-$puesto",
        'puesto_nombre' => "Puesto $puesto",
        'circles'       => [[
            'falla'          => ['id' => 1, 'code' => 'TST', 'name' => 'Falla de prueba', 'caracteristica' => $caracteristica],
            'cantidad'       => 1,
            'esCritico'      => false,
            'esLeve'         => false,
        ]],
    ]);
    $resp = json_decode($controller->store($req)->getContent(), true);
    $id = $resp['data']['ids'][0] ?? null;
    if (!$id) {
        echo "\e[31mNo se pudo crear el defecto de prueba ($qr): " . json_encode($resp) . "\e[0m\n";
        exit(1);
    }
    return $id;
}

// =====================================================================
// 1) Defecto tipo A
// =====================================================================
echo "\n-- Defecto A --\n";
$idA = storeDefecto($controller, "$PREFIX-A-0001", '1', 'A', $calidad);
$defectoA = EolDefecto::find($idA);
chk($defectoA->caracteristica === 'A', 'PASO marcar A · caracteristica sellada = A');

// 2) validar sin gl_codigo -> 422 requiereGl
$req = Request::create("/api/eol-defecto/$idA/validar", 'PUT', ['user' => $inspector->id, 'accion' => 'validar']);
$resp = $controller->validar($req, $idA);
$body = json_decode($resp->getContent(), true);
chk($resp->getStatusCode() === 422, 'PASO validar A sin gl_codigo · status 422');
chk(($body['data']['requiereGl'] ?? false) === true, 'PASO validar A sin gl_codigo · data.requiereGl=true');
chk(($body['error'] ?? false) === true, 'PASO validar A sin gl_codigo · error=true');

// 3) validar con codigo de rol < 30 -> 403
$req = Request::create("/api/eol-defecto/$idA/validar", 'PUT', ['user' => $inspector->id, 'accion' => 'validar', 'gl_codigo' => $noGl->cod_autorizacion]);
$resp = $controller->validar($req, $idA);
$body = json_decode($resp->getContent(), true);
chk($resp->getStatusCode() === 403, 'PASO validar A con codigo rol<GL · status 403');
chk(($body['data']['requiereGl'] ?? false) === true, 'PASO validar A con codigo rol<GL · data.requiereGl=true');

// codigo inexistente -> tambien 403
$req = Request::create("/api/eol-defecto/$idA/validar", 'PUT', ['user' => $inspector->id, 'accion' => 'validar', 'gl_codigo' => 'NO-EXISTE-999']);
$resp = $controller->validar($req, $idA);
chk($resp->getStatusCode() === 403, 'PASO validar A con codigo inexistente · status 403');

// 4) validar con GL valido -> 200, VALIDADO, validado_gl_por sellado
$req = Request::create("/api/eol-defecto/$idA/validar", 'PUT', ['user' => $inspector->id, 'accion' => 'validar', 'gl_codigo' => $gl->cod_autorizacion]);
$resp = $controller->validar($req, $idA);
$body = json_decode($resp->getContent(), true);
chk($resp->getStatusCode() === 200, 'PASO validar A con GL valido · status 200');
$defectoA->refresh();
chk($defectoA->estado === 'VALIDADO', 'PASO validar A con GL valido · estado=VALIDADO');
chk((int) $defectoA->validado_gl_por === $gl->id, 'PASO validar A con GL valido · validado_gl_por sellado');
chk($defectoA->validado_gl_por_nombre === $gl->name, 'PASO validar A con GL valido · validado_gl_por_nombre sellado');

$eventoValidado = EolDefectoEvento::where('eol_defecto_id', $idA)->where('evento', 'VALIDADO')->first();
chk($eventoValidado && str_contains((string) $eventoValidado->observaciones, 'Autorizo GL: ' . $gl->name), 'PASO validar A con GL valido · evento con "Autorizo GL: <nombre>"');

// 4b) si el que valida YA es GL o superior, autoriza el mismo SIN gl_codigo
echo "\n-- Defecto A validado por un GL logueado (sin escaneo extra) --\n";
$idA2 = storeDefecto($controller, "$PREFIX-A-0002", '1', 'A', $calidad);
$req = Request::create("/api/eol-defecto/$idA2/validar", 'PUT', ['user' => $gl->id, 'accion' => 'validar']);
$resp = $controller->validar($req, $idA2);
chk($resp->getStatusCode() === 200, 'PASO validar A logueado como GL · status 200 sin gl_codigo');
$defectoA2 = EolDefecto::find($idA2);
chk($defectoA2->estado === 'VALIDADO', 'PASO validar A logueado como GL · estado=VALIDADO');
chk((int) $defectoA2->validado_gl_por === $gl->id, 'PASO validar A logueado como GL · validado_gl_por = el mismo GL');

// =====================================================================
// 5) Defecto tipo C: valida sin GL, como siempre
// =====================================================================
echo "\n-- Defecto C --\n";
$idC = storeDefecto($controller, "$PREFIX-C-0001", '2', 'C', $calidad);
$defectoC = EolDefecto::find($idC);
chk($defectoC->caracteristica === 'C', 'PASO marcar C · caracteristica sellada = C');

$req = Request::create("/api/eol-defecto/$idC/validar", 'PUT', ['user' => $calidad->id, 'accion' => 'validar']);
$resp = $controller->validar($req, $idC);
$body = json_decode($resp->getContent(), true);
chk($resp->getStatusCode() === 200, 'PASO validar C sin gl_codigo · status 200');
$defectoC->refresh();
chk($defectoC->estado === 'VALIDADO', 'PASO validar C sin gl_codigo · estado=VALIDADO');
chk($defectoC->validado_gl_por === null, 'PASO validar C sin gl_codigo · validado_gl_por sigue null');

// Defecto B, por completitud del catalogo (no exige GL, igual que C)
echo "\n-- Defecto B --\n";
$idB = storeDefecto($controller, "$PREFIX-B-0001", '2', 'B', $calidad);
$defectoB = EolDefecto::find($idB);
chk($defectoB->caracteristica === 'B', 'PASO marcar B · caracteristica sellada = B');
$req = Request::create("/api/eol-defecto/$idB/validar", 'PUT', ['user' => $calidad->id, 'accion' => 'validar']);
$resp = $controller->validar($req, $idB);
chk($resp->getStatusCode() === 200, 'PASO validar B sin gl_codigo · status 200');

// caracteristica invalida en el input -> cae a 'C'
echo "\n-- Defecto con caracteristica invalida en el input --\n";
$idRaro = storeDefecto($controller, "$PREFIX-X-0001", '3', 'ZZZ', $calidad);
$defectoRaro = EolDefecto::find($idRaro);
chk($defectoRaro->caracteristica === 'C', 'PASO marcar con valor invalido · cae a C');

// =====================================================================
// 6) mapToLegacy (reporte por QR) incluye 'caracteristica'
// =====================================================================
echo "\n-- reporte (mapToLegacy) --\n";
$req = Request::create('/api/eol-defectos/reporte', 'GET', ['qr' => "$PREFIX-A-0001"]);
$resp = $controller->reporte($req);
$body = json_decode($resp->getContent(), true);
$item = $body['data'][0] ?? null;
chk($item !== null && $item['caracteristica'] === 'A', 'PASO reporte(qr) · trae caracteristica=A en el shape legacy');

// =====================================================================
// 7) reporte-qc/mensual: 'puestos' con el mismo shape que 'lineas'
// =====================================================================
echo "\n-- reporte-qc/mensual --\n";
$reporteQc = new ReporteQcController();
$req = Request::create('/api/reporte-qc/mensual', 'GET', ['mes' => $MES]);
$resp = $reporteQc->mensual($req);
$body = json_decode($resp->getContent(), true);
$data = $body['data'] ?? [];
chk(isset($data['puestos']) && is_array($data['puestos']), 'PASO mensual · trae "puestos" (array)');

$bloquePuesto1 = collect($data['puestos'] ?? [])->firstWhere('puesto', 'Puesto 1');
chk($bloquePuesto1 !== null, 'PASO mensual · aparece "Puesto 1" (donde marcamos el defecto A)');
chk($bloquePuesto1 && isset($bloquePuesto1['turnos']) && isset($bloquePuesto1['total']) && array_key_exists('totalMesAnterior', $bloquePuesto1), 'PASO mensual · bloque de puesto con mismo shape que linea (turnos/total/totalMesAnterior)');

$bloqueLinea = collect($data['lineas'] ?? [])->first();
chk($bloqueLinea !== null && array_key_exists('linea', $bloqueLinea) && array_key_exists('turnos', $bloqueLinea) && array_key_exists('total', $bloqueLinea) && array_key_exists('totalMesAnterior', $bloqueLinea), 'PASO mensual · "lineas" sigue con su shape de siempre (no se rompio)');

// =====================================================================
// 8) reporte-qc/top: cada item trae 'caracteristica'
// =====================================================================
echo "\n-- reporte-qc/top --\n";
$hoy = date('Y-m-d');
$req = Request::create('/api/reporte-qc/top', 'GET', ['fecha' => $hoy]);
$resp = $reporteQc->top($req);
$body = json_decode($resp->getContent(), true);
$turnos = $body['data']['turnos'] ?? [];
$algunTop = null;
foreach ($turnos as $lineasArr) {
    foreach ($lineasArr as $bloqueLinea2) {
        foreach (($bloqueLinea2['top'] ?? []) as $t) {
            if ($t['codigo'] === 'TST') { $algunTop = $t; break 3; }
        }
    }
}
chk($algunTop !== null, 'PASO top · encuentra la falla de prueba TST en el dia de hoy');
chk($algunTop !== null && array_key_exists('caracteristica', $algunTop), 'PASO top · el item trae la clave "caracteristica"');
// Agrupamos A/B/C bajo el mismo codigo 'TST' -> tiene que quedarse con la mas grave (A).
chk($algunTop !== null && $algunTop['caracteristica'] === 'A', 'PASO top · con A+B+C mezclados en el mismo codigo, gana la mas grave (A)');

// =====================================================================
// limpieza
// =====================================================================
$ids = [$idA, $idB, $idC, $idRaro];
EolDefectoEvento::whereIn('eol_defecto_id', $ids)->delete();
EolDefecto::whereIn('id', $ids)->delete();
$gl->delete();
$noGl->delete();
echo "\n[datos de prueba eliminados]\n";

echo "\n" . ($fallos === 0 ? "\e[32mTODO OK\e[0m" : "\e[31m$fallos fallo(s)\e[0m") . "\n";
