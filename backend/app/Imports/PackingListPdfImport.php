<?php

namespace App\Imports;

use App\Models\MaterialesPiezas;
use App\Models\RecepcionPackingList;
use Smalot\PdfParser\Parser;
use Throwable;

class PackingListPdfImport {
    private const PROVEEDOR_SEIREN = 1;
    private const PROVEEDOR_AUNDE = 2;

    protected $proveedorId = null;
    public $operacionId = null;
    public $remitos = [];
    public $errors = [];

    public function __construct($proveedorId) {
        $this->proveedorId = intval($proveedorId);
        $this->operacionId = $this->obtenerNumeroOperacion();
    }

    public function import($file) {
        if (!in_array($this->proveedorId, [self::PROVEEDOR_SEIREN, self::PROVEEDOR_AUNDE])) {
            $this->errors[] = 'El proveedor indicado no tiene lectura PDF configurada.';
            return;
        }

        try {
            $text = $this->extraerTexto($file);
        } catch (Throwable $th) {
            $this->errors[] = 'No se pudo leer el PDF: ' . $th->getMessage();
            return;
        }

        $remito = $this->proveedorId === self::PROVEEDOR_AUNDE
            ? $this->obtenerRemitoAunde($text)
            : $this->obtenerRemitoSeiren($text);

        if (!$remito) {
            $this->errors[] = 'No se pudo obtener el nro de remito/invoice del PDF.';
            return;
        }

        $items = $this->proveedorId === self::PROVEEDOR_AUNDE
            ? $this->obtenerItemsAunde($text)
            : $this->obtenerItemsSeiren($text);

        foreach ($items as $item) {
            $codigo = $this->proveedorId === self::PROVEEDOR_AUNDE
                ? $this->normalizarCodigoAunde($item['codigo'])
                : $this->normalizarCodigoSeiren($item['codigo']);
            $material = $this->verificaExistenciaMaterial($codigo);

            if (!$material) {
                continue;
            }

            $datos = [
                'remito'    => $remito,
                'lote'      => $item['lote'] ?? null,
                'codigo'    => $codigo,
                'cantidad'  => $item['cantidad'],
            ];

            if (floatval($datos['cantidad']) <= 0) {
                continue;
            }

            $this->agregaRemitos($datos);

            RecepcionPackingList::create([
                'codigo'        => $datos['codigo'],
                'remito'        => $datos['remito'],
                'lote'          => $datos['lote'],
                'cantidad'      => floatval($datos['cantidad']),
                'proveedor_id'  => $this->proveedorId,
                'operacion'     => $this->operacionId
            ]);
        }
    }

    private function extraerTexto($file) {
        $path = is_string($file) ? $file : $file->getRealPath();
        $parser = new Parser();

        return $parser->parseFile($path)->getText();
    }

    private function obtenerItemsSeiren($text) {
        $items = [];
        $lines = preg_split('/\R/u', $text);

        foreach ($lines as $line) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $line));

            if (!preg_match('/^\d+,\d+\s+M\s+/u', $line)) {
                continue;
            }

            if (preg_match('/^(\d+,\d+)\s+M\s+(.+)\(([A-Z0-9-]+)\).*$/u', $line, $matches)) {
                $items[] = [
                    'cantidad'      => $this->numeroDecimal($matches[1]),
                    'codigo'        => $matches[3],
                    'descripcion'   => trim($matches[2]),
                ];
                continue;
            }

            if (preg_match('/^(\d+,\d+)\s+M\s+(.+?)(?:\s+\d+,\d+){1,}\s*$/u', $line, $matches)) {
                $codigo = $this->obtenerCodigoDesdeDescripcionSeiren($matches[2]);

                if ($codigo) {
                    $items[] = [
                        'cantidad'      => $this->numeroDecimal($matches[1]),
                        'codigo'        => $codigo,
                        'descripcion'   => trim($matches[2]),
                    ];
                }
            }
        }

        if (count($items) === 0) {
            $this->errors[] = 'No se encontraron items de detalle en el PDF.';
        }

        return $items;
    }

    private function obtenerCodigoDesdeDescripcionSeiren($descripcion) {
        if (preg_match_all('/\b[A-Z]{2,}[A-Z0-9-]*\d[A-Z0-9-]*\b/u', $descripcion, $matches)) {
            return end($matches[0]);
        }

        return null;
    }

    private function obtenerItemsAunde($text) {
        $items = [];
        $codigoActual = null;
        $lines = preg_split('/\R/u', $text);

        foreach ($lines as $line) {
            $line = trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line));

            if (preg_match('/^Client Code:\s*([A-Z0-9-]+)/iu', $line, $matches)) {
                $codigoActual = strtoupper(trim($matches[1]));
                continue;
            }

            if (!$codigoActual || stripos($line, 'TOTAL') === 0) {
                continue;
            }

            if (!preg_match('/^([A-Z0-9\/]+)\*?\s+(\d+,\d+)m\b/iu', $line, $matches)) {
                continue;
            }

            $lote = $this->obtenerLoteAunde($matches[1]);

            if (!$lote) {
                continue;
            }

            $items[] = [
                'codigo'    => $codigoActual,
                'lote'      => $lote,
                'cantidad'  => $this->numeroDecimal($matches[2]),
            ];
        }

        if (count($items) === 0) {
            $this->errors[] = 'No se encontraron items de detalle en el PDF.';
        }

        return $items;
    }

    private function obtenerLoteAunde($value) {
        $value = strtoupper(trim($value));

        if (preg_match('/^\d+([A-Z].+)$/u', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^\d(\d{7,}\/.+)$/u', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function obtenerRemitoAunde($text) {
        if (preg_match('/Invoice:\s*([A-Z0-9-]+)/iu', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function obtenerRemitoSeiren($text) {
        if (preg_match('/Commercial Invoice n[ºo]\s+([A-Z0-9-]+)/iu', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function normalizarCodigoSeiren($codigo) {
        $codigo = strtoupper(trim($codigo));
        $alias = [
            'PVCGD-BLAC13-00'  => 'PVCGD-BLA13-00',
            'ETERN-BLA-30-0'   => 'ETERN-BLA30-00',
        ];

        return $alias[$codigo] ?? $codigo;
    }

    private function normalizarCodigoAunde($codigo) {
        return strtoupper(trim($codigo));
    }

    private function numeroDecimal($value) {
        return round(floatval(str_replace(',', '.', $value)), 3);
    }

    private function agregaRemitos($datos) {
        $encontro = false;

        foreach ($this->remitos as $remito) {
            if ($remito == $datos['remito']) {
                $encontro = true;
                break;
            }
        }

        if (!$encontro && !empty($datos['remito'])) {
            array_push($this->remitos, $datos['remito']);
        }
    }

    private function obtenerNumeroOperacion() {
        $operacion = RecepcionPackingList::selectRaw('MAX(operacion) as operacion')->first();

        if ($operacion) {
            return intval($operacion->operacion) + 1;
        }

        return 1;
    }

    private function verificaExistenciaMaterial($codigo) {
        $material = MaterialesPiezas::where('codigo_proveedor', $codigo)->where('proveedor_id', $this->proveedorId)->first();

        if (!$material) {
            $material = MaterialesPiezas::where('codigo', $codigo)->where('proveedor_id', $this->proveedorId)->first();
        }

        if (!$material) {
            $material = MaterialesPiezas::where('codigo', $codigo)->whereNull('proveedor_id')->first();
        }

        if (!$material) {
            array_push($this->errors, "El codigo " . $codigo . " no existe o no esta asociado al proveedor indicado.");
            return null;
        }

        return $material;
    }
}
