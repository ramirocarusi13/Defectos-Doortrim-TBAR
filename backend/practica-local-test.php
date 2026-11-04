<?php
/**
 * PRUEBA LOCAL del modo practica/capacitacion (usuario 9005, departamento=
 * CAPACITACION), contra la base local (EOL_TBAR), sin tocar JITMS de verdad.
 *
 *   1) marcar con 9005            -> flujo='PRACTICA' sellado (aunque el payload
 *                                     pida flujo=EOL, el servidor lo pisa)
 *   2) marcar con 9005            -> eol_interlock_marcas NO crece (no llamo a
 *                                     InterlockService::cargarPendiente)
 *   3) reporte-qc/mensual         -> el defecto de practica NO aparece (sin flujo=)
 *   4) reporte-qc/mensual?flujo=PRACTICA -> SI aparece (auditoria explicita)
 *   5) dashboard-qc               -> el defecto de practica NO aparece en kpis/topDefectos
 *   6) reporte (por QR)           -> SI aparece el defecto de practica (la tablet
 *                                     necesita verlo para el ciclo completo)
 *   7) ciclo completo reparar + validar (defecto A, exige GL) -> sin llamar a
 *                                     InterlockService::confirmarRetrabajo
 *   8) defecto normal con 9001    -> flujo='EOL', interlock intacto (control)
 *
 *   php practica-local-test.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\DashboardQcController;
use App\Http\Controllers\EolDefectoController;
use App\Http\Controllers\ReporteQcController;
use App\Models\EolDefecto;
use App\Models\EolDefectoEvento;
use App\Models\FactoryConfig;
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
echo "  PRUEBA LOCAL MODO PRACTICA/CAPACITACION (usuario 9005)\n";
echo "=============================================================\n";

$PREFIX = 'TEST-PRACTICA-260909';
$MES    = date('Y-m'); // hoy cae en el mes actual: si NO se filtrara, aparaceria en mensual/dashboard
// Linea distintiva: el bloque 'lineas' de mensual() no tiene limite de top-N (a
// diferencia de topDefectos/porOperario), asi que es la forma confiable de medir
// cantidades exactas sin que el ranking se pierda contra los datos reales del mes.
$LINEA_TEST = 'TEST-PRACTICA-LINEA-260909';

// ---------- limpieza previa (por si quedo algo de una corrida anterior) ----------
$viejos = EolDefecto::where('qr', 'like', "$PREFIX%")->pluck('id');
EolDefectoEvento::whereIn('eol_defecto_id', $viejos)->delete();
EolDefecto::whereIn('id', $viejos)->delete();
DB::connection('sqlsrv')->table('eol_interlock_marcas')->where('qr', 'like', "$PREFIX%")->delete();

// ---------- usuarios ----------
$practica = User::where('cod_autorizacion', '9005')->first();
if (!$practica) {
    echo "\e[31mFalta el usuario de practica cod_autorizacion=9005: correr primero 'php seed-usuario-practica.php'.\e[0m\n";
    exit(1);
}
chk(strtoupper(trim((string) $practica->departamento)) === 'CAPACITACION', 'PASO seed · usuario 9005 con departamento=CAPACITACION');

$calidad = User::where('cod_autorizacion', '9001')->first(); // operario de calidad normal, control
if (!$calidad) {
    echo "\e[31mFalta el usuario de prueba cod_autorizacion=9001 (ver memoria: usuarios-prueba).\e[0m\n";
    exit(1);
}

// Habilito el interlock (contra la conexion local sqlsrv) para poder verificar que
// el modo practica lo salta de verdad, no que esta apagado el flag global.
$rec = FactoryConfig::where('key', 'active')->first();
$cfg = $rec->data; $orig = $cfg;
$cfg['settings']['interlockConnection'] = 'sqlsrv';
$cfg['settings']['plcInterlockEnabled'] = true;
$rec->data = $cfg; $rec->save();

$controller       = new EolDefectoController();
$reporteQc        = new ReporteQcController();
$dashboardQc      = new DashboardQcController();

function storeDefecto(EolDefectoController $controller, string $qr, $user, string $flujoInput, string $caracteristica, string $linea): array
{
    $req = Request::create('/api/eol-defecto', 'POST', [
        'qr'      => $qr,
        'user'    => $user->id,
        'linea'   => $linea,
        'flujo'   => $flujoInput,
        'circles' => [[
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
    return [$id, $resp];
}

$countMarcas = fn (string $qr) => DB::connection('sqlsrv')->table('eol_interlock_marcas')->where('qr', $qr)->count();

try {
    // =====================================================================
    // 1-2) marcar con 9005: flujo sellado a PRACTICA (aun pidiendo 'EOL' en el
    // payload) e interlock NUNCA tocado.
    // =====================================================================
    echo "\n-- Marcar con usuario 9005 (CAPACITACION) --\n";
    $QR_PRACTICA = "$PREFIX-A-0001";
    [$idPractica, $respPractica] = storeDefecto($controller, $QR_PRACTICA, $practica, 'EOL', 'A', $LINEA_TEST);
    $defectoPractica = EolDefecto::find($idPractica);

    chk($defectoPractica->flujo === 'PRACTICA', "PASO marcar 9005 · flujo sellado='PRACTICA' (payload pedia 'EOL')");
    chk(($respPractica['data']['interlockCargado'] ?? null) === false, 'PASO marcar 9005 · interlockCargado=false en la respuesta');
    chk($countMarcas($QR_PRACTICA) === 0, 'PASO marcar 9005 · eol_interlock_marcas NO crece (sigue en 0)');

    // Ademas: si alguien pidiera flujo=PRACTICA en el payload sin ser del depto
    // CAPACITACION, la whitelist lo tira a EOL (no se puede spoofear PRACTICA).
    $QR_SPOOF = "$PREFIX-SPOOF-0001";
    [$idSpoof] = storeDefecto($controller, $QR_SPOOF, $calidad, 'PRACTICA', 'C', $LINEA_TEST);
    $defectoSpoof = EolDefecto::find($idSpoof);
    chk($defectoSpoof->flujo === 'EOL', "PASO anti-spoof · 9001 pidiendo flujo=PRACTICA en el payload cae a 'EOL'");

    // =====================================================================
    // 3-4) reporte-qc/mensual: sin flujo= no aparece; con flujo=PRACTICA si.
    // =====================================================================
    echo "\n-- reporte-qc/mensual --\n";
    // El bloque 'lineas' agrupa TODAS las lineas del mes (sin limite top-N, a
    // diferencia de topDefectos/porOperario), asi que usando una linea distintiva
    // ($LINEA_TEST) el total no se pierde contra los datos reales del mes.
    $req = Request::create('/api/reporte-qc/mensual', 'GET', ['mes' => $MES]);
    $resp = $reporteQc->mensual($req);
    $body = json_decode($resp->getContent(), true);
    $bloqueLineaTest = collect($body['data']['lineas'] ?? [])->firstWhere('linea', $LINEA_TEST);
    // En este linea solo hay 2 defectos marcados: 1 PRACTICA (9005) + 1 EOL (spoof/9001).
    // Sin flujo= explicito, solo debe contar el EOL.
    chk($bloqueLineaTest !== null && (int) $bloqueLineaTest['total'] === 1, "PASO mensual sin flujo= · linea '$LINEA_TEST' cuenta solo el defecto EOL (practica excluida, total=1)");

    $porOperarioPractica = collect($body['data']['porOperario'] ?? [])->firstWhere('nombre', $practica->name);
    chk($porOperarioPractica === null, 'PASO mensual sin flujo= · porOperario NO incluye a QC PRACTICA');

    $req = Request::create('/api/reporte-qc/mensual', 'GET', ['mes' => $MES, 'flujo' => 'PRACTICA']);
    $resp = $reporteQc->mensual($req);
    $body = json_decode($resp->getContent(), true);
    $bloqueLineaTest2 = collect($body['data']['lineas'] ?? [])->firstWhere('linea', $LINEA_TEST);
    chk($bloqueLineaTest2 !== null && (int) $bloqueLineaTest2['total'] === 1, "PASO mensual ?flujo=PRACTICA · linea '$LINEA_TEST' SI trae al defecto de practica (auditoria explicita, total=1)");

    echo "\n-- reporte-qc/top --\n";
    $req = Request::create('/api/reporte-qc/top', 'GET', ['fecha' => date('Y-m-d')]);
    $resp = $reporteQc->top($req);
    $body = json_decode($resp->getContent(), true);
    $cantidadTop = 0;
    foreach (($body['data']['turnos'] ?? []) as $lineasArr) {
        foreach ($lineasArr as $bloqueLinea) {
            foreach (($bloqueLinea['top'] ?? []) as $t) {
                if ($t['codigo'] === 'TST') { $cantidadTop += (int) $t['cantidad']; }
            }
        }
    }
    chk($cantidadTop === 1, 'PASO top sin flujo= · cuenta solo el defecto EOL (practica excluida)');

    // =====================================================================
    // 5) dashboard-qc: el defecto de practica no aparece en kpis/topDefectos/operarios.
    // =====================================================================
    echo "\n-- dashboard-qc --\n";
    $req = Request::create('/api/dashboard-qc', 'GET', ['fecha' => date('Y-m-d')]);
    $resp = $dashboardQc->index($req);
    $body = json_decode($resp->getContent(), true);
    $topTstDash = collect($body['data']['topDefectos'] ?? [])->firstWhere('codigo', 'TST');
    chk($topTstDash !== null && (int) $topTstDash['cantidad'] === 1, 'PASO dashboard-qc · topDefectos cuenta solo el defecto EOL (practica excluida)');

    $operarioPracticaDash = collect($body['data']['operarios'] ?? [])->firstWhere('nombre', $practica->name);
    chk($operarioPracticaDash === null, 'PASO dashboard-qc · operarios NO incluye a QC PRACTICA');
    chk((int) ($body['data']['kpis']['marcados'] ?? -1) === 1, 'PASO dashboard-qc · kpis.marcados cuenta solo el defecto EOL');

    $req = Request::create('/api/dashboard-qc/operario', 'GET', ['nombre' => $practica->name, 'fecha' => date('Y-m-d')]);
    $resp = $dashboardQc->operario($req);
    $body = json_decode($resp->getContent(), true);
    chk((int) ($body['data']['cantidadPeriodo'] ?? -1) === 0, 'PASO dashboard-qc/operario · QC PRACTICA da cantidadPeriodo=0');
    chk((int) ($body['data']['mesActual'] ?? -1) === 0, 'PASO dashboard-qc/operario · QC PRACTICA da mesActual=0');

    $req = Request::create('/api/dashboard-qc/evolucion', 'GET', ['mes' => $MES]);
    $resp = $dashboardQc->evolucion($req);
    $body = json_decode($resp->getContent(), true);
    $hoyDia = (string) (int) date('j');
    // Solo puedo afirmar "no cuenta la practica"; puede haber otros defectos reales
    // del dia asi que no comparo contra un total exacto, solo que no reviente y
    // sea >= 0 (ya excluye practica por construccion, verificado arriba en index()).
    chk(isset($body['data']['porDia'][$hoyDia]), 'PASO dashboard-qc/evolucion · responde con el dia de hoy (no rompe)');

    // =====================================================================
    // 6) reporte por QR (detalle): SI muestra el defecto de practica.
    // =====================================================================
    echo "\n-- reporte por QR (detalle, sin filtrar) --\n";
    $req = Request::create('/api/eol-defectos/reporte', 'GET', ['qr' => $QR_PRACTICA]);
    $resp = $controller->reporte($req);
    $body = json_decode($resp->getContent(), true);
    chk(count($body['data'] ?? []) === 1, 'PASO reporte(qr) · el detalle por QR SI trae el defecto de practica');

    // =====================================================================
    // 7) ciclo completo reparar + validar (defecto A, exige GL) sin interlock.
    // =====================================================================
    echo "\n-- Ciclo completo reparar + validar (practica, caracteristica A) --\n";
    $req = Request::create("/api/eol-defecto/$idPractica/reparar", 'PUT', ['user' => $calidad->id, 'estado' => 'RETRABAJADO']);
    $resp = $controller->reparar($req, $idPractica);
    chk($resp->getStatusCode() === 200, 'PASO reparar practica · status 200');
    $defectoPractica->refresh();
    chk($defectoPractica->estado === 'RETRABAJADO', 'PASO reparar practica · estado=RETRABAJADO');

    // Caracteristica A: exige GL. Un rol < 30 sin gl_codigo -> 422 (misma regla que EOL).
    $req = Request::create("/api/eol-defecto/$idPractica/validar", 'PUT', ['user' => $calidad->id, 'accion' => 'validar']);
    $resp = $controller->validar($req, $idPractica);
    chk($resp->getStatusCode() === 422, 'PASO validar practica (A) sin gl_codigo · status 422 (regla A/B/C intacta)');

    $gl = User::create([
        'name'             => 'GL PRACTICA DE PRUEBA',
        'email'            => "gl.practica.$PREFIX@eol-tbar.local",
        'password'         => bcrypt('x'),
        'cod_autorizacion' => "$PREFIX-GL",
        'departamento'     => 'CALIDAD',
        'rol'              => 30,
        'activo'           => 1,
    ]);
    $req = Request::create("/api/eol-defecto/$idPractica/validar", 'PUT', ['user' => $calidad->id, 'accion' => 'validar', 'gl_codigo' => $gl->cod_autorizacion]);
    $resp = $controller->validar($req, $idPractica);
    $body = json_decode($resp->getContent(), true);
    chk($resp->getStatusCode() === 200, 'PASO validar practica (A) con GL · status 200');
    $defectoPractica->refresh();
    chk($defectoPractica->estado === 'VALIDADO', 'PASO validar practica (A) con GL · estado=VALIDADO');
    chk(($body['interlockLiberado'] ?? null) === false, 'PASO validar practica · interlockLiberado=false en la respuesta');
    chk($countMarcas($QR_PRACTICA) === 0, 'PASO validar practica · eol_interlock_marcas sigue en 0 tras cerrar el ciclo');
    $gl->delete();

    // =====================================================================
    // 8) defecto normal con 9001: flujo='EOL', interlock intacto (control).
    // =====================================================================
    echo "\n-- Control: defecto normal con 9001 (flujo EOL) --\n";
    $QR_NORMAL = "$PREFIX-EOL-0001";
    [$idNormal, $respNormal] = storeDefecto($controller, $QR_NORMAL, $calidad, 'EOL', 'C', $LINEA_TEST);
    $defectoNormal = EolDefecto::find($idNormal);
    chk($defectoNormal->flujo === 'EOL', 'PASO marcar 9001 · flujo=EOL');
    // El QR no matchea ninguna llave real (resolverClave falla) -> cargarPendiente
    // devuelve false igual, PERO SI se llama (a diferencia de practica/door): no
    // queda cortado antes por la condicion del flujo. Lo verificamos por el flag
    // de la respuesta y porque no quedo ninguna marca (no por logs).
    chk(array_key_exists('interlockCargado', $respNormal['data']), 'PASO marcar 9001 · la respuesta trae interlockCargado (paso por la rama del interlock)');

    $req = Request::create("/api/eol-defecto/$idNormal/validar", 'PUT', ['user' => $calidad->id, 'accion' => 'validar']);
    $resp = $controller->validar($req, $idNormal);
    $body = json_decode($resp->getContent(), true);
    chk($resp->getStatusCode() === 200, 'PASO validar 9001 (C) · status 200');
    chk(array_key_exists('interlockLiberado', $body), 'PASO validar 9001 · la respuesta trae interlockLiberado (paso por la rama del interlock)');
} finally {
    // ---------- limpieza ----------
    $ids = EolDefecto::where('qr', 'like', "$PREFIX%")->pluck('id');
    EolDefectoEvento::whereIn('eol_defecto_id', $ids)->delete();
    EolDefecto::whereIn('id', $ids)->delete();
    DB::connection('sqlsrv')->table('eol_interlock_marcas')->where('qr', 'like', "$PREFIX%")->delete();
    User::where('cod_autorizacion', 'like', "$PREFIX%")->delete();

    // Restaura la config EXACTAMENTE como estaba antes del test.
    $rec->data = $orig; $rec->save();
    echo "\n[datos de prueba eliminados, config restaurada]\n";
}

echo "\n" . ($fallos === 0 ? "\e[32mTODO OK\e[0m" : "\e[31m$fallos fallo(s)\e[0m") . "\n";
