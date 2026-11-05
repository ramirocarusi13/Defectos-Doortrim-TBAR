<?php

namespace App\Services;

use App\Models\FactoryConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lee los QR de la linea de door trims, que genera la app de camaras (.153).
 * Nada de JITMS: todo sale del propio QR, con el mismo formato que esa app
 * ("Contenido de los QR"):
 *
 *   PANEL    campos separados por ';'   secuencia;mano;posicion;SD   0001;RH;FR;D3L
 *   ARMREST  por posicion, largo 21     fecha+hora de encolado (14) + SD (3) +
 *                                       posicion (2) + mano (2)       20260728142712A4LRRRH
 *   GARNISH  todo el codigo es el SD                                   GDC
 *
 * Con SD + tipo + posicion + mano se busca el numero de parte en /config
 * (models[].jitms: sd / sdTasa, familia, posicionMano).
 *
 * Curado (4 hs, settings.door.minutosSecado): el armrest lo trae en el QR (fecha
 * de encolado). El panel no: su hora esta en la base TB-L de la app de camaras
 * (pendiente de conectar, ver tbl-explorar.php). El garnish no se controla.
 */
class DoorQrService
{
    /** Formato de cada pieza. Posiciones desde 1; longitud 0 = el resto del codigo. */
    public const FORMATOS = [
        'PANEL' => [
            'formato'   => 'SEPARADO',
            'separador' => ';',
            'campos'    => ['secuencia' => 1, 'mano' => 2, 'posicion' => 3, 'sd' => 4],
        ],
        'ARM_REST' => [
            'formato' => 'POSICION',
            'largo'   => 21,
            'campos'  => ['fecha' => [1, 14], 'sd' => [15, 3], 'posicion' => [18, 2], 'mano' => [20, 2]],
        ],
        'GARNISH' => [
            'formato' => 'POSICION',
            'largo'   => 0,
            'campos'  => ['sd' => [1, 0]],
        ],
    ];

    /** Familia de /config (jitms.familia) de cada tipo de pieza. */
    private const FAMILIA = ['PANEL' => 'DOOR', 'ARM_REST' => 'ARMREST', 'GARNISH' => 'GARNISH'];

    /**
     * SD que la camara imprime con otro nombre: se toman como el SD de la derecha
     * (mismo modelo, mismas fotos y compatibles en el cambio de pieza). El SD tal
     * cual vino en el QR queda en 'sd_qr'.
     */
    private const SD_EQUIVALENTE = ['D3H' => 'D3E'];

    /**
     * Interpreta un QR de door trim. Devuelve null si no tiene ninguno de los
     * formatos (no es una pieza de door trim).
     */
    public static function interpretar(?string $qr, ?array $modelos = null): ?array
    {
        $qr = strtoupper(trim((string) $qr));
        if ($qr === '') {
            return null;
        }

        $modelos ??= self::modelosConfig();
        $datos = self::leerSeparado($qr, $modelos) ?? self::leerArmrest($qr) ?? self::leerGarnish($qr);
        if (!$datos) {
            return null;
        }
        $datos['sd_qr'] = $datos['sd'];
        $datos['sd'] = self::SD_EQUIVALENTE[$datos['sd']] ?? $datos['sd'];

        $candidatos = self::buscarModelos($modelos, $datos);
        $unico = count($candidatos) === 1 ? $candidatos[0] : null;
        // Garnish: no trae mano. Si todos los candidatos son el mismo numero de parte
        // salvo la mano, igual se informa el primero como referencia de foto.
        $datos['codigo']      = $unico['code'] ?? null;
        $datos['descripcion'] = $unico['name'] ?? null;
        // Sin numero de parte en /config: el modelo de foto de ese SD, posicion y mano
        // ("D3E RR RH", importar-fotos-doortrim.php), asi la tablet muestra SOLO las
        // fotos de ese lado. El garnish no trae mano: su modelo de foto es el SD.
        if (!$datos['codigo']) {
            $codigoFoto = $datos['tipo'] === 'GARNISH'
                ? $datos['sd']
                : implode(' ', array_filter([$datos['sd'], $datos['posicion'], $datos['mano']]));
            foreach ($modelos as $m) {
                if (strtoupper((string) ($m['code'] ?? '')) === $codigoFoto && ($m['active'] ?? true) !== false) {
                    $datos['codigo'] = $m['code'];
                    break;
                }
            }
        }
        $datos['candidatos']  = array_map(fn ($m) => [
            'codigo'       => $m['code'] ?? null,
            'descripcion'  => $m['name'] ?? null,
            'posicionMano' => $m['jitms']['posicionMano'] ?? null,
        ], $candidatos);
        $datos['familia'] = self::FAMILIA[$datos['tipo']];

        return $datos + self::curado($qr, $datos);
    }

    // ------------------------------------------------------------------ formatos

    /**
     * QR con campos separados por '|' o ';'. Los campos se reconocen por su valor,
     * no por su lugar, asi sirven los dos formatos de la app de camaras:
     *   14343|1|D3H|FR|LH   (secuencia | ? | SD | posicion | mano)  <- el de la linea
     *   0001;RH;FR;D3L      (secuencia ; mano ; posicion ; SD)
     * mano = LH/RH, posicion = FR/RR, SD = codigo corto que empieza con letra,
     * secuencia = el primer numero. El tipo sale de /config por el SD (familia);
     * si el SD no esta cargado, por su letra: A = arm rest, G = garnish, resto = panel.
     */
    private static function leerSeparado(string $qr, array $modelos): ?array
    {
        if (!preg_match('/[|;]/', $qr)) {
            return null;
        }
        $partes = array_values(array_filter(array_map('trim', preg_split('/[|;]/', $qr)), fn ($p) => $p !== ''));
        // Armrest del puesto: "20261010171023|A3H|FR|LH" = fecha y hora de encolado
        // (aaaammddhhmmss, 14 digitos) | SD | posicion | mano: la fecha no es la
        // secuencia y da el curado de 4 hs.
        $mano = $posicion = $sd = $secuencia = $fecha = null;
        foreach ($partes as $p) {
            if (!$mano && in_array($p, ['LH', 'RH'], true)) {
                $mano = $p;
            } elseif (!$posicion && in_array($p, ['FR', 'RR'], true)) {
                $posicion = $p;
            } elseif (!$sd && preg_match('/^[A-Z][A-Z0-9]{1,4}$/', $p)) {
                $sd = $p;
            } elseif (!$fecha && strlen($p) === 14 && ($f = self::fecha($p))) {
                $fecha = $f;
            } elseif (!$secuencia && ctype_digit($p)) {
                $secuencia = $p;
            }
        }
        if (!$mano || !$posicion || !$sd) {
            return null;
        }
        return [
            'tipo'      => self::tipoPorSd($sd, $modelos),
            'sd'        => $sd,
            'mano'      => $mano,
            'posicion'  => $posicion,
            'secuencia' => $secuencia,
            'fecha_encolado' => $fecha?->format('Y-m-d H:i:s'),
        ];
    }

    /** PANEL / ARM_REST / GARNISH segun la familia del SD en /config, o su letra. */
    private static function tipoPorSd(string $sd, array $modelos): string
    {
        $porFamilia = array_flip(self::FAMILIA);
        foreach ($modelos as $m) {
            $j = $m['jitms'] ?? null;
            if (is_array($j) && in_array($sd, [strtoupper((string) ($j['sd'] ?? '')), strtoupper((string) ($j['sdTasa'] ?? ''))], true)) {
                $tipo = $porFamilia[strtoupper((string) ($j['familia'] ?? ''))] ?? null;
                if ($tipo) {
                    return $tipo;
                }
            }
        }
        return match ($sd[0]) {
            'A'     => 'ARM_REST',
            'G'     => 'GARNISH',
            default => 'PANEL',
        };
    }

    private static function leerArmrest(string $qr): ?array
    {
        $f = self::FORMATOS['ARM_REST'];
        if (strlen($qr) !== $f['largo']) {
            return null;
        }
        $v = fn ($campo) => self::tramo($qr, ...$f['campos'][$campo]);
        $fecha = self::fecha($v('fecha'));
        $mano = $v('mano');
        $posicion = $v('posicion');
        if (!$fecha || !in_array($mano, ['LH', 'RH'], true) || !in_array($posicion, ['FR', 'RR'], true)) {
            return null;
        }
        return [
            'tipo'      => 'ARM_REST',
            'sd'        => $v('sd'),
            'mano'      => $mano,
            'posicion'  => $posicion,
            'secuencia' => null,
            'fecha_encolado' => $fecha->format('Y-m-d H:i:s'),
        ];
    }

    private static function leerGarnish(string $qr): ?array
    {
        $sd = self::tramo($qr, ...self::FORMATOS['GARNISH']['campos']['sd']);
        // El SD es un codigo corto que empieza con letra (GDC, GSB...). Cualquier otra
        // cosa no es un garnish: asi un QR de asiento, una secuencia de panel cortada
        // ("0001") o basura no se toman como SD.
        if (!preg_match('/^[A-Z][A-Z0-9]{1,4}$/', $sd)) {
            return null;
        }
        return [
            'tipo'      => 'GARNISH',
            'sd'        => $sd,
            'mano'      => null,
            'posicion'  => null,
            'secuencia' => null,
            'fecha_encolado' => null,
        ];
    }

    private static function tramo(string $qr, int $inicio, int $largo): string
    {
        return trim($largo > 0 ? substr($qr, $inicio - 1, $largo) : substr($qr, $inicio - 1));
    }

    private static function fecha(string $s): ?Carbon
    {
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $s, $m)) {
            return null;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || $m[4] > 23 || $m[5] > 59 || $m[6] > 59) {
            return null;
        }
        return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4], (int) $m[5], (int) $m[6]);
    }

    // ------------------------------------------------------------------ modelo

    private static function modelosConfig(): array
    {
        try {
            $row = FactoryConfig::query()->where('key', 'active')->first();
            return $row->data['models'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Numeros de parte de /config que coinciden con el SD, tipo, posicion y mano del QR. */
    private static function buscarModelos(array $modelos, array $d): array
    {
        $familia = self::FAMILIA[$d['tipo']];
        $pm = trim(($d['posicion'] ?? '') . ' ' . ($d['mano'] ?? ''));
        return array_values(array_filter($modelos, function ($m) use ($familia, $d, $pm) {
            $j = $m['jitms'] ?? null;
            if (!is_array($j) || strtoupper((string) ($j['familia'] ?? '')) !== $familia) {
                return false;
            }
            $sds = [strtoupper((string) ($j['sd'] ?? '')), strtoupper((string) ($j['sdTasa'] ?? ''))];
            if (!in_array($d['sd'], $sds, true)) {
                return false;
            }
            if ($d['posicion'] && $d['mano']) {
                return strtoupper(trim((string) ($j['posicionMano'] ?? ''))) === $pm;
            }
            return true;
        }));
    }

    // ------------------------------------------------------------------ curado

    /**
     * Desde cuando la pieza esta curada. curado_fuente: QR (armrest), TBL (panel,
     * desde la base de la app de camaras), SIN_DATO (panel sin TB-L) o NO_APLICA
     * (garnish).
     */
    private static function curado(string $qr, array $d): array
    {
        $minutos = (int) (DoorService::settings()['minutosSecado'] ?? 240);
        $base = null;
        $fuente = 'NO_APLICA';

        if ($d['tipo'] === 'ARM_REST' && !empty($d['fecha_encolado'])) {
            $base = Carbon::parse($d['fecha_encolado']);
            $fuente = 'QR';
        } elseif ($d['tipo'] === 'ARM_REST') {
            // Formato "sec|x|SD|pos|mano" (control final): no trae fecha de encolado.
            $fuente = 'SIN_DATO';
        } elseif ($d['tipo'] === 'PANEL') {
            $base = self::fechaPanelEnTbl($qr, $d);
            $fuente = $base ? 'TBL' : 'SIN_DATO';
        }

        $hasta = $base?->copy()->addMinutes($minutos);
        return [
            'curado_fuente'  => $fuente,
            'curado_minutos' => $minutos,
            'curado_hasta'   => $hasta?->toIso8601String(),
            'curado_ok'      => $hasta ? now()->gte($hasta) : null,
            'curado_faltan_minutos' => $hasta && now()->lt($hasta) ? (int) ceil(now()->diffInMinutes($hasta)) : 0,
        ];
    }

    /**
     * Hora del panel en TB-L (base de la app de camaras, conexion 'tbl', solo lectura).
     * La consulta va en DOOR_TBL_SQL_PANEL (un SELECT con un '?' para el QR que
     * devuelve una columna 'fecha'); se define cuando se vea la tabla real con
     * tbl-explorar.php. Sin eso, o si TB-L no responde, el panel queda sin dato.
     */
    private static function fechaPanelEnTbl(string $qr, array $d): ?Carbon
    {
        $sql = config('door.tbl_sql_panel');
        if (!$sql) {
            return null;
        }
        try {
            $fila = DB::connection('tbl')->selectOne($sql, [$qr]);
            $fecha = $fila->fecha ?? null;
            return $fecha ? Carbon::parse($fecha) : null;
        } catch (\Throwable $e) {
            Log::warning('DoorQrService: TB-L no respondio', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
