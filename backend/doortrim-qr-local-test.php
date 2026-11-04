<?php
/**
 * PRUEBA LOCAL de los QR de la app de camaras (sin JITMS) en la linea de door trims:
 *
 *   1) se interpretan los 3 formatos (panel ; / armrest por posicion / garnish = SD)
 *   2) armrest: cambio por uno curado (alta automatica desde el QR) -> CAMBIADO
 *   3) armrest recien encolado (menos de 4 hs) -> rechazado por curado
 *   4) armrest de la otra mano / otra posicion -> rechazado
 *   5) panel de otro SD -> rechazado; mismo SD, mano y posicion -> CAMBIADO
 *   6) garnish: mismo SD (mismo QR) -> CAMBIADO; otro SD -> rechazado
 *   7) Calidad encuentra el defecto escaneando la pieza que entro
 *   8) la base TB-L (conexion 'tbl') no acepta escrituras
 *
 * Al final borra todo lo que creo.   php doortrim-qr-local-test.php
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
use App\Services\DoorQrService;
use App\Services\DoorService;
use Illuminate\Support\Facades\DB;

$fallos = 0;
function chequear(string $desc, bool $cond): void {
    global $fallos;
    echo 'PASO ' . $desc . '  [' . ($cond ? "\e[32mOK\e[0m" : "\e[31mFALLA\e[0m") . "]\n";
    if (!$cond) $fallos++;
}
function rechaza(callable $f, string $texto): bool {
    try { $f(); return false; } catch (\RuntimeException $e) { return stripos($e->getMessage(), $texto) !== false; }
}

$user = User::query()->first();
$defectosCreados = [];
$qrsPiezas = [];

// QR con el formato real pero que no pueden existir (año 2025, secuencias 99xx, SD ZZ9).
$armSale   = '20250101080000A4LRRRH';
$armEntra  = '20250101090000A4LRRRH';
$armFresco = now()->subHour()->format('YmdHis') . 'A4LRRRH';
$armOtraMano = '20250101100000A4LRRLH';
$armOtraPos  = '20250101110000A4LFRRH';
$panSale   = '9901;RH;FR;P3A';
$panEntra  = '9902;RH;FR;P3A';
$panOtroSd = '9903;RH;FR;P4A';
$garn      = 'ZZ9';
$qrsPiezas = [$armSale, $armEntra, $armFresco, $armOtraMano, $armOtraPos, $panSale, $panEntra, $panOtroSd, $garn];

function marcar(string $qr, string $tipo): int {
    global $user, $defectosCreados;
    $r = DoorService::marcarRetrabajo([
        'qr' => $qr, 'tipo_pieza' => $tipo, 'user' => $user?->id,
        'circles' => [['x' => 0.5, 'y' => 0.5, 'color' => 'EF4444', 'imageId' => 'v1', 'viewName' => 'Frente',
            'falla' => ['id' => 99, 'codigo' => 'P1', 'nombre' => 'Mancha'], 'cantidad' => 1]],
    ]);
    $x = $r['defectos'][0];
    $id = is_object($x) ? $x->id : (is_array($x) ? $x['id'] : (int) $x);
    $defectosCreados[] = $id;
    return $id;
}
function cambiar(string $sale, string $entra, int $defectoId, string $tipo): array {
    global $user;
    return DoorService::cambiar([
        'qr' => $sale, 'tipo_pieza' => $tipo, 'pieza_defectuosa_qr' => $sale, 'pieza_reemplazo_qr' => $entra,
        'defecto_ids' => [$defectoId], 'user' => $user?->id,
    ]);
}

echo "=============================================================\n";
echo "  PRUEBA LOCAL QR DE LA APP DE CAMARAS (door trims)\n";
echo "=============================================================\n";

try {
    // 1) formatos
    $p = DoorQrService::interpretar('0001;RH;FR;P3A');
    chequear('1a panel: tipo, SD, mano, posicion, numero de parte', $p && $p['tipo'] === 'PANEL' && $p['sd'] === 'P3A' && $p['mano'] === 'RH' && $p['posicion'] === 'FR' && $p['secuencia'] === '0001' && $p['codigo'] === '67610-0KC00');
    $a = DoorQrService::interpretar('20260728142712A4LRRRH');
    chequear('1b armrest: fecha de encolado y curado desde el QR', $a && $a['tipo'] === 'ARM_REST' && $a['fecha_encolado'] === '2026-07-28 14:27:12' && $a['curado_fuente'] === 'QR' && str_starts_with($a['curado_hasta'], '2026-07-28T18:27:12') && $a['codigo'] === '74250-0K160-A');
    $g = DoorQrService::interpretar('GDC');
    chequear('1c garnish: el QR es el SD, sin curado', $g && $g['tipo'] === 'GARNISH' && $g['sd'] === 'GDC' && $g['curado_fuente'] === 'NO_APLICA' && count($g['candidatos']) === 2);
    chequear('1d QR de JITMS / basura -> no es door trim', DoorQrService::interpretar('261030100421A00P1A000001212201001101001110') === null && DoorQrService::interpretar('hola mundo') === null);

    // 2) armrest ok
    $d1 = marcar($armSale, 'ARM_REST');
    $r = cambiar($armSale, $armEntra, $d1, 'ARM_REST');
    $entra = EolPieza::where('pieza_qr', $armEntra)->first();
    chequear('2 armrest curado -> CAMBIADO, reemplazo dado de alta desde su QR', EolDefecto::find($d1)->estado === 'CAMBIADO'
        && $entra && $entra->estado === 'EN_ASIENTO' && $entra->modelo === 'A4L' && $entra->lado === 'RH' && $entra->tipo_pieza === 'ARM_REST'
        && EolPieza::where('pieza_qr', $armSale)->value('estado') === 'DEFECTUOSA');

    // 3) curado
    $d2 = marcar($armOtraMano, 'ARM_REST');
    chequear('3 armrest encolado hace 1 h -> rechazado por curado', rechaza(fn () => cambiar($armOtraMano, $armFresco, $d2, 'ARM_REST'), 'curando'));
    chequear('3b y el defecto sigue PENDIENTE', EolDefecto::find($d2)->estado === 'PENDIENTE');

    // 4) mano / posicion
    $qrsPiezas[] = '20250101130000A4LRRRH';
    $d3 = marcar('20250101130000A4LRRRH', 'ARM_REST');
    chequear('4a armrest de otra mano -> rechazado', rechaza(fn () => cambiar('20250101130000A4LRRRH', $armOtraMano, $d3, 'ARM_REST'), 'mano'));
    $d4 = marcar('20250101120000A4LRRRH', 'ARM_REST');
    $qrsPiezas[] = '20250101120000A4LRRRH';
    chequear('4b armrest de otra posicion -> rechazado', rechaza(fn () => cambiar('20250101120000A4LRRRH', $armOtraPos, $d4, 'ARM_REST'), 'posicion'));

    // 5) panel
    $d5 = marcar($panSale, 'PANEL');
    chequear('5a panel de otro SD -> rechazado', rechaza(fn () => cambiar($panSale, $panOtroSd, $d5, 'PANEL'), 'SD'));
    cambiar($panSale, $panEntra, $d5, 'PANEL');
    chequear('5b panel mismo SD/mano/posicion -> CAMBIADO', EolDefecto::find($d5)->estado === 'CAMBIADO');

    // 6) garnish
    $d6 = marcar($garn, 'GARNISH');
    chequear('6a garnish de otro SD -> rechazado', rechaza(fn () => cambiar($garn, 'ZZ8', $d6, 'GARNISH'), 'garnish'));
    $rg = cambiar($garn, $garn, $d6, 'GARNISH');
    $door = $rg['door'];
    chequear('6b garnish mismo SD -> CAMBIADO, sin piezas en el maestro', EolDefecto::find($d6)->estado === 'CAMBIADO'
        && $door->tipo_pieza === 'GARNISH' && $door->pieza_entregada_qr === $garn && !EolPieza::where('pieza_qr', $garn)->exists());

    // 7) Calidad escanea la pieza que entro
    $ctrl = app(App\Http\Controllers\EolDoorController::class);
    $resp = $ctrl->validaciones(Illuminate\Http\Request::create('/', 'GET', ['qr' => $armEntra]))->getData(true);
    $ids = array_column($resp['data'] ?? [], 'id');
    chequear('7 escaneando el armrest que entro aparece el defecto para validar', in_array($d1, $ids));

    // 8) TB-L solo lectura (se rechaza antes de conectarse)
    $bloqueo = false;
    try { DB::connection('tbl')->statement('UPDATE x SET y = 1'); } catch (\Throwable $e) { $bloqueo = str_contains($e->getMessage(), 'solo lectura'); }
    chequear('8 la base TB-L no acepta escrituras', $bloqueo);
} catch (\Throwable $e) {
    $fallos++;
    echo "\e[31mERROR\e[0m " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    echo "\n-- limpieza --\n";
    $doorIds = EolDefecto::whereIn('id', $defectosCreados)->pluck('door_id')->filter();
    EolDefectoEvento::whereIn('eol_defecto_id', $defectosCreados)->delete();
    EolDefecto::whereIn('id', $defectosCreados)->delete();
    EolDoor::whereIn('id', $doorIds)->delete();
    $piezaIds = EolPieza::whereIn('pieza_qr', $qrsPiezas)->pluck('id');
    EolPiezaEvento::whereIn('pieza_id', $piezaIds)->delete();
    EolPieza::whereIn('id', $piezaIds)->delete();
    echo "[datos de prueba eliminados]\n";
}

echo "\n" . ($fallos === 0 ? "\e[32mTODO OK\e[0m" : "\e[31m$fallos fallo(s)\e[0m") . "\n";
exit($fallos === 0 ? 0 : 1);
