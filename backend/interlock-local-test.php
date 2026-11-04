<?php
/**
 * PRUEBA LOCAL del flujo interlock completo (sin tocar JITMS).
 * Replica el indice unico y simula el NG del PLC.
 *
 *   1) defecto abierto      -> cargarPendiente()  INSERT OK (DEFECTO PENDIENTE) con el
 *                              nombre del operario de calidad en Usuario
 *   2) escaneo ultimo puesto-> (simulado) INSERT NG  (etiqueta repetida del PLC)
 *   3) qrsConOkYNg          -> detecta OK+NG (dispara el andon rojo)
 *   4) calidad VALIDA       -> confirmarRetrabajo() BORRA nuestro OK y deja la NG del PLC
 *                              con Mensaje='DEFECTO RETRABAJADO OK - <inspector>'
 *
 *   php interlock-local-test.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FactoryConfig;
use App\Services\InterlockService;
use Illuminate\Support\Facades\DB;

$local = DB::connection('sqlsrv');
$QR = 'TEST-LOCAL-260625-C3J-LH-0001';
$T = 'produccion.historicocontrolcalidad';

function ok($b) { return $b ? "\e[32mOK\e[0m" : "\e[31mFALLA\e[0m"; }
function ver($local, $T, $QR, $t) {
    echo "\n-- historico ($t) --\n";
    $rows = $local->table($T)->where('CodigoEscaneado', $QR)->orderBy('IdHistoricoControlCalidad')->get();
    if ($rows->isEmpty()) { echo "   (sin filas) -> asiento limpio\n"; return; }
    foreach ($rows as $r) echo "   id={$r->IdHistoricoControlCalidad} | {$r->PosicionMano} | Estado={$r->Estado} | '{$r->Mensaje}' | {$r->Usuario}\n";
}

echo "=============================================================\n";
echo "  PRUEBA LOCAL FLUJO INTERLOCK COMPLETO (EOL_TBAR)\n";
echo "=============================================================\n";

$local->statement("IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name='produccion') EXEC('CREATE SCHEMA produccion')");
$local->statement("IF OBJECT_ID('produccion.interlock','U') IS NULL CREATE TABLE produccion.interlock (Id bigint IDENTITY(1,1) PRIMARY KEY, FechaHora datetime NULL, Kanban nvarchar(45) NULL, Puesto nvarchar(20) NULL, Resultado nvarchar(2) NULL, FechaKanban date NULL, Secuencia int NULL, SD nvarchar(3) NULL, Mano nvarchar(2) NULL)");
$local->statement("IF OBJECT_ID('produccion.historicocontrolcalidad','U') IS NULL CREATE TABLE produccion.historicocontrolcalidad (IdHistoricoControlCalidad bigint IDENTITY(1,1) PRIMARY KEY, Secuencia int, FechaKanban date, FechaEscaneado datetime, SD varchar(4), PosicionMano varchar(16), CodigoEscaneado varchar(45), Estado varchar(3), Mensaje varchar(512) NULL, Usuario varchar(50) NULL)");
$local->statement("IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='NCIX_Secuencia_FechaKanban_PosicionMano_Estado') CREATE UNIQUE INDEX NCIX_Secuencia_FechaKanban_PosicionMano_Estado ON produccion.historicocontrolcalidad (Secuencia, FechaKanban, PosicionMano, Estado)");

$local->table('produccion.interlock')->where('Kanban', $QR)->delete();
$local->table($T)->where('CodigoEscaneado', $QR)->delete();
// Marcas de corridas anteriores: si quedan pendientes, el confirmarRetrabajo de este
// test intentaria revertir ids que ya no existen y el resultado no seria limpio.
$local->table('eol_interlock_marcas')->where('qr', $QR)->delete();
$hoy = date('Y-m-d');
$local->table('produccion.interlock')->insert(['FechaHora' => now(), 'Kanban' => $QR, 'Puesto' => 'FR 14', 'Resultado' => 'OK', 'FechaKanban' => $hoy, 'Secuencia' => 9999, 'SD' => 'C3J', 'Mano' => 'LH']);

$rec = FactoryConfig::where('key', 'active')->first();
$cfg = $rec->data; $orig = $cfg;
$cfg['settings']['interlockConnection'] = 'sqlsrv';
$cfg['settings']['plcInterlockEnabled'] = true;
$rec->data = $cfg; $rec->save();

$MARCADOR  = 'A.PEREZ';
$INSPECTOR = 'M.LOPEZ';

$fallos = 0;
try {
    // 1) defecto abierto -> cargarPendiente (INSERT OK con el operario de calidad)
    InterlockService::cargarPendiente($QR, $MARCADOR);
    ver($local, $T, $QR, 'tras abrir defecto');
    $nuestro = $local->table($T)->where('CodigoEscaneado', $QR)->where('Estado', 'OK')->first();
    $conUsuario = $nuestro && $nuestro->Usuario === $MARCADOR;
    echo "\nPASO marcar · Usuario = operario ($MARCADOR), no 'EOL'  [" . ok($conUsuario) . "]\n";
    if (!$conUsuario) $fallos++;

    // 2) simulo el escaneo del ultimo puesto: el PLC ve el OK y escribe NG
    $cl = $local->table($T)->where('CodigoEscaneado', $QR)->first();
    $local->table($T)->insert(['Secuencia' => $cl->Secuencia, 'FechaKanban' => $cl->FechaKanban, 'FechaEscaneado' => now(), 'SD' => $cl->SD, 'PosicionMano' => $cl->PosicionMano, 'CodigoEscaneado' => $QR, 'Estado' => 'NG', 'Mensaje' => 'Asiento previamente escaneado', 'Usuario' => 'G.QUISPE']);
    ver($local, $T, $QR, 'tras escaneo ultimo puesto (PLC escribe NG)');
    $ngPlc = $local->table($T)->where('CodigoEscaneado', $QR)->where('Estado', 'NG')->first();

    // 3) deteccion del andon
    $alerta = in_array($QR, InterlockService::qrsConOkYNg([$QR]), true);
    echo "\nPASO andon · detecta OK+NG (pantallazo rojo)   [" . ok($alerta) . "]\n";
    if (!$alerta) $fallos++;

    // 4) calidad VALIDA -> borra nuestro OK y deja el rastro en la NG del PLC
    InterlockService::confirmarRetrabajo($QR, $INSPECTOR);
    ver($local, $T, $QR, 'tras VALIDAR (sin OK, NG con el rastro)');

    $filas  = $local->table($T)->where('CodigoEscaneado', $QR)->get();
    $sinOk  = $filas->where('Estado', 'OK')->isEmpty();
    echo "\nPASO validar · nuestro OK borrado              [" . ok($sinOk) . "]\n";
    if (!$sinOk) $fallos++;

    // La NG del PLC tiene que seguir siendo la MISMA fila (mismo id, sigue en NG):
    // pasarla a otro Estado o borrarla es justo lo que no se puede hacer.
    $ng = $local->table($T)->where('IdHistoricoControlCalidad', $ngPlc->IdHistoricoControlCalidad)->first();
    $ngViva = $ng && $ng->Estado === 'NG';
    echo "PASO validar · NG del PLC viva y sigue en NG   [" . ok($ngViva) . "]\n";
    if (!$ngViva) $fallos++;

    $rastro = $ng && $ng->Mensaje === "DEFECTO RETRABAJADO OK - $INSPECTOR";
    echo "PASO validar · NG con rastro del inspector     [" . ok($rastro) . "]\n";
    if (!$rastro) $fallos++;

    // 5) re-escaneo: sin OK previo el PLC puede escribir un OK nuevo -> etiqueta valida.
    // Es lo que garantiza que el asiento no quede trabado despues de validar.
    try {
        $local->table($T)->insert(['Secuencia' => $cl->Secuencia, 'FechaKanban' => $cl->FechaKanban, 'FechaEscaneado' => now(), 'SD' => $cl->SD, 'PosicionMano' => $cl->PosicionMano, 'CodigoEscaneado' => $QR, 'Estado' => 'OK', 'Mensaje' => '', 'Usuario' => 'G.QUISPE']);
        $reescaneo = true;
    } catch (\Throwable $e) {
        $reescaneo = false;
        echo "   -> " . $e->getMessage() . "\n";
    }
    ver($local, $T, $QR, 'tras re-escaneo del PLC');
    echo "\nPASO re-escaneo · el PLC pudo escribir OK nuevo [" . ok($reescaneo) . "]\n";
    if (!$reescaneo) $fallos++;

    // ==========================================================================
    // ESCENARIO 2: el asiento YA tiene un OK del control real (fila AJENA).
    // Es la rama riesgosa: no insertamos nada, solo le pisamos el Mensaje, y al
    // validar hay que devolverselo intacto sin borrar la fila.
    // ==========================================================================
    echo "\n=============================================================\n";
    echo "  ESCENARIO 2: OK preexistente del control real (fila ajena)\n";
    echo "=============================================================\n";

    $QR2 = 'TEST-LOCAL-260625-C3J-RH-0002';
    $local->table('produccion.interlock')->where('Kanban', $QR2)->delete();
    $local->table($T)->where('CodigoEscaneado', $QR2)->delete();
    $local->table('eol_interlock_marcas')->where('qr', $QR2)->delete();
    $local->table('produccion.interlock')->insert(['FechaHora' => now(), 'Kanban' => $QR2, 'Puesto' => 'FR 14', 'Resultado' => 'OK', 'FechaKanban' => $hoy, 'Secuencia' => 9998, 'SD' => 'C3J', 'Mano' => 'RH']);

    // El control real ya escaneo el asiento y dejo su OK con SU mensaje.
    $MSG_REAL = 'Control OK turno noche';
    $local->table($T)->insert(['Secuencia' => 9998, 'FechaKanban' => $hoy, 'FechaEscaneado' => now(), 'SD' => 'C3J', 'PosicionMano' => 'FR RH', 'CodigoEscaneado' => $QR2, 'Estado' => 'OK', 'Mensaje' => $MSG_REAL, 'Usuario' => 'J.SOSA']);
    $okAjeno = $local->table($T)->where('CodigoEscaneado', $QR2)->first();

    InterlockService::cargarPendiente($QR2, $MARCADOR);
    ver($local, $T, $QR2, 'tras abrir defecto (marca el OK ajeno)');

    $marcado = $local->table($T)->where('IdHistoricoControlCalidad', $okAjeno->IdHistoricoControlCalidad)->first();
    $noInserto  = $local->table($T)->where('CodigoEscaneado', $QR2)->count() === 1;
    $pisoMsg    = $marcado->Mensaje === 'DEFECTO PENDIENTE';
    $dejoUsuario = $marcado->Usuario === 'J.SOSA';
    echo "\nPASO ajeno · no inserto otra fila              [" . ok($noInserto) . "]\n";
    echo "PASO ajeno · le piso el Mensaje                [" . ok($pisoMsg) . "]\n";
    echo "PASO ajeno · conservo el Usuario real          [" . ok($dejoUsuario) . "]\n";
    if (!$noInserto) $fallos++;
    if (!$pisoMsg) $fallos++;
    if (!$dejoUsuario) $fallos++;

    // El PLC escanea y genera la NG.
    $local->table($T)->insert(['Secuencia' => 9998, 'FechaKanban' => $hoy, 'FechaEscaneado' => now(), 'SD' => 'C3J', 'PosicionMano' => 'FR RH', 'CodigoEscaneado' => $QR2, 'Estado' => 'NG', 'Mensaje' => 'Asiento previamente escaneado', 'Usuario' => 'G.QUISPE']);

    InterlockService::confirmarRetrabajo($QR2, $INSPECTOR);
    ver($local, $T, $QR2, 'tras VALIDAR (OK ajeno restaurado)');

    $post = $local->table($T)->where('IdHistoricoControlCalidad', $okAjeno->IdHistoricoControlCalidad)->first();
    $sigueViva  = $post !== null && $post->Estado === 'OK';
    $restaurado = $post !== null && $post->Mensaje === $MSG_REAL;
    echo "\nPASO ajeno · el OK real NO se borro            [" . ok($sigueViva) . "]\n";
    echo "PASO ajeno · Mensaje original restaurado       [" . ok($restaurado) . "]\n";
    if (!$sigueViva) $fallos++;
    if (!$restaurado) $fallos++;

    $ng2 = $local->table($T)->where('CodigoEscaneado', $QR2)->where('Estado', 'NG')->first();
    $rastro2 = $ng2 && $ng2->Mensaje === "DEFECTO RETRABAJADO OK - $INSPECTOR";
    echo "PASO ajeno · NG con rastro del inspector       [" . ok($rastro2) . "]\n";
    if (!$rastro2) $fallos++;
} finally {
    // Restaura la config EXACTAMENTE como estaba antes del test (no pisa el flag de produccion)
    $rec->data = $orig; $rec->save();
    echo "\n[config restaurada al estado original]\n";
}

echo "\n" . ($fallos === 0 ? "\e[32mTODO OK\e[0m" : "\e[31m$fallos fallo(s)\e[0m") . "\n";
