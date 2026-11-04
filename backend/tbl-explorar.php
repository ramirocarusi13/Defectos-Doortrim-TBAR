<?php
/**
 * Mira la base TB-L (app de camaras de door trims, .153) SOLO LEYENDO, para saber
 * en que tabla queda cada QR y su hora (curado del panel).
 *
 *   php tbl-explorar.php                     tablas, columnas y filas
 *   php tbl-explorar.php "0001;RH;FR;D3L"    ademas busca ese QR en las tablas
 *                                            que parecen de lecturas/QR
 *   php tbl-explorar.php "QR" --todas        lo busca en todas las tablas
 *
 * Con la tabla encontrada se arma DOOR_TBL_SQL_PANEL en backend/.env, por ejemplo:
 *   DOOR_TBL_SQL_PANEL="SELECT TOP 1 FechaHora AS fecha FROM dbo.Lecturas WHERE Codigo = ? ORDER BY FechaHora DESC"
 * La conexion 'tbl' rechaza cualquier escritura (AppServiceProvider).
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$qr = $argv[1] ?? null;
$todas = in_array('--todas', $argv, true);
$db = DB::connection('tbl');

try {
    $db->selectOne('SELECT 1 AS ok');
} catch (Throwable $e) {
    fwrite(STDERR, "No me pude conectar a TB-L (" . config('database.connections.tbl.host') . "): " . $e->getMessage() . "\n");
    exit(1);
}

$tablas = $db->select("
    SELECT s.name AS esquema, t.name AS tabla, SUM(p.rows) AS filas
    FROM sys.tables t
    JOIN sys.schemas s ON s.schema_id = t.schema_id
    JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0, 1)
    GROUP BY s.name, t.name
    ORDER BY s.name, t.name");

$columnas = [];
foreach ($db->select("
    SELECT s.name AS esquema, t.name AS tabla, c.name AS columna, ty.name AS tipo, c.max_length AS largo
    FROM sys.columns c
    JOIN sys.tables t ON t.object_id = c.object_id
    JOIN sys.schemas s ON s.schema_id = t.schema_id
    JOIN sys.types ty ON ty.user_type_id = c.user_type_id
    ORDER BY s.name, t.name, c.column_id") as $c) {
    $columnas["{$c->esquema}.{$c->tabla}"][] = $c;
}

echo "TB-L: " . count($tablas) . " tablas\n\n";
foreach ($tablas as $t) {
    $k = "{$t->esquema}.{$t->tabla}";
    $cols = array_map(fn ($c) => "{$c->columna} ({$c->tipo})", $columnas[$k] ?? []);
    echo "{$k}  [{$t->filas} filas]\n    " . implode(', ', $cols) . "\n";
    // Las 3 filas mas nuevas de ejemplo (por la primera columna de fecha si hay),
    // para ver como guarda la camara cada QR y su hora.
    if ($t->filas > 0) {
        $fecha = collect($columnas[$k] ?? [])->first(fn ($c) => in_array($c->tipo, ['datetime', 'datetime2', 'smalldatetime', 'date', 'datetimeoffset'], true));
        $orden = $fecha ? "ORDER BY [{$fecha->columna}] DESC" : '';
        try {
            foreach ($db->select("SELECT TOP 3 * FROM [{$t->esquema}].[{$t->tabla}] WITH (NOLOCK) {$orden}") as $f) {
                echo '      ' . mb_substr(json_encode($f, JSON_UNESCAPED_UNICODE), 0, 600) . "\n";
            }
        } catch (Throwable $e) {
            echo "      (no se pudo leer: " . mb_substr($e->getMessage(), 0, 120) . ")\n";
        }
    }
}

if (!$qr) {
    exit(0);
}

echo "\nBuscando el QR \"{$qr}\"...\n";
$pareceDeQr = '/qr|etiq|lect|regist|panel|door|camar|curad|codig|pieza|produc|histor/i';
foreach ($tablas as $t) {
    $k = "{$t->esquema}.{$t->tabla}";
    if (!$todas && !preg_match($pareceDeQr, $t->tabla)) {
        continue;
    }
    foreach ($columnas[$k] ?? [] as $c) {
        if (!in_array($c->tipo, ['varchar', 'nvarchar', 'char', 'nchar'], true)) {
            continue;
        }
        if ($c->largo > 0 && $c->largo < strlen($qr) && $c->tipo[0] !== 'n') {
            continue;
        }
        $sql = "SELECT TOP 3 * FROM [{$t->esquema}].[{$t->tabla}] WITH (NOLOCK) WHERE [{$c->columna}] = ?";
        try {
            $filas = $db->select($sql, [$qr]);
        } catch (Throwable $e) {
            continue;
        }
        if ($filas) {
            echo "\n>> {$k}.{$c->columna}\n";
            foreach ($filas as $f) {
                echo '   ' . json_encode($f, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    }
}
echo "\nListo.\n";
