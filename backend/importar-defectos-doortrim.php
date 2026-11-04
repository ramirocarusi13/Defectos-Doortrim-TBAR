<?php
/**
 * Importa la planilla de defectos de Door Trim (una hoja por pieza) al catalogo
 * que usa la tablet DOORTRIM DEFECTOS: resources/data/defectos-doortrim.json.
 *
 *   php importar-defectos-doortrim.php "C:\ruta\Lista defectos Door Trim 737D - Rev. 22-10-26.xlsx"
 *
 * Hojas reconocidas (por nombre): "Paneles", "Armrest CON cover", "Armrest SIN cover",
 * "Garnish". Columnas: ID | Rango (A/B/C) | Defecto | Grupo.
 * Cada defecto queda con un codigo corto por pieza (P1, AC1, AS1, G1) que es el que
 * se dibuja sobre la foto. Al cambiar la planilla se vuelve a correr: no hace falta
 * recompilar la app (la tablet baja el catalogo de GET /api/eol-door/defectos).
 */
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$archivo = $argv[1] ?? null;
if (!$archivo || !is_file($archivo)) {
    fwrite(STDERR, "Uso: php importar-defectos-doortrim.php <planilla.xlsx>\n");
    exit(1);
}

$PIEZAS = [
    // patron del nombre de hoja => [id, tipo_pieza, nombre en la tablet, prefijo del codigo]
    '/armrest.*sin/i' => ['ARM_REST_SIN_COVER', 'ARM_REST', 'ARM REST SIN COVER', 'AS'],
    '/armrest.*con/i' => ['ARM_REST_CON_COVER', 'ARM_REST', 'ARM REST CON COVER', 'AC'],
    '/panel/i'        => ['PANEL', 'PANEL', 'PANEL', 'P'],
    '/garnish/i'      => ['GARNISH', 'GARNISH', 'GARNISH', 'G'],
];
$ORDEN = ['PANEL', 'ARM_REST_CON_COVER', 'ARM_REST_SIN_COVER', 'GARNISH'];

$GRUPOS = [
    'APARIENCIA'     => ['Apariencia', 'FACC15'],
    'FUNCIONAL'      => ['Funcional', 'EF4444'],
    'ENSAMBLE'       => ['Ensamble', '3B82F6'],
    'ESPECIFICACION' => ['Especificación', 'A855F7'],
];

function normalizar(string $s): string
{
    $s = strtoupper(trim($s));
    return strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U']);
}

$libro = IOFactory::load($archivo);
$piezas = [];

foreach ($libro->getWorksheetIterator() as $hoja) {
    $nombreHoja = $hoja->getTitle();
    $def = null;
    foreach ($PIEZAS as $patron => $d) {
        if (preg_match($patron, $nombreHoja)) {
            $def = $d;
            break;
        }
    }
    if (!$def) {
        echo "  (hoja ignorada: $nombreHoja)\n";
        continue;
    }
    [$id, $tipo, $nombre, $prefijo] = $def;

    $filas = $hoja->toArray(null, true, true, false);
    // Encabezado: la fila que tiene "Defecto" y "Grupo".
    $cols = null;
    $defectos = [];
    foreach ($filas as $fila) {
        $celdas = array_map(fn ($c) => trim((string) $c), $fila);
        if ($cols === null) {
            $norm = array_map('normalizar', $celdas);
            $iDef = array_search('DEFECTO', $norm, true);
            $iGrupo = array_search('GRUPO', $norm, true);
            if ($iDef !== false && $iGrupo !== false) {
                $cols = [
                    'id'    => array_search('ID', $norm, true),
                    'rango' => array_search('RANGO', $norm, true),
                    'def'   => $iDef,
                    'grupo' => $iGrupo,
                ];
            }
            continue;
        }
        $texto = $celdas[$cols['def']] ?? '';
        if ($texto === '') {
            continue;
        }
        $grupo = normalizar($celdas[$cols['grupo']] ?? '');
        if (!isset($GRUPOS[$grupo])) {
            fwrite(STDERR, "  [OJO] $nombreHoja: grupo desconocido '{$celdas[$cols['grupo']]}' en '$texto' (queda en APARIENCIA)\n");
            $grupo = 'APARIENCIA';
        }
        $rango = normalizar($cols['rango'] !== false ? ($celdas[$cols['rango']] ?? '') : '');
        $nro = $cols['id'] !== false && ($celdas[$cols['id']] ?? '') !== '' ? (int) $celdas[$cols['id']] : count($defectos) + 1;
        $defectos[] = [
            'id'     => "$id-$nro",
            'codigo' => $prefijo . $nro,
            'nombre' => $texto,
            'rango'  => in_array($rango, ['A', 'B', 'C'], true) ? $rango : 'C',
            'grupo'  => $grupo,
        ];
    }
    if ($cols === null) {
        fwrite(STDERR, "  [OJO] $nombreHoja: no encontre el encabezado (ID | Rango | Defecto | Grupo)\n");
        continue;
    }
    $piezas[$id] = ['id' => $id, 'tipo_pieza' => $tipo, 'nombre' => $nombre, 'defectos' => $defectos];
    echo sprintf("  %-20s %3d defectos  (hoja \"%s\")\n", $nombre, count($defectos), $nombreHoja);
}

$ordenadas = [];
foreach ($ORDEN as $id) {
    if (isset($piezas[$id])) {
        $ordenadas[] = $piezas[$id];
    }
}
if (empty($ordenadas)) {
    fwrite(STDERR, "No se importo ninguna pieza.\n");
    exit(1);
}

$catalogo = [
    'fuente'      => basename($archivo),
    'importado_at' => date('Y-m-d H:i:s'),
    'grupos'      => array_map(
        fn ($k, $g) => ['id' => $k, 'nombre' => $g[0], 'color' => $g[1]],
        array_keys($GRUPOS),
        $GRUPOS
    ),
    'piezas'      => $ordenadas,
];

$destino = __DIR__ . '/resources/data/defectos-doortrim.json';
if (!is_dir(dirname($destino))) {
    mkdir(dirname($destino), 0775, true);
}
file_put_contents($destino, json_encode($catalogo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "Catalogo guardado en resources/data/defectos-doortrim.json\n";
