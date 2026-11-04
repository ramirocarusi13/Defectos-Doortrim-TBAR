<?php
/**
 * AUDITORIA DEL INTERLOCK CONTRA LA BASE REAL (JITMS).
 *
 * Es SOLO LECTURA sobre historicocontrolcalidad: no inserta, no borra, no actualiza nada.
 * Sirve para probar el ciclo en la linea con la base de produccion y demostrar que EOL
 * solo borro la fila que inserto el propio sistema.
 *
 * USO
 *   1) ANTES de marcar el defecto en la tablet:
 *        php interlock-auditoria.php snapshot <QR>
 *      Guarda en storage/ el estado exacto: conteos globales de la tabla + todas las
 *      filas de ese asiento.
 *
 *   2) Hacer el ciclo real: marcar -> escanear en el ultimo puesto -> retrabajar -> validar.
 *
 *   3) DESPUES:
 *        php interlock-auditoria.php comparar <QR>
 *      Compara contra el snapshot y dice fila por fila que se inserto, que se borro y que
 *      se modifico, y si el total de la tabla cambio mas de lo esperado.
 *
 * Tambien:
 *   php interlock-auditoria.php marcas <QR>    -> que ids registro EOL para ese asiento
 *   php interlock-auditoria.php riesgo         -> filas de produccion que hoy matchearian
 *                                                 los filtros del servicio (deberia dar 0)
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\InterlockService;
use Illuminate\Support\Facades\DB;

const TABLA   = 'produccion.historicocontrolcalidad';
const MENSAJE = 'DEFECTO PENDIENTE';

$accion = $argv[1] ?? '';
$QR     = $argv[2] ?? '';

$conexion = InterlockService::conexion();
$con      = DB::connection($conexion);
$dir      = __DIR__ . '/storage/app/interlock-auditoria';

function c($txt, $color) { return "\e[{$color}m{$txt}\e[0m"; }
function verde($t) { return c($t, 32); }
function rojo($t)  { return c($t, 31); }
function amar($t)  { return c($t, 33); }

/** Conteos globales de la tabla: si alguno baja de mas, se borro algo que no era nuestro. */
function totales($con): array
{
    return [
        'total' => (int) $con->table(TABLA)->count(),
        'ok'    => (int) $con->table(TABLA)->where('Estado', 'OK')->count(),
        'ng'    => (int) $con->table(TABLA)->where('Estado', 'NG')->count(),
    ];
}

function filasDelQr($con, string $qr): array
{
    return $con->table(TABLA)
        ->where('CodigoEscaneado', $qr)
        ->orderBy('IdHistoricoControlCalidad')
        ->get()
        ->keyBy('IdHistoricoControlCalidad')
        ->map(fn ($r) => (array) $r)
        ->all();
}

function pintarFila(array $r): string
{
    return sprintf(
        "id=%-10s %-8s %-4s %-45s '%s'",
        $r['IdHistoricoControlCalidad'],
        $r['PosicionMano'] ?? '',
        $r['Estado'] ?? '',
        $r['Usuario'] ?? '',
        $r['Mensaje'] ?? ''
    );
}

echo "\n=============================================================\n";
echo "  AUDITORIA INTERLOCK  ·  conexion: $conexion  ·  SOLO LECTURA\n";
echo "=============================================================\n";

if ($conexion !== 'sqlsrvsar') {
    echo amar("AVISO: la conexion configurada es '$conexion', no la de JITMS ('sqlsrvsar').\n");
    echo amar("       Estas mirando la copia local, no produccion.\n");
}

if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$archivo = $dir . '/' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $QR) . '.json';

switch ($accion) {
    // -----------------------------------------------------------------
    case 'snapshot':
        if ($QR === '') { exit(rojo("Falta el QR.\n")); }

        $snap = ['qr' => $QR, 'cuando' => date('c'), 'totales' => totales($con), 'filas' => filasDelQr($con, $QR)];
        file_put_contents($archivo, json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo "\nQR: $QR\n";
        echo "Totales de la tabla -> total={$snap['totales']['total']}  OK={$snap['totales']['ok']}  NG={$snap['totales']['ng']}\n";
        echo "\nFilas del asiento (" . count($snap['filas']) . "):\n";
        foreach ($snap['filas'] as $r) { echo '   ' . pintarFila($r) . "\n"; }
        if (empty($snap['filas'])) { echo "   (ninguna todavia)\n"; }
        echo "\n" . verde("Snapshot guardado") . " en $archivo\n";
        echo "Ahora hace el ciclo completo en la tablet y despues corre:\n";
        echo "   php interlock-auditoria.php comparar $QR\n";
        break;

    // -----------------------------------------------------------------
    case 'comparar':
        if ($QR === '') { exit(rojo("Falta el QR.\n")); }
        if (!is_file($archivo)) { exit(rojo("No hay snapshot para $QR. Corre 'snapshot' primero.\n")); }

        $snap  = json_decode(file_get_contents($archivo), true);
        $antes = $snap['filas'];
        $ahora = filasDelQr($con, $QR);
        $tAntes = $snap['totales'];
        $tAhora = totales($con);

        echo "\nQR: $QR   (snapshot del {$snap['cuando']})\n";

        $borradas   = array_diff_key($antes, $ahora);
        $insertadas = array_diff_key($ahora, $antes);
        $modificadas = [];
        foreach (array_intersect_key($antes, $ahora) as $id => $a) {
            if (($a['Estado'] ?? null) !== ($ahora[$id]['Estado'] ?? null)
                || (string) ($a['Mensaje'] ?? '') !== (string) ($ahora[$id]['Mensaje'] ?? '')
                || (string) ($a['Usuario'] ?? '') !== (string) ($ahora[$id]['Usuario'] ?? '')) {
                $modificadas[$id] = [$a, $ahora[$id]];
            }
        }

        // Ids que EOL declaro haber tocado para este asiento.
        $marcas = DB::connection('sqlsrv')->table('eol_interlock_marcas')->where('qr', $QR)->get();
        $idsPropios = $marcas->pluck('jitms_id')->map(fn ($v) => (int) $v)->all();
        $idsInsertados = $marcas->where('accion', 'INSERT')->pluck('jitms_id')->map(fn ($v) => (int) $v)->all();

        echo "\n--- FILAS DEL ASIENTO ---\n";
        echo "\nInsertadas (" . count($insertadas) . "):\n";
        foreach ($insertadas as $r) { echo '   + ' . pintarFila($r) . "\n"; }
        if (!$insertadas) { echo "   (ninguna)\n"; }

        echo "\nBorradas (" . count($borradas) . "):\n";
        foreach ($borradas as $id => $r) {
            $propia = in_array((int) $id, $idsInsertados, true);
            echo '   - ' . pintarFila($r) . '   ' . ($propia ? verde('[la insertamos nosotros]') : rojo('[NO ERA NUESTRA]')) . "\n";
        }
        if (!$borradas) { echo "   (ninguna)\n"; }

        echo "\nModificadas (" . count($modificadas) . "):\n";
        foreach ($modificadas as $id => [$a, $b]) {
            $declarada = in_array((int) $id, $idsPropios, true);
            echo '   ~ ' . pintarFila($b) . '   ' . ($declarada ? verde('[declarada en la auditoria]') : rojo('[NO DECLARADA]')) . "\n";
            echo "       antes: Estado={$a['Estado']} Mensaje='{$a['Mensaje']}' Usuario='{$a['Usuario']}'\n";
            echo "       ahora: Estado={$b['Estado']} Mensaje='{$b['Mensaje']}' Usuario='{$b['Usuario']}'\n";
        }
        if (!$modificadas) { echo "   (ninguna)\n"; }

        // --- VEREDICTO ---
        echo "\n--- VEREDICTO ---\n";
        $fallos = 0;

        // 1) Todo lo borrado tiene que ser fila que insertamos nosotros.
        $ajenasBorradas = array_filter(array_keys($borradas), fn ($id) => !in_array((int) $id, $idsInsertados, true));
        $ok1 = empty($ajenasBorradas);
        echo ($ok1 ? verde('[OK]   ') : rojo('[FALLA]')) . " solo se borraron filas insertadas por EOL\n";
        if (!$ok1) { $fallos++; echo rojo('        ids ajenos borrados: ' . implode(', ', $ajenasBorradas)) . "\n"; }

        // 2) Ninguna NG se perdio.
        $ngBorradas = array_filter($borradas, fn ($r) => strtoupper((string) $r['Estado']) === 'NG');
        $ok2 = empty($ngBorradas);
        echo ($ok2 ? verde('[OK]   ') : rojo('[FALLA]')) . " ninguna NG fue borrada\n";
        if (!$ok2) { $fallos++; }

        // 3) El delta global de la tabla no puede ser peor que lo del asiento.
        //    Si bajaron filas de OTROS asientos, aparece aca.
        $deltaGlobal = $tAhora['total'] - $tAntes['total'];
        $deltaAsiento = count($insertadas) - count($borradas);
        $ok3 = $deltaGlobal >= $deltaAsiento;
        echo ($ok3 ? verde('[OK]   ') : rojo('[FALLA]')) . " delta global ($deltaGlobal) consistente con el del asiento ($deltaAsiento)\n";
        echo "        total: {$tAntes['total']} -> {$tAhora['total']}   |   OK: {$tAntes['ok']} -> {$tAhora['ok']}   |   NG: {$tAntes['ng']} -> {$tAhora['ng']}\n";
        if (!$ok3) { $fallos++; echo rojo("        Bajaron filas de OTROS asientos. Revisar ya.\n"); }

        // 4) El conteo global de NG no puede bajar.
        $ok4 = $tAhora['ng'] >= $tAntes['ng'];
        echo ($ok4 ? verde('[OK]   ') : rojo('[FALLA]')) . " el total de NG de la tabla no bajo\n";
        if (!$ok4) { $fallos++; }

        // 5) Nada modificado sin estar declarado en eol_interlock_marcas.
        $noDeclaradas = array_filter(array_keys($modificadas), fn ($id) => !in_array((int) $id, $idsPropios, true));
        $ok5 = empty($noDeclaradas);
        echo ($ok5 ? verde('[OK]   ') : rojo('[FALLA]')) . " toda modificacion quedo declarada en eol_interlock_marcas\n";
        if (!$ok5) { $fallos++; echo rojo('        ids no declarados: ' . implode(', ', $noDeclaradas)) . "\n"; }

        // 6) No quedo ningun OK con nuestro mensaje colgado.
        $colgados = array_filter($ahora, fn ($r) => (string) $r['Mensaje'] === MENSAJE);
        $ok6 = empty($colgados);
        echo ($ok6 ? verde('[OK]   ') : amar('[AVISO]')) . " no quedo ningun 'DEFECTO PENDIENTE' colgado en el asiento\n";
        foreach ($colgados as $r) { echo amar('        ' . pintarFila($r)) . "\n"; }

        echo "\n" . ($fallos === 0
            ? verde('SIN PERDIDA DE DATOS: el sistema solo toco lo suyo.')
            : rojo("$fallos CHEQUEO(S) EN ROJO - no seguir hasta revisarlo.")) . "\n";
        break;

    // -----------------------------------------------------------------
    case 'marcas':
        $q = DB::connection('sqlsrv')->table('eol_interlock_marcas')->orderByDesc('id');
        if ($QR !== '') { $q->where('qr', $QR); }
        $rows = $q->limit(50)->get();

        echo "\nMarcas registradas por EOL" . ($QR !== '' ? " para $QR" : ' (ultimas 50)') . ":\n\n";
        foreach ($rows as $m) {
            $estado = $m->revertido_at ? 'revertida' : amar('PENDIENTE');
            echo sprintf("   #%-5s %-30s jitms_id=%-10s %-7s %-10s  usuario='%s'  msg_orig='%s'\n",
                $m->id, $m->qr, $m->jitms_id, $m->accion, $estado, $m->usuario_fila, $m->mensaje_original);
        }
        if ($rows->isEmpty()) { echo "   (ninguna)\n"; }
        break;

    // -----------------------------------------------------------------
    // Cuantas filas de produccion matchearian hoy los criterios del servicio.
    // Con el codigo actual el unico borrado va por clave primaria registrada, asi que
    // esto es un control de contexto: si da > 0 hay filas ajenas con nuestro mensaje.
    case 'riesgo':
        $conNuestroMsg = (int) $con->table(TABLA)->where('Mensaje', MENSAJE)->count();
        $usuarioEol    = (int) $con->table(TABLA)->where('Usuario', 'EOL')->count();
        $pendientes    = (int) DB::connection('sqlsrv')->table('eol_interlock_marcas')->whereNull('revertido_at')->count();

        echo "\nFilas en produccion con Mensaje='" . MENSAJE . "' : $conNuestroMsg\n";
        echo "Filas en produccion con Usuario='EOL'                : $usuarioEol\n";
        echo "Marcas de EOL pendientes de revertir                 : $pendientes\n\n";
        echo "El borrado del servicio va SIEMPRE por IdHistoricoControlCalidad registrado\n";
        echo "en eol_interlock_marcas, asi que estos numeros son informativos: una fila\n";
        echo "ajena con el mismo mensaje no puede ser alcanzada.\n";
        break;

    // -----------------------------------------------------------------
    default:
        echo "\nUso:\n";
        echo "   php interlock-auditoria.php snapshot <QR>    (antes de marcar)\n";
        echo "   php interlock-auditoria.php comparar <QR>    (despues de validar)\n";
        echo "   php interlock-auditoria.php marcas [QR]\n";
        echo "   php interlock-auditoria.php riesgo\n";
}

echo "\n";
