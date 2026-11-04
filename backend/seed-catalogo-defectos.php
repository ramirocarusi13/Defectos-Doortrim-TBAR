<?php
/**
 * SEMILLA: pedido de la planta del cliente sobre el catalogo de defectos
 * (factory_configs, key 'active', ver App\Http\Controllers\FactoryConfigController):
 *
 *   1. Recategoriza el defecto PROTUBERANCIA de "Ensamble" a "Apariencia".
 *   2. Agrega el defecto "Material sobrante" (MAT-SOBRANTE) en "Apariencia",
 *      para todos los puestos.
 *   3. Agrega el defecto "Uretano equivocado" (URET-EQUIV) en "Especificacion",
 *      solo para los puestos "FRxx" (si no hay ninguno, queda en todos los
 *      puestos y se avisa por consola).
 *   4. Si "Apariencia" o "Especificacion" no existen en defectCategories, las
 *      crea (nunca borra ni desactiva categorias existentes).
 *
 * Idempotente: matchea defectos/categorias por code o name normalizados
 * (mayusculas, sin acentos, trim). Correrlo dos veces no duplica nada y
 * reporta "sin cambios". Nunca borra ni desactiva nada de lo que ya haya.
 *
 * Flags:
 *   --dry-run  muestra que haria sin guardar nada.
 *   --yes      obligatorio para escribir cuando DB_HOST NO es localhost (el
 *              servidor de planta usa IP, ej. 172.17.132.150): es la
 *              confirmacion explicita de que se quiere tocar esa base.
 *
 * Requiere que la fila factory_configs 'active' ya exista (o sea, que alguien
 * haya guardado /config al menos una vez); si no existe, aborta: crearla desde
 * aca dejaria una config sin modelos/fotos y las tablets sin nada que marcar.
 *
 * IMPORTANTE: despues de correrlo, refrescar (F5) cualquier pestaña de /config
 * que estuviera abierta: la web guarda la config ENTERA desde su estado local y
 * una pestaña vieja pisaria estos cambios al guardar cualquier otra cosa.
 *
 *   php seed-catalogo-defectos.php [--dry-run] [--yes]
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FactoryConfig;

const CONFIG_KEY = 'active';

$dryRun = in_array('--dry-run', $argv, true);
$yes = in_array('--yes', $argv, true);

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------

/** Mayusculas, sin acentos, trim y espacios colapsados, para matchear. */
function normalizar(string $s): string
{
    $s = trim($s);
    $s = strtr($s, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
    ]);
    $s = strtoupper($s);
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

function slug(string $s): string
{
    $s = strtolower(normalizar($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/** Id al estilo del front (defect-xxxxxxx-yyyyyyy). */
function randomConfigId(string $prefix): string
{
    $rand = substr(bin2hex(random_bytes(5)), 0, 7);
    $time = base_convert((string) round(microtime(true) * 1000), 10, 36);
    return "{$prefix}-{$rand}-{$time}";
}

/** Busca una categoria por name/code normalizado dentro de la lista dada. */
function buscarCategoria(array $categorias, string $normalizado): ?array
{
    foreach ($categorias as $cat) {
        $name = normalizar((string) ($cat['name'] ?? ''));
        $code = normalizar((string) ($cat['code'] ?? ''));
        if ($name === $normalizado || $code === $normalizado) {
            return $cat;
        }
    }
    return null;
}

/** Busca un defecto por name/code normalizado dentro de la lista dada. */
function buscarDefecto(array $defectos, string $normalizado): ?int
{
    foreach ($defectos as $idx => $d) {
        $name = normalizar((string) ($d['name'] ?? ''));
        $code = normalizar((string) ($d['code'] ?? ''));
        if ($name === $normalizado || $code === $normalizado) {
            return $idx;
        }
    }
    return null;
}

/**
 * Devuelve [categoria, creada:bool] resolviendo por name/code normalizado, o
 * la crea (nunca toca/borra categorias existentes) y la agrega a $categorias.
 */
function resolverOCrearCategoria(array &$categorias, string $normalizado, string $nombreDisplay, string $color): array
{
    $existente = buscarCategoria($categorias, $normalizado);
    if ($existente !== null) {
        return [$existente, false];
    }

    // Id deterministico pero sin colisionar con uno ya usado (ej. la planta
    // renombro la categoria default 'category-apariencia' a otra cosa).
    $idsUsados = array_map(fn ($c) => (string) ($c['id'] ?? ''), $categorias);
    $base = 'category-' . slug($nombreDisplay);
    $id = $base;
    for ($n = 2; in_array($id, $idsUsados, true); $n++) {
        $id = "{$base}-{$n}";
    }

    $nueva = [
        'id' => $id,
        'code' => strtoupper(slug($nombreDisplay)),
        'name' => $nombreDisplay,
        'active' => true,
        'color' => $color,
    ];
    $categorias[] = $nueva;
    return [$nueva, true];
}

// -----------------------------------------------------------------------
// Carga de la config vigente
// -----------------------------------------------------------------------

$row = FactoryConfig::query()->where('key', CONFIG_KEY)->first();
if (!$row || !is_array($row->data) || empty($row->data)) {
    echo "ABORTADO: no existe la fila factory_configs key='" . CONFIG_KEY . "' (o esta vacia).\n";
    echo "Guarda /config al menos una vez desde la web y volve a correr el script.\n";
    exit(1);
}
$data = $row->data;

$defectCategories = is_array($data['defectCategories'] ?? null) ? $data['defectCategories'] : [];
$defects = is_array($data['defects'] ?? null) ? $data['defects'] : [];
$puestos = is_array($data['puestos'] ?? null) ? $data['puestos'] : [];

$reporte = [];
$cambios = false;

// -----------------------------------------------------------------------
// 4) Resolver (o crear) las categorias Apariencia y Especificacion
// -----------------------------------------------------------------------

[$catApariencia, $creadaApariencia] = resolverOCrearCategoria($defectCategories, 'APARIENCIA', 'Apariencia', '3B82F6');
if ($creadaApariencia) {
    $reporte[] = "+ Categoria creada: {$catApariencia['name']} (id={$catApariencia['id']}, code={$catApariencia['code']})";
    $cambios = true;
} else {
    $reporte[] = "= Categoria existente: {$catApariencia['name']} (id={$catApariencia['id']})";
}

[$catEspecificacion, $creadaEspecificacion] = resolverOCrearCategoria($defectCategories, 'ESPECIFICACION', 'Especificacion', '8B5CF6');
if ($creadaEspecificacion) {
    $reporte[] = "+ Categoria creada: {$catEspecificacion['name']} (id={$catEspecificacion['id']}, code={$catEspecificacion['code']})";
    $cambios = true;
} else {
    $reporte[] = "= Categoria existente: {$catEspecificacion['name']} (id={$catEspecificacion['id']})";
}

// -----------------------------------------------------------------------
// 1) PROTUBERANCIA: Ensamble -> Apariencia
// -----------------------------------------------------------------------

$idxProtuberancia = buscarDefecto($defects, 'PROTUBERANCIA');
if ($idxProtuberancia === null) {
    $reporte[] = "! AVISO: no se encontro el defecto PROTUBERANCIA en el catalogo. No se recategoriza nada.";
} else {
    $defecto = $defects[$idxProtuberancia];
    $categoriaActual = normalizar((string) ($defecto['category'] ?? ''));
    if ($categoriaActual === 'APARIENCIA' && ($defecto['categoryId'] ?? null) === $catApariencia['id']) {
        $reporte[] = "= PROTUBERANCIA ya esta en Apariencia, sin cambios.";
    } else {
        $anterior = $defecto['category'] ?? '(sin categoria)';
        $defects[$idxProtuberancia]['category'] = $catApariencia['name'];
        $defects[$idxProtuberancia]['categoryId'] = $catApariencia['id'];
        $reporte[] = "* PROTUBERANCIA recategorizado: \"$anterior\" -> \"{$catApariencia['name']}\"";
        $cambios = true;
    }
}

// -----------------------------------------------------------------------
// Puestos "FRxx" para el nuevo defecto de Especificacion
// -----------------------------------------------------------------------

$puestoIdsFR = [];
$codigosPuestos = [];
foreach ($puestos as $p) {
    $code = (string) ($p['code'] ?? '');
    $name = (string) ($p['name'] ?? '');
    $codigosPuestos[] = trim($code . ' ' . $name);
    if (str_starts_with(normalizar($code), 'FR') || str_starts_with(normalizar($name), 'FR')) {
        $puestoIdsFR[] = $p['id'];
    }
}

if (empty($puestoIdsFR)) {
    $listaPuestos = implode(', ', array_filter($codigosPuestos)) ?: '(no hay puestos cargados)';
    $reporte[] = "! WARNING: no se encontro ningun puesto cuyo code/name empiece con \"FR\". "
        . "URET-EQUIV va a quedar visible en TODOS los puestos. Puestos existentes: $listaPuestos. "
        . "Ajustalo manualmente en /config si corresponde.";
}

// -----------------------------------------------------------------------
// 2) Agregar "Material sobrante" (MAT-SOBRANTE) en Apariencia, todos los puestos
// -----------------------------------------------------------------------

$idxMatSobrante = buscarDefecto($defects, 'MAT-SOBRANTE');
if ($idxMatSobrante === null) {
    $idxMatSobrante = buscarDefecto($defects, 'MATERIAL SOBRANTE');
}

if ($idxMatSobrante !== null) {
    // Ya existe: solo aseguramos la categoria (no tocamos el resto).
    $cambioCategoria = ($defects[$idxMatSobrante]['category'] ?? null) !== $catApariencia['name']
        || ($defects[$idxMatSobrante]['categoryId'] ?? null) !== $catApariencia['id'];
    if ($cambioCategoria) {
        $defects[$idxMatSobrante]['category'] = $catApariencia['name'];
        $defects[$idxMatSobrante]['categoryId'] = $catApariencia['id'];
        $reporte[] = "* Material sobrante ya existia: categoria actualizada a Apariencia.";
        $cambios = true;
    } else {
        $reporte[] = "= Material sobrante ya existia (code=MAT-SOBRANTE), sin cambios.";
    }
} else {
    $nuevo = [
        'id' => randomConfigId('defect'),
        'code' => 'MAT-SOBRANTE',
        'name' => 'Material sobrante',
        'category' => $catApariencia['name'],
        'categoryId' => $catApariencia['id'],
        'caracteristica' => 'C',
        'color' => $catApariencia['color'] ?? 'A855F7',
        // false = circuito normal (critico/interlock). Si la planta lo quiere
        // como LEVE (retrabajo en linea), se marca "Leve" en /config.
        'canBeMinor' => false,
        'active' => true,
        'puestoIds' => [],
    ];
    $defects[] = $nuevo;
    $reporte[] = "+ Defecto agregado: Material sobrante (MAT-SOBRANTE) en Apariencia, todos los puestos.";
    $cambios = true;
}

// -----------------------------------------------------------------------
// 3) Agregar "Uretano equivocado" (URET-EQUIV) en Especificacion, puestos FRxx
// -----------------------------------------------------------------------

$idxUretEquiv = buscarDefecto($defects, 'URET-EQUIV');
if ($idxUretEquiv === null) {
    $idxUretEquiv = buscarDefecto($defects, 'URETANO EQUIVOCADO');
}

if ($idxUretEquiv !== null) {
    $cambioCategoria = ($defects[$idxUretEquiv]['category'] ?? null) !== $catEspecificacion['name']
        || ($defects[$idxUretEquiv]['categoryId'] ?? null) !== $catEspecificacion['id'];
    if ($cambioCategoria) {
        $defects[$idxUretEquiv]['category'] = $catEspecificacion['name'];
        $defects[$idxUretEquiv]['categoryId'] = $catEspecificacion['id'];
        $reporte[] = "* Uretano equivocado ya existia: categoria actualizada a Especificacion.";
        $cambios = true;
    } else {
        $reporte[] = "= Uretano equivocado ya existia (code=URET-EQUIV), sin cambios.";
    }
} else {
    $nuevo = [
        'id' => randomConfigId('defect'),
        'code' => 'URET-EQUIV',
        'name' => 'Uretano equivocado',
        'category' => $catEspecificacion['name'],
        'categoryId' => $catEspecificacion['id'],
        'caracteristica' => 'B',
        'color' => $catEspecificacion['color'] ?? '8B5CF6',
        'canBeMinor' => false,
        'active' => true,
        'puestoIds' => $puestoIdsFR,
    ];
    $defects[] = $nuevo;
    $puestosTxt = empty($puestoIdsFR) ? 'todos los puestos (ver warning arriba)' : implode(', ', $puestoIdsFR);
    $reporte[] = "+ Defecto agregado: Uretano equivocado (URET-EQUIV) en Especificacion, puestos: $puestosTxt.";
    $cambios = true;
}

// -----------------------------------------------------------------------
// Guardado
// -----------------------------------------------------------------------

$data['defectCategories'] = $defectCategories;
$data['defects'] = $defects;

$dbHost = (string) env('DB_HOST', '');
$dbDatabase = (string) env('DB_DATABASE', '');
$esLocal = stripos($dbHost, 'localhost') !== false;

echo "=============================================================\n";
echo "  SEED CATALOGO DE DEFECTOS (pedido cliente: Protuberancia,\n";
echo "  Material sobrante, Uretano equivocado)\n";
echo "=============================================================\n";
echo "DB_HOST=$dbHost\n";
echo "DB_DATABASE=$dbDatabase\n";
echo ($esLocal ? "-> DB local detectada.\n" : "-> DB NO local detectada.\n");
echo "-------------------------------------------------------------\n";
foreach ($reporte as $linea) {
    echo "$linea\n";
}
echo "-------------------------------------------------------------\n";

if (!$cambios) {
    echo "Sin cambios.\n";
}

if ($dryRun) {
    echo "\nMODO --dry-run: no se guardo nada.\n";
    exit(0);
}

if (!$esLocal && !$yes) {
    echo "\nABORTADO: DB_HOST=$dbHost no es localhost y no se paso --yes.\n";
    echo "Si de verdad queres escribir en esa base (ej. el servidor de planta), corre:\n";
    echo "  php seed-catalogo-defectos.php --yes\n";
    exit(1);
}

$data['updatedAt'] = now()->toISOString();

$row->data = $data;
$row->save();

echo "\nGuardado OK en factory_configs (key=" . CONFIG_KEY . ").\n";
echo "Total defectos en config: " . count($defects) . "\n";
echo "Total categorias en config: " . count($defectCategories) . "\n";
