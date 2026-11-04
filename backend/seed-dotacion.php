<?php
/**
 * SEMILLA: puestos de trabajo de la linea + dotacion de operarios de un turno,
 * para el paso "¿DONDE SE GENERO EL DEFECTO?" del retrabajo (puesto -> turno ->
 * operario) y el tablero en tiempo real (/reporte-origen).
 *
 * Toma un JSON con la forma del Excel "MANNING SEAT - <TURNO>":
 *   { "puestos":   [ { "sector": "FRONT", "codigo": "FR 3", "nombre": "Engrampado Back" }, ... ],
 *     "operarios": [ { "legajo": "2236", "nombre": "Abalsamo Julian Agustin" }, ... ] }
 *
 *   php seed-dotacion.php database/data/manning_seat_amarillo.json A
 *
 * Turno: A = AMARILLO, B = BLANCO, N = NARANJA (mismo criterio que el reporte QC).
 *
 * Idempotente y aditivo (mismo criterio que seed-componentes.php):
 *  - puestosTrabajo: clave = codigo normalizado. Si ya existe NO lo toca.
 *  - dotacion: clave = legajo + turno (o nombre + turno si el Excel no trae
 *    legajo, como el manning del turno Naranja). Si ya existe NO lo toca.
 *  Nunca borra ni desactiva nada. Lo demas se edita en /config -> Dotacion.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FactoryConfig;

const CONFIG_KEY = 'active';

$archivo = $argv[1] ?? (__DIR__ . '/database/data/manning_seat_amarillo.json');
$turno   = strtoupper(trim($argv[2] ?? 'A'));
$turno   = ['AMARILLO' => 'A', 'BLANCO' => 'B', 'NARANJA' => 'N'][$turno] ?? $turno;

if (!in_array($turno, ['A', 'B', 'N'], true)) {
    fwrite(STDERR, "Turno invalido '$turno' (use A, B o N)\n");
    exit(1);
}
if (!is_file($archivo)) {
    $archivo = __DIR__ . '/' . ltrim($archivo, '/\\');
}
$json = is_file($archivo) ? json_decode(file_get_contents($archivo), true) : null;
if (!is_array($json)) {
    fwrite(STDERR, "No se pudo leer el JSON: $archivo\n");
    exit(1);
}

$normCodigo = fn ($v) => strtoupper(preg_replace('/\s+/', ' ', trim((string) $v)));

$row  = FactoryConfig::query()->where('key', CONFIG_KEY)->first();
$data = $row?->data ?: [];

$puestos  = array_values($data['puestosTrabajo'] ?? []);
$dotacion = array_values($data['dotacion'] ?? []);

// ---- Puestos de trabajo ----
$existentesPuesto = [];
$maxOrden = 0;
foreach ($puestos as $p) {
    $existentesPuesto[$normCodigo($p['codigo'] ?? '')] = true;
    $maxOrden = max($maxOrden, (int) ($p['orden'] ?? 0));
}

$puestosAgregados = 0;
foreach ($json['puestos'] ?? [] as $p) {
    $codigo = preg_replace('/\s+/', ' ', trim((string) ($p['codigo'] ?? '')));
    if ($codigo === '' || isset($existentesPuesto[$normCodigo($codigo)])) {
        continue;
    }
    $maxOrden++;
    $puestos[] = [
        'id'     => 'pt-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $codigo)),
        'codigo' => $codigo,
        'nombre' => preg_replace('/\s+/', ' ', trim((string) ($p['nombre'] ?? ''))),
        'sector' => strtoupper(trim((string) ($p['sector'] ?? ''))),
        'orden'  => $maxOrden,
        'active' => true,
    ];
    $existentesPuesto[$normCodigo($codigo)] = true;
    $puestosAgregados++;
}

// ---- Dotacion (operarios del turno) ----
// Clave de un operario dentro del turno: legajo si hay; si no, el nombre normalizado.
$claveOp = function (string $legajo, string $nombre, string $turno): string {
    $id = $legajo !== '' ? 'L:' . $legajo : 'N:' . mb_strtoupper(preg_replace('/\s+/', ' ', trim($nombre)));
    return $id . '|' . strtoupper($turno);
};
$slug = fn (string $v) => trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v) ?: $v)), '-');

$existentesOp = [];
foreach ($dotacion as $o) {
    $existentesOp[$claveOp(trim((string) ($o['legajo'] ?? '')), (string) ($o['nombre'] ?? ''), (string) ($o['turno'] ?? ''))] = true;
}

$opAgregados = 0;
foreach ($json['operarios'] ?? [] as $o) {
    $legajo = trim((string) ($o['legajo'] ?? ''));
    $nombre = preg_replace('/\s+/', ' ', trim((string) ($o['nombre'] ?? '')));
    $clave  = $claveOp($legajo, $nombre, $turno);
    if ($nombre === '' || isset($existentesOp[$clave])) {
        continue;
    }
    $dotacion[] = [
        'id'     => 'op-' . $turno . '-' . ($legajo !== '' ? $legajo : $slug($nombre)),
        'legajo' => $legajo,
        'nombre' => $nombre,
        'turno'  => $turno,
        'active' => true,
    ];
    $existentesOp[$clave] = true;
    $opAgregados++;
}

$data['puestosTrabajo'] = $puestos;
$data['dotacion']       = $dotacion;

// updateOrCreate actualiza updated_at -> las tablets ven la nueva version de la
// config y la re-bajan solas.
FactoryConfig::updateOrCreate(['key' => CONFIG_KEY], ['data' => $data]);

echo "=============================================================\n";
echo "  SEED DOTACION (puestos de trabajo + operarios turno $turno)\n";
echo "=============================================================\n";
echo "Archivo: $archivo\n";
echo "Puestos agregados: $puestosAgregados (total en config: " . count($puestos) . ")\n";
echo "Operarios agregados: $opAgregados (total en config: " . count($dotacion) . ")\n";
