<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Puesto de produccion "Panel NG" (Produccion 4 -> impresora .90, Produccion 3 ->
 * .68). Un panel vuelve con un defecto que no se cargo en door:
 *   1) se escanea su etiqueta y queda NG (tabla eol_door_produccion_ng, con el puesto);
 *   2) se escanea el panel nuevo: tiene que ser el mismo SD, posicion y mano;
 *   3) se le reimprime la etiqueta al panel nuevo con SU id y todo lo suyo, pero
 *      con la SECUENCIA del panel NG (es el que lo reemplaza en la secuencia).
 * QR: id|secuencia|SD|posicion|mano  (ej. 15213|1|D3H|RR|LH).
 * Los datos de la etiqueta que no vienen en el QR van fijos (ETIQUETA_FIJA).
 */
class DoorReetiquetaService
{
    /** Datos de la etiqueta que no estan en el QR: fijos por ahora (como la foto). */
    public const ETIQUETA_FIJA = [
        'linea'          => 'I',
        'sd_tasa'        => 'C00',
        'linea_producto' => 'HI - LUX',
        'ensamble'       => 'H',
        'producto'       => '67630-0K840',
        'suffix'         => 'FA23',
        'sid'            => 'MM66022805',
        'variante'       => '(D)',
        'clase'          => 'A',
        // Parte de abajo: [titulo, valor]; valor null = recuadro negro.
        'grilla' => [
            [['LOWER', 'LW03'], ['POCKET', 'DS07'], ['PULL', null]],
            [['PLATE', null], ['UPR', 'DQ07'], ['ORNAMENT', null]],
            [['EA PAD', '-'], ['WS', 'P/W'], ['CORNER PAD', 'DA B3, X 2']],
            [['OUT LINE PAD', 'DA B4 X 2, DA B5'], ['NVH', 'DA B8'], ['NON WOVEN', '-']],
        ],
    ];

    /** "15213 | 1 | D3H | RR | LH" o "15213 1 D3H RR LH" -> partes. null si no es valido. */
    public static function parsear(?string $qr): ?array
    {
        $txt = strtoupper(trim((string) $qr));
        if ($txt === '') {
            return null;
        }
        $txt = str_contains($txt, '|')
            ? preg_replace('/\s*\|\s*/', '|', $txt)
            : preg_replace('/\s+/', '|', $txt);
        $p = explode('|', $txt);
        if (count($p) < 5) {
            return null;
        }
        [$id, $sec, $sd, $pos, $mano] = array_map('trim', array_slice($p, 0, 5));
        if ($id === '' || $sec === '' || $sd === '' || !in_array($pos, ['FR', 'RR'], true) || !in_array($mano, ['LH', 'RH'], true)) {
            return null;
        }
        return [
            'qr'        => "{$id}|{$sec}|{$sd}|{$pos}|{$mano}",
            'id'        => $id,
            'secuencia' => $sec,
            'sd'        => $sd,
            'posicion'  => $pos,
            'mano'      => $mano,
        ];
    }

    public static function impresora(string $puesto): ?string
    {
        return config('door.impresoras')[strtoupper(trim($puesto))] ?? null;
    }

    /** 1) Panel con defecto: queda NG. Si ya estaba NG sin reetiquetar, devuelve ese. */
    public static function marcarNg(string $qr, string $puesto, ?string $usuario = null): array
    {
        $p = self::parsear($qr);
        if (!$p) {
            throw new \RuntimeException('El QR no tiene el formato id|secuencia|SD|posicion|mano', 422);
        }
        $puesto = strtoupper(trim($puesto));
        if (!self::impresora($puesto)) {
            throw new \RuntimeException("Puesto desconocido: {$puesto}", 422);
        }
        $abierto = DB::table('eol_door_produccion_ng')->where('qr_ng', $p['qr'])->where('estado', 'NG')->orderByDesc('id')->first();
        if ($abierto) {
            return (array) $abierto + ['ya_estaba' => true];
        }
        $id = DB::table('eol_door_produccion_ng')->insertGetId([
            'qr_ng'          => $p['qr'],
            'id_ng'          => $p['id'],
            'secuencia'      => $p['secuencia'],
            'sd'             => $p['sd'],
            'posicion'       => $p['posicion'],
            'mano'           => $p['mano'],
            'puesto'         => $puesto,
            'estado'         => 'NG',
            'usuario_nombre' => $usuario,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        return (array) DB::table('eol_door_produccion_ng')->find($id) + ['ya_estaba' => false];
    }

    /**
     * 2+3) Panel nuevo: mismo SD/posicion/mano que el NG; se imprime su etiqueta con
     * la secuencia del NG. $prueba: imprime con la leyenda "PRUEBA - NO USAR".
     */
    public static function reetiquetar(int $ngId, string $qrNuevo, string $puesto, bool $prueba = false): array
    {
        $ng = DB::table('eol_door_produccion_ng')->find($ngId);
        if (!$ng) {
            throw new \RuntimeException('No encuentro el panel NG', 404);
        }
        $n = self::parsear($qrNuevo);
        if (!$n) {
            throw new \RuntimeException('El QR del panel nuevo no tiene el formato id|secuencia|SD|posicion|mano', 422);
        }
        if ($n['id'] === $ng->id_ng) {
            throw new \RuntimeException('Es el mismo panel NG: escanea el panel nuevo', 422);
        }
        foreach (['sd' => 'modelo (SD)', 'posicion' => 'posicion', 'mano' => 'mano'] as $campo => $nombre) {
            if ($n[$campo] !== $ng->{$campo}) {
                throw new \RuntimeException("El panel nuevo es de {$nombre} {$n[$campo]} y el NG es {$ng->{$campo}}", 422);
            }
        }
        $puesto = strtoupper(trim($puesto ?: (string) $ng->puesto));
        $ip = self::impresora($puesto);
        if (!$ip) {
            throw new \RuntimeException("Puesto desconocido: {$puesto}", 422);
        }

        // Etiqueta del panel nuevo: su id y sus datos, con la secuencia del NG.
        $etiqueta = $n;
        $etiqueta['secuencia'] = $ng->secuencia;
        $etiqueta['qr'] = "{$n['id']}|{$ng->secuencia}|{$n['sd']}|{$n['posicion']}|{$n['mano']}";

        self::imprimir($ip, self::zpl($etiqueta, $prueba));

        DB::table('eol_door_produccion_ng')->where('id', $ngId)->update([
            'estado'      => 'REETIQUETADO',
            'qr_nuevo'    => $n['qr'],
            'qr_impreso'  => $etiqueta['qr'],
            'impresora'   => $ip,
            'impreso_at'  => now(),
            'impresiones' => DB::raw('impresiones + 1'),
            'updated_at'  => now(),
        ]);
        return ['ng' => DB::table('eol_door_produccion_ng')->find($ngId), 'qr_impreso' => $etiqueta['qr'], 'impresora' => $ip];
    }

    /** Vuelve a imprimir la ultima etiqueta de un NG ya reetiquetado. */
    public static function reimprimir(int $ngId, bool $prueba = false): array
    {
        $ng = DB::table('eol_door_produccion_ng')->find($ngId);
        if (!$ng || !$ng->qr_impreso) {
            throw new \RuntimeException('Ese panel todavia no tiene etiqueta impresa', 404);
        }
        $ip = $ng->impresora ?: self::impresora((string) $ng->puesto);
        self::imprimir($ip, self::zpl(self::parsear($ng->qr_impreso), $prueba));
        DB::table('eol_door_produccion_ng')->where('id', $ngId)->update(['impresiones' => DB::raw('impresiones + 1'), 'updated_at' => now()]);
        return ['qr_impreso' => $ng->qr_impreso, 'impresora' => $ip];
    }

    // ------------------------------------------------------------------ impresion

    /** Manda el ZPL a la Zebra por TCP 9100. */
    public static function imprimir(string $ip, string $zpl): void
    {
        $sock = @fsockopen($ip, 9100, $errno, $errstr, 5);
        if (!$sock) {
            throw new \RuntimeException("No se pudo conectar con la impresora {$ip} ({$errstr})", 503);
        }
        stream_set_timeout($sock, 10);
        fwrite($sock, $zpl);
        fclose($sock);
    }

    /**
     * Etiqueta 4x4" (ZT231 203 dpi: 812x812 puntos), misma grilla que la original:
     * arriba los datos (secuencia, SD, posicion y mano del QR + los fijos), el QR, y
     * abajo la grilla de componentes fija.
     */
    public static function zpl(array $e, bool $prueba = false): string
    {
        $f = self::ETIQUETA_FIJA;
        // Ajuste a la etiqueta fisica (config door.etiqueta): el diseno esta hecho en
        // 812x812 puntos; se escala en ancho/alto ('escala_x'/'escala_y') y se corre
        // con 'x'/'y' (puntos).
        $cfg = (array) config('door.etiqueta');
        $sx = (float) ($cfg['escala_x'] ?? 1);
        $sy = (float) ($cfg['escala_y'] ?? 1);
        $ox = (int) ($cfg['x'] ?? 0);
        $oy = (int) ($cfg['y'] ?? 0);
        $kx = fn ($v) => max(1, (int) round($v * $sx));
        $ky = fn ($v) => max(1, (int) round($v * $sy));
        $t = fn ($v) => str_replace(['^', '~'], ['', ''], (string) $v);
        // Texto en recuadro: x, y, ancho, alto de letra, texto, alineacion (L/C)
        $txt = fn (int $x, int $y, int $w, int $h, $v, string $al = 'L') =>
            "^FO{$kx($x)},{$ky($y)}^A0N,{$ky($h)},{$kx($h)}^FB{$kx($w)},1,0,{$al},0^FD" . $t($v) . "^FS";
        $caja = fn (int $x, int $y, int $w, int $h, int $g = 2) => "^FO{$kx($x)},{$ky($y)}^GB{$kx($w)},{$ky($h)}," . max(2, min($kx($g), $ky($g))) . "^FS";
        $lineaH = fn (int $x, int $y, int $w) => "^FO{$kx($x)},{$ky($y)}^GB{$kx($w)},2,2^FS";
        $lineaV = fn (int $x, int $y, int $h) => "^FO{$kx($x)},{$ky($y)}^GB2,{$ky($h)},2^FS";
        $k = $kx;

        $z = ['^XA', '^CI28', '^PW832', '^LL' . (int) ($cfg['largo'] ?? 812), "^LH{$ox},{$oy}"];
        // Oscuridad (-30..30, relativa a la de la impresora) y velocidad (pulg/s)
        $z[] = '^MD' . (int) ($cfg['oscuridad'] ?? 0);
        $z[] = '^PR' . (int) ($cfg['velocidad'] ?? 4);
        $z[] = $caja(8, 8, 796, 796, 3);

        // Fila 1-2: Secuencia TASA | linea | SD TASA (alto)
        $z[] = $lineaV(300, 8, 204);
        $z[] = $lineaV(500, 8, 204);
        $z[] = $lineaH(8, 118, 492);
        $z[] = $txt(18, 16, 280, 22, 'Secuencia TASA');
        $z[] = $txt(18, 42, 280, 70, $e['secuencia']);
        $z[] = $txt(300, 30, 200, 70, $f['linea'], 'C');
        $z[] = $txt(510, 16, 290, 22, 'SD TASA');
        $z[] = $txt(500, 70, 304, 90, $f['sd_tasa'], 'C');
        $z[] = $txt(18, 124, 280, 22, 'Línea de Producto');
        $z[] = $txt(18, 152, 280, 50, $f['linea_producto']);
        $z[] = $txt(310, 126, 190, 24, 'Fecha:  ' . now()->format('j/n/Y'));
        $z[] = $txt(310, 155, 190, 24, 'Hora:  ' . now()->format('H:i'));
        $z[] = $txt(310, 184, 190, 24, 'Ensamble:  ' . $f['ensamble']);

        // Fila 3: Producto | Suffix | SID
        $z[] = $lineaH(8, 212, 796);
        $z[] = $lineaV(300, 212, 70);
        $z[] = $lineaV(500, 212, 70);
        $z[] = $txt(18, 218, 280, 20, 'Producto');
        $z[] = $txt(18, 242, 280, 34, $f['producto'], 'C');
        $z[] = $txt(310, 218, 190, 20, 'Suffix');
        $z[] = $txt(300, 242, 200, 34, $f['suffix'], 'C');
        $z[] = $txt(510, 218, 290, 20, 'SID');
        $z[] = $txt(500, 242, 304, 34, $f['sid'], 'C');

        // Fila 4: QR | SD (variante) / posicion mano | clase
        $z[] = $lineaH(8, 282, 796);
        // QR: aumento 4 (version 3, 29 modulos = ~116 puntos) para que entre en su fila.
        $z[] = "^FO{$kx(20)},{$ky(290)}^BQN,2," . (int) ($cfg["qr_aumento"] ?? 4) . "^FDQA," . $t($e["qr"]) . "^FS";
        $z[] = $txt(180, 298, 220, 80, $e['sd'], 'C');
        $z[] = $txt(400, 308, 200, 50, $f['variante'], 'C');
        $z[] = $txt(180, 372, 220, 50, $e['posicion'], 'C');
        $z[] = $txt(400, 372, 200, 50, $e['mano'], 'C');
        $z[] = $lineaV(600, 282, 148);
        $z[] = $lineaH(650, 300, 104);
        $z[] = $txt(600, 330, 204, 80, $f['clase'], 'C');

        // Grilla de abajo (fija): 4 filas x 3 columnas
        $y0 = 430;
        $alto = 93;
        $cols = [8, 273, 538, 804];
        $z[] = $lineaH(8, $y0, 796);
        foreach ($f['grilla'] as $i => $fila) {
            $y = $y0 + $i * $alto;
            if ($i > 0) {
                $z[] = $lineaH(8, $y, 796);
            }
            foreach ($fila as $j => [$titulo, $valor]) {
                $x = $cols[$j];
                $w = $cols[$j + 1] - $x;
                if ($j > 0) {
                    $z[] = $lineaV($x, $y, $alto);
                }
                $z[] = $txt($x, $y + 6, $w, 22, $titulo, 'C');
                if ($valor === null) {
                    $z[] = $caja($x + 10, $y + 32, $w - 20, 52, 52);
                } else {
                    $h = strlen($valor) > 12 ? 26 : 34;
                    $z[] = $txt($x, $y + 42, $w, $h, $valor, 'C');
                }
            }
        }

        if ($prueba) {
            $z[] = $caja(60, 180, 692, 90, 90);
            $z[] = "^FO{$kx(60)},{$ky(195)}^A0N,{$ky(64)},{$kx(64)}^FR^FB{$kx(692)},1,0,C,0^FDPRUEBA - NO USAR^FS";
        }
        $z[] = '^PQ1';
        $z[] = '^XZ';
        return implode("\n", $z);
    }
}
