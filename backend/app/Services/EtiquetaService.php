<?php

namespace App\Services;

use App\Http\EstadosQr;
use App\Http\LineasModelo;
use App\Models\EpEtiqueta;
use App\Models\EpEtiquetaMid;
use App\Models\Modelos;
use App\Models\Sar\TEtiquetaAirbag;
use DateInterval;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EtiquetaService {

    static function creaEtiquetaAirbagDesdeBaseSAR(string $qr) {
        //ESTADO 2 ES PARA QUE NO ESTE ESCRAPEADA
        $dataAirbag = TEtiquetaAirbag::with('mod')->where('ID', $qr)->where('estado', '<>', 2)->first();
        $data = null;

        if ($dataAirbag) {
            //GENERO LA ETIQUETA EN LA BASE
            try {
                $modeloAirbag = Modelos::where('nombre', $dataAirbag->mod->MODELO)->first();
            } catch (\Throwable $th) {
                $modeloAirbag = null;
            }

            $data = EpEtiqueta::create([
                'airbag'            => true,
                'kanban'            => null,
                'id_etiqueta'       => 0,
                'modelo'            => $modeloAirbag ? $modeloAirbag->nombre : null,
                'lado'              => $dataAirbag->LADO == 'L' ? 'LH' : 'RH',
                'secuencia'         => str_pad($dataAirbag->SECUENCIA, 4, "0", STR_PAD_LEFT),
                'estado'            => EstadosQr::VALIDADA,
                'qr'                => $qr,
                'ubicacion'         => '',
                'tipo'              => 'BC',
                'vehiculo'          => '',
                'codigo'            => '',
                'linea'             => $dataAirbag->LINEA,
                'kanban_impresion'  => '',
                'modelo_id'         => $modeloAirbag ? $modeloAirbag->id : null,
                'linea_real'        => $dataAirbag->LINEA,
            ]);

            EpEtiquetaMid::create([
                'qr'            => $qr,
                'modelo_id'     => $modeloAirbag ? $modeloAirbag?->id : null,
                'linea_real'    => $dataAirbag->LINEA
            ]);
        }

        return $data;
    }

    /**
     * Resuelve un KANBAN de fin de linea (dinamico, generado en JITMS):
     *  - MODELO (sd): del mapeo jitms_qr_templates (EOL_TBAR), tomando
     *    qr_template = substr(qr, 11)  (QR sin fecha(6)+lector(1)+secuencia(4)).
     *  - LADO + secuencia + validacion: de JITMS PRODUCCION.RegistroActividad
     *    (por CodigoKanban). El lado sale de la descripcion del producto (FR RH/LH).
     * Crea/refresca la fila cache en ep_etiquetas. Si el template no esta mapeado -> null
     * (cae a los otros resolutores: airbag / parseo).
     */
    static function creaEtiquetaFrontSeatDesdeJitms(string $qr) {
        // 1) Modelo desde el mapeo de templates (jitms_qr_templates, EOL_TBAR).
        $template = strlen($qr) > 11 ? substr($qr, 11) : $qr;
        $tpl = DB::table('jitms_qr_templates')->where('qr_template', $template)->first();
        if (!$tpl) {
            return null;
        }
        $modelo = $tpl->sd;                 // ej. C4S / C3S / C4L
        $codigo = $tpl->codigo ?? '';

        // 2) Lado / secuencia / validacion desde JITMS RegistroActividad (por CodigoKanban).
        $lado = '';
        $secuencia = ltrim(substr($qr, 7, 4), '0') ?: substr($qr, 7, 4);
        try {
            // Se busca por CodigoKanban O CodigoEscaneado (ambos con el QR completo).
            // Medido sobre 500 escaneos reales de produccion (2026-07-22):
            //   solo CodigoKanban    -> 355/500 (71%)
            //   solo CodigoEscaneado -> 495/500 (99%)
            // Sin esto, ~29% de los asientos quedaban sin LADO (RH/LH) y la pantalla
            // mostraba todas las vistas del modelo en vez de las del lado correcto.
            // Se prioriza CodigoKanban (mas autoritativo) y se cae a CodigoEscaneado.
            // El match es por igualdad con el QR completo, asi que no puede colisionar
            // con codigos de barra de componentes.
            $ra = DB::connection('sqlsrvsar')->selectOne(
                "SELECT TOP 1 ra.Secuencia, p.Descripcion
                   FROM PRODUCCION.RegistroActividad ra
                   LEFT JOIN CATALOGO.Producto p ON p.IdProducto = ra.IdProducto
                  WHERE ra.CodigoKanban = ? OR ra.CodigoEscaneado = ?
                  ORDER BY CASE WHEN ra.CodigoKanban = ? THEN 0 ELSE 1 END,
                           ra.IdRegistroActividad DESC",
                [$qr, $qr, $qr]
            );
            if ($ra) {
                $secuencia = $ra->Secuencia ?? $secuencia;
                $desc = strtoupper($ra->Descripcion ?? '');
                if (strpos($desc, ' RH') !== false) {
                    $lado = 'RH';
                } elseif (strpos($desc, ' LH') !== false) {
                    $lado = 'LH';
                }
            }
        } catch (\Throwable $th) {
            Log::warning('creaEtiquetaFrontSeatDesdeJitms (RegistroActividad) : ' . $th->getMessage());
        }

        // Si JITMS no trajo el lado (sin RegistroActividad todavia, o descripcion
        // sin RH/LH), se deriva del propio QR. Sin lado, la pantalla muestra las
        // vistas de los dos lados.
        if ($lado === '') {
            $lado = self::ladoDesdeQr($qr);
        }

        // El modelo (catalogo estatico) vive en EOL_TBAR. Lo busco por nombre.
        $mod = Modelos::where('nombre', $modelo)->first();

        $datos = [
            'airbag'            => false,
            'kanban'            => null,
            'id_etiqueta'       => 0,
            'modelo'            => $modelo,
            'lado'              => $lado,
            'secuencia'         => str_pad((string) $secuencia, 4, "0", STR_PAD_LEFT),
            'ubicacion'         => '',
            'tipo'              => '',                            // FR no distingue Back/Cushion en el kanban
            'vehiculo'          => '',
            'codigo'            => $codigo,
            'linea'             => '',
            'kanban_impresion'  => $qr,
            'modelo_id'         => $mod?->id,
            'linea_real'        => '',
        ];

        // Refresca la fila cache (o la crea). No pisa el estado si ya existia (QC/scrap).
        $etiqueta = EpEtiqueta::where('qr', $qr)->first();
        if ($etiqueta) {
            $etiqueta->fill($datos)->save();
        } else {
            $etiqueta = EpEtiqueta::create($datos + ['qr' => $qr, 'estado' => EstadosQr::VALIDADA]);
        }

        return $etiqueta;
    }

    static function getEtiquetaPorQr(string $qr) {
        return EpEtiqueta::where('qr', $qr)->first();
    }

    /**
     * Lado (RH/LH) codificado en el kanban dinamico de JITMS (45 chars): la letra
     * R/L aparece dos veces, en las posiciones 18 y 26
     * (ej. 260820139553J00F3J[R]0000001[R]221111142110000000 -> RH).
     * Solo se acepta cuando las dos posiciones coinciden; si no, '' (desconocido).
     */
    public static function ladoDesdeQr(string $qr): string {
        if (strlen($qr) !== 45) {
            return '';
        }
        $a = strtoupper($qr[18]);
        $b = strtoupper($qr[26]);
        if ($a !== $b) {
            return '';
        }
        return $a === 'R' ? 'RH' : ($a === 'L' ? 'LH' : '');
    }

    public function generarEtiqueta(object $etiquetaData) {

        $codigoKanban = $etiquetaData->kanban;
        $linea = $etiquetaData->linea;
        $user = $etiquetaData->userId;
        $lineaOriginal = '';

        $kanban =  KanbanService::getKanban($codigoKanban);
        $partes = ParteService::getPartesByKanban($kanban);

        if (is_null($linea)) {
            $linea = ModeloLineaService::getPrimerLineaDeModelo($kanban->modelo);
        }

        if (is_null($linea)) {
            $linea = "1";
        }

        $lineaOriginal = $linea;

        if (strlen($linea) > 1) {
            $linea = "0"; //Todas las lineas que sean de 2 digitos van en 0 por pedido de TBAR
        }

        $ubicacion = $kanban->modelo->fila->fila;
        if ($ubicacion == 'FRONT') {
            $ubicacion = 'FR';
        } else if ($ubicacion == 'REAR 1') {
            $ubicacion = 'R1';
        } else if ($ubicacion == 'REAR 2') {
            $ubicacion = 'R2';
        } else if ($ubicacion == 'REAR 3') {
            $ubicacion = 'R3';
        } else if ($ubicacion == 'SUP') {
            $ubicacion = 'SU';
        }

        $secuenciaInicial = EtiquetaService::getProximoNumeroSecuencia();

        DB::beginTransaction();

        foreach ($partes as $parte) {
            for ($i = 0; $i < $kanban->modelo->cantidad; $i++) {
                $lado = $parte->lado->lado;

                if ($lado == '100%') {
                    $lado = '00';
                }

                $tipoSeat = 'CS';

                if ($parte->tipo->tipo == 'CUSHION') {
                    $tipoSeat = 'CS';
                } else {
                    if (($lado != 'RH' && $lado != 'LH') || $parte->tipo->tipo == 'BACK') {
                        $tipoSeat = 'BC';
                    }
                }

                $parteCodigo = $parte->codigo;

                if (strlen($parte->codigo) == 13) {
                    $parteCodigo = $parte->codigo . '0';
                } else if (strlen($parte->codigo) == 12) {
                    $parteCodigo = $parte->codigo . '00';
                } else if (strlen($parte->codigo) == 11) {
                    $parteCodigo = $parte->codigo . '-00';
                }

                $secuencia = str_pad($secuenciaInicial, 4, "0", STR_PAD_LEFT);
                $qr = date("Ydm") . "1" . $secuencia . $parteCodigo . $ubicacion . $lado . $tipoSeat  . $linea . '1SAR000';

                if (!$this->validaEtiquetaDuplicada($qr)) {
                    DB::rollBack();
                    Log::error('EpEtiquetaController::store : Se aborta creación de etiquetas. Etiqueta duplicada en el último día : ' . $qr);
                    // return $this->setResponse([], 'El sistema detecto que se van a generar etiquetas duplicadas. Comuníquese con el encargado de IT.', true);
                }

                $secuenciaInicial = $secuenciaInicial + 1;
                if ($secuenciaInicial >= 9999) {
                    $secuenciaInicial = 0;
                }
            }
        }
    }

    public function crearEtiqueta(
        string $codigoKanban,
        Modelos $modelo,
        string $lado,
        int $secuencia,
        EstadosQr $estado,
        string $ubicacion,
        string $tipo,
        string $linea,
        string $lineaReal,
        string $partNumber,
        int $userId
    ) {

        $qr = '';

        $creada = EpEtiqueta::create([
            'kanban_impresion'  => $codigoKanban,
            'id_etiqueta'       => 0,
            'modelo'            => $modelo->nombre,
            'modelo_id'         => $modelo->id,
            'lado'              => $lado,
            'secuencia'         => $secuencia,
            'estado'            => $estado,
            'qr'                => $qr,
            'ubicacion'         => $ubicacion,
            'tipo'              => $tipo,
            'linea'             => $linea,
            'linea_real'        => $lineaReal,
            'codigo'            => $partNumber, //$tipo->codigo,
            'user_id'           => $userId,
            'vehiculo'          => LineasModelo::VEHICULOS[$modelo->nombre],
        ]);

        EpEtiquetaMid::create([
            'qr'            => $qr,
            'modelo_id'     => $modelo->id,
            'linea_real'    => $lineaReal
        ]);
    }

    private function validaEtiquetaDuplicada(string $qr): bool {
        //VERIFICO SI LA ETIQUETA EXISTE EN EL ÚLTIMO DÍA

        $fecha = new DateTime();
        $fecha->sub(new DateInterval('P1D'));
        $existe = EpEtiqueta::where('qr', $qr)->where('created_at', '>=', $fecha)->first();

        return ($existe ? false : true);
    }

    static function getProximoNumeroSecuencia(): int {
        /**
         * FUNCION CONTROLADA OK
         */
        // $existente = EpEtiqueta::orderBy('id', 'DESC')->first();
        // if ($existente) {
        //     $secuencia = intval($existente->secuencia) + 1;

        //     if ($secuencia >= 9999) {
        //         $secuencia = 0;
        //     }
        // } else {
        //     $secuencia = 0;
        // }

        // return $secuencia;
        $secuenciaInicial = 0;

        $existente = EpEtiqueta::where('estado', '<>', 'RETRABAJO')
            ->where('estado', '<>', 'SCRAP')
            ->where(function ($q) {
                $q->where('airbag', false);
                $q->orWhere('airbag', null);
            })
            ->orderBy('id', 'DESC')->first();

        if ($existente) {
            $secuenciaInicial = intval($existente->secuencia) + 1;
            if ($secuenciaInicial >= 9999) {
                $secuenciaInicial = 0;
            }
        } else {
            $secuenciaInicial = 0;
        }

        return $secuenciaInicial;
    }
}
