<?php
/**
 * Carga a /config las fotos de los modelos de door trim que manda Calidad
 * (carpeta "737D - Fotos DOOR AR GN": Door/, Armrest/, Garnish/, una subcarpeta
 * por SD y posicion, ej. "D3E D3L RR" = SD D3E y D3L, posicion RR).
 *
 *   php importar-fotos-doortrim.php "C:\Users\...\737D - Fotos DOOR AR GN"
 *
 * Por cada SD + posicion:
 *   - copia las fotos a public/uploads/modelos/<SD>_<POS>/
 *   - las pone en los numeros de parte de /config de ese SD y posicion
 *     (jitms.sd / sdTasa + jitms.posicionMano), reemplazando las fotos que tenian
 *   - crea/actualiza el modelo de foto "<SD> <POS>" (ej. "D3E RR"; garnish: "GDC"),
 *     que usa la tablet cuando el SD no tiene numero de parte en /config.
 * Antes de guardar deja una copia de la config en storage/app/factory-config-<fecha>.json.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FactoryConfig;

/**
 * Copia la foto para la tablet. Las que manda Calidad son chicas (las vistas de
 * costado ~200x900 px) y la tablet las estira y se ven borrosas: se agrandan aca
 * con interpolacion bicubica hasta 1600 px del lado largo, se les da un poco de
 * nitidez y se guardan en JPG de alta calidad. $espejo: da vuelta la foto (LH
 * armado desde el RH cuando no hay fotos del otro lado).
 */
function procesarFoto(string $origen, string $destino, bool $espejo): void
{
    $ext = strtolower(pathinfo($origen, PATHINFO_EXTENSION));
    $img = $ext === 'png' ? imagecreatefrompng($origen) : imagecreatefromjpeg($origen);
    if ($espejo) {
        imageflip($img, IMG_FLIP_HORIZONTAL);
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $largo = 1600;
    if (max($w, $h) < $largo) {
        $factor = $largo / max($w, $h);
        $nuevo = imagescale($img, (int) round($w * $factor), (int) round($h * $factor), IMG_BICUBIC);
        imagedestroy($img);
        $img = $nuevo;
        // Nitidez suave (unsharp liviano) para compensar el agrandado.
        imageconvolution($img, [[0, -1, 0], [-1, 8, -1], [0, -1, 0]], 4, 0);
    }
    // Fondo blanco si venia con transparencia (png).
    $fondo = imagecreatetruecolor(imagesx($img), imagesy($img));
    imagefill($fondo, 0, 0, imagecolorallocate($fondo, 255, 255, 255));
    imagecopy($fondo, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
    imagejpeg($fondo, $destino, 95);
    imagedestroy($img);
    imagedestroy($fondo);
}

$origen = rtrim($argv[1] ?? '', '\\/');
if (!$origen || !is_dir($origen)) {
    fwrite(STDERR, "Uso: php importar-fotos-doortrim.php \"<carpeta 737D - Fotos DOOR AR GN>\"\n");
    exit(1);
}

$row = FactoryConfig::query()->where('key', 'active')->first();
if (!$row) {
    fwrite(STDERR, "No hay factory-config activa.\n");
    exit(1);
}
$cfg = $row->data;
@mkdir(storage_path('app'), 0777, true);
$backup = storage_path('app/factory-config-' . date('Ymd-His') . '.json');
file_put_contents($backup, json_encode($cfg, JSON_UNESCAPED_UNICODE));

$familias = ['DOOR' => 'DOOR', 'ARMREST' => 'ARMREST', 'GARNISH' => 'GARNISH'];
// Orden de las vistas en la tablet: Front A siempre 1/6.
// Version de las fotos: se agrega a la URL para que la tablet no muestre las viejas guardadas.
$version = date("YmdHis");
$ordenVistas = ['FRONT A' => 1, 'FRONT' => 2, 'REAR' => 3, 'BACK' => 4, 'TOP' => 5, 'BOTTOM' => 6];
$destBase = public_path('uploads/modelos');

$models = $cfg['models'] ?? [];
$images = $cfg['modelImages'] ?? [];
$resumen = [];

// Que SD/posicion/lado tienen fotos reales (para no espejar donde ya hay).
$reales = [];
foreach (glob($origen . '/*/*', GLOB_ONLYDIR) as $dir) {
    $fotos = glob($dir . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);
    if (!$fotos || !preg_match('/\b(LH|RH)\b/i', basename($fotos[0]), $mm)) {
        continue;
    }
    $tokens = preg_split('/\s+/', strtoupper(trim(basename($dir))));
    $p = current(array_intersect($tokens, ['FR', 'RR'])) ?: '';
    foreach ($tokens as $t) {
        if (!in_array($t, ['FR', 'RR'], true) && preg_match('/^[A-Z][A-Z0-9]{1,4}$/', $t)) {
            $reales["{$t}|{$p}|" . strtoupper($mm[1])] = true;
        }
    }
}

// Los modelos de foto de door trim se rearman de cero en cada corrida.
$viejos = array_column(array_filter($models, fn ($m) => str_starts_with((string) ($m['id'] ?? ''), 'model-dt-')), 'id');
$models = array_values(array_filter($models, fn ($m) => !in_array($m['id'] ?? '', $viejos, true)));
$images = array_values(array_filter($images, fn ($img) => !in_array($img['modelId'] ?? '', $viejos, true)));

foreach (glob($origen . '/*', GLOB_ONLYDIR) as $dirFamilia) {
    $familia = $familias[strtoupper(basename($dirFamilia))] ?? null;
    if (!$familia) {
        continue;
    }
    foreach (glob($dirFamilia . '/*', GLOB_ONLYDIR) as $dir) {
        $tokens = preg_split('/\s+/', strtoupper(trim(basename($dir))));
        $pos = null;
        $sds = [];
        foreach ($tokens as $t) {
            if (in_array($t, ['FR', 'RR'], true)) {
                $pos = $t;
            } elseif (preg_match('/^[A-Z][A-Z0-9]{1,4}$/', $t)) {
                $sds[] = $t;
            }
        }
        $fotos = glob($dir . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);
        if (!$sds || !$fotos) {
            continue;
        }
        // La mano sale del nombre de las fotos ("FR RH - Back View.jpg").
        $mano = preg_match('/\b(LH|RH)\b/i', basename($fotos[0]), $mm) ? strtoupper($mm[1]) : null;
        $manoOriginal = $mano;
        // Vistas ordenadas: Front A, Front, Rear, Back, Top, Bottom.
        $vistas = [];
        foreach ($fotos as $f) {
            $vista = trim(preg_replace('/\s*view$/i', '', preg_replace('/^.*-\s*/', '', pathinfo($f, PATHINFO_FILENAME))));
            $vistas[] = ['archivo' => $f, 'vista' => $vista ?: 'Vista', 'orden' => $ordenVistas[strtoupper($vista)] ?? 9];
        }
        usort($vistas, fn ($a, $b) => $a['orden'] <=> $b['orden']);

        // Lados a cargar: el de las fotos y, si de ese SD/posicion no vinieron fotos
        // del otro lado, el otro lado ESPEJADO (el LH es el espejo del RH). Cuando
        // lleguen las fotos reales del otro lado, se cargan y reemplazan al espejo.
        $otro = ['RH' => 'LH', 'LH' => 'RH'][$mano] ?? null;
        $tareas = [];
        foreach ($sds as $sd) {
            $tareas[] = [$sd, $mano, false];
            if ($familia !== 'GARNISH' && $otro && !isset($reales["{$sd}|{$pos}|{$otro}"])) {
                $tareas[] = [$sd, $otro, true];
            }
        }

        foreach ($tareas as [$sd, $manoCarga, $espejo]) {
            $carpeta = implode('_', array_filter([$sd, $pos, $manoCarga]));
            @mkdir("{$destBase}/{$carpeta}", 0777, true);
            $lista = [];
            foreach ($vistas as $i => $v) {
                $ext = strtolower(pathinfo($v['archivo'], PATHINFO_EXTENSION));
                $nombre = "{$carpeta}__" . str_replace(' ', '_', $v['vista']) . ".jpg";
                procesarFoto($v['archivo'], "{$destBase}/{$carpeta}/{$nombre}", $espejo);
                $lista[] = ['vista' => $v['vista'], 'path' => "modelos/{$carpeta}/{$nombre}?v={$version}", 'seq' => $i + 1];
            }
            $mano = $manoCarga;

            // Modelos destino: numeros de parte de ese SD, posicion y MANO (cada lado ve
            // solo sus fotos) + el modelo de foto "<SD> <POS> <MANO>". El garnish no
            // trae mano en el QR: su modelo de foto es solo el SD.
            $codigoFoto = $familia === 'GARNISH' ? $sd : implode(' ', array_filter([$sd, $pos, $mano]));
            $idFoto = 'model-dt-' . strtolower(str_replace(' ', '-', $codigoFoto));
            $destinos = [];
            foreach ($models as $m) {
                $j = $m['jitms'] ?? null;
                if (!is_array($j) || strtoupper((string) ($j['familia'] ?? '')) !== $familia) {
                    continue;
                }
                $sdsModelo = [strtoupper((string) ($j['sd'] ?? '')), strtoupper((string) ($j['sdTasa'] ?? ''))];
                $pm = strtoupper(trim((string) ($j['posicionMano'] ?? '')));
                $pmFotos = trim(($pos ?? '') . ' ' . ($mano ?? ''));
                if (in_array($sd, $sdsModelo, true) && ($pmFotos === '' || $pm === $pmFotos || (!$pos && str_ends_with($pm, (string) $mano)))) {
                    $destinos[] = $m['id'];
                }
            }
            $existe = false;
            foreach ($models as &$m) {
                if (($m['id'] ?? '') === $idFoto) {
                    $existe = true;
                    $m['active'] = true;
                }
            }
            unset($m);
            if (!$existe) {
                $models[] = [
                    'lineIds' => [], 'subLineIds' => [], 'active' => true,
                    'id' => $idFoto, 'code' => $codigoFoto, 'name' => $codigoFoto,
                ];
            }
            $destinos[] = $idFoto;

            // Reemplaza las fotos de esos modelos por las nuevas.
            $images = array_values(array_filter($images, fn ($img) => !in_array($img['modelId'] ?? '', $destinos, true)));
            foreach ($destinos as $modelId) {
                foreach ($lista as $l) {
                    $images[] = [
                        'defectIds' => [], 'active' => true, 'imageDataUrl' => null,
                        'remotePath' => $l['path'],
                        'viewCode' => '-' . strtoupper(str_replace(' ', '_', $l['vista'])),
                        'viewName' => $l['vista'], 'name' => $l['vista'],
                        'sequence' => $l['seq'],
                        'id' => "img-{$modelId}-{$l['seq']}",
                        'modelId' => $modelId,
                    ];
                }
            }
            $resumen[] = sprintf('%-8s %-3s %d fotos -> %d modelos (%s)%s', $sd, $pos ?? '-', count($lista), count($destinos), $codigoFoto, $espejo ? '  [espejo del otro lado]' : '');
        }
        $mano = $manoOriginal;
    }
}

$cfg['models'] = $models;
$cfg['modelImages'] = $images;
$cfg['updatedAt'] = now()->toIso8601String();
$row->data = $cfg;
$row->save();
$row->touch();

echo implode("\n", $resumen) . "\n\nGuardado. Copia de la config anterior: {$backup}\n";
