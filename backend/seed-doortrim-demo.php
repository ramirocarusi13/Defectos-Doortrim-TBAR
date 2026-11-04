<?php
/**
 * Piezas de DEMO para probar la tablet DOORTRIM DEFECTOS contra la base LOCAL.
 * Todas tienen QR que empieza con DEMO- (se pueden tipear en la tablet).
 *
 *   php seed-doortrim-demo.php          -> crea (o resetea) las piezas demo
 *   php seed-doortrim-demo.php --borrar -> borra piezas demo, sus cambios y defectos
 *
 * Door trim de prueba: escanear/tipear DEMO-DOORTRIM-01 y elegir a mano el
 * modelo "PANEL ASSY FR LH M1".
 *
 *   Sale (montadas)          Entra OK (buffer)      Entran MAL (para ver el rechazo)
 *   DEMO-PANEL-SALE          DEMO-PANEL-OK          DEMO-PANEL-RH   (otro modelo y lado)
 *   DEMO-ARM-SALE            DEMO-ARM-OK            DEMO-PANEL-CURA (todavia curando)
 *   DEMO-GAR-SALE            DEMO-GAR-OK
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EolDefecto;
use App\Models\EolDefectoEvento;
use App\Models\EolDoor;
use App\Models\EolPieza;
use App\Models\EolPiezaEvento;

$sets = ['DEMO-DOORTRIM-01', 'DEMO-DOORTRIM-02', 'DEMO-DOORTRIM-03'];

// QR con formato real (resuelven contra JITMS local): los door trims de prueba de
// qr-prueba-doortrim.html y las piezas de buffer que los reemplazan. Si un cambio
// de pieza los dio de alta como pieza, se limpian (los defectos NO se tocan).
$qrsReales = [
    '261030100421A00P1A000001212201001101001110', '261030100431A00P1A000001111201001101001110',
    '261102100101A00A1A000001212201001101001110', '261102100121A00A1A000001111201001101001110',
    '26110210011DC00GDC000002422101001011011101', '26110210013DC00GDC000002321102001001001101',
    '261030100521A00P1A000001212201001101001110', '261102100201A00A1A000001212201001101001110',
    '26110210021DC00GDC000002422101001011011101',
];

$borrar = function () use ($sets, $qrsReales) {
    $piezas = EolPieza::where('pieza_qr', 'like', 'DEMO-%')->orWhereIn('pieza_qr', $qrsReales)->get();
    EolPiezaEvento::whereIn('pieza_id', $piezas->pluck('id'))->delete();
    EolDoor::where('pieza_retirada_qr', 'like', 'DEMO-%')->orWhere('pieza_entregada_qr', 'like', 'DEMO-%')->orWhereIn('qr', $sets)
        ->orWhereIn('pieza_retirada_qr', $qrsReales)->orWhereIn('pieza_entregada_qr', $qrsReales)->delete();
    $defIds = EolDefecto::whereIn('qr', $sets)->pluck('id');
    EolDefectoEvento::whereIn('eol_defecto_id', $defIds)->delete();
    EolDefecto::whereIn('id', $defIds)->delete();
    EolPieza::whereIn('id', $piezas->pluck('id'))->delete();
    echo "Piezas demo borradas: {$piezas->count()}, defectos: {$defIds->count()}\n";
};

$borrar();
if (in_array('--borrar', $argv, true)) {
    exit(0);
}

$base = ['linea' => 'DOORTRIM', 'alta_at' => now(), 'ultimo_evento_at' => now(), 'alta_por_nombre' => 'DEMO'];
$piezas = [
    ['DEMO-PANEL-SALE', 'PANEL',    '67620-0KB80',    'LH', 'EN_ASIENTO', null],
    ['DEMO-PANEL-OK',   'PANEL',    '67620-0KB80',    'LH', 'DISPONIBLE', null],
    ['DEMO-PANEL-RH',   'PANEL',    '67610-0KB80',    'RH', 'DISPONIBLE', null],
    ['DEMO-PANEL-CURA', 'PANEL',    '67620-0KB80',    'LH', 'DISPONIBLE', now()->addHours(3)],
    ['DEMO-ARM-SALE',   'ARM_REST', '74220-0K140-C0', 'LH', 'EN_ASIENTO', null],
    ['DEMO-ARM-OK',     'ARM_REST', '74220-0K140-C0', 'LH', 'DISPONIBLE', null],
    ['DEMO-GAR-SALE',   'GARNISH',  '67674-KK010',    'LH', 'EN_ASIENTO', null],
    ['DEMO-GAR-OK',     'GARNISH',  '67674-KK010',    'LH', 'DISPONIBLE', null],
    // Piezas de buffer con QR real: Calidad las puede escanear al reinspeccionar.
    ['261030100521A00P1A000001212201001101001110', 'PANEL',    '67620-0KB80',    'LH', 'DISPONIBLE', null],
    ['261102100201A00A1A000001212201001101001110', 'ARM_REST', '74220-0K140-C0', 'LH', 'DISPONIBLE', null],
    ['26110210021DC00GDC000002422101001011011101', 'GARNISH',  '67674-KK010',    'LH', 'DISPONIBLE', null],
];
foreach ($piezas as [$qr, $tipo, $modelo, $lado, $estado, $cura]) {
    EolPieza::create($base + [
        'pieza_qr' => $qr, 'tipo_pieza' => $tipo, 'modelo' => $modelo, 'lado' => $lado,
        'estado' => $estado, 'disponible_desde' => $cura,
        'buffer_id' => $estado === 'DISPONIBLE' ? 'buffer-demo' : null,
        'buffer_nombre' => $estado === 'DISPONIBLE' ? 'Buffer DEMO' : null,
    ]);
    echo "  $qr  $tipo  $modelo  $lado  $estado" . ($cura ? '  (curando)' : '') . "\n";
}
echo "Listo: " . count($piezas) . " piezas demo.\n";
