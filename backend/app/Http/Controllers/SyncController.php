<?php

namespace App\Http\Controllers;

use App\Http\Depositos;
use App\Http\WmsUnidades;
use App\Models\Configuracion;
use App\Models\EpEtiqueta;
use App\Models\MovimientosContenido;
use App\Models\Sar\TEtiquetaAirbag;
use App\Models\Sar\TRegistros;
use App\Services\EtiquetaService;
use App\Services\StockService;
use DateTime;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller {
    public function syncElementosFunda() {
        $config = Configuracion::where('clave', 'id_actualizacion_elementos')->first();
        $idFG = $config->valor;

        $stockService = new StockService;

        $elementosPendientes = TRegistros::selectRaw('T_REGISTROS.ID,T_REGISTROS.ID_CODIGO,T_REGISTROS.CANT,T_C_TIPOS.TIPO')
            ->leftJoin('T_CODIGOS', 'T_REGISTROS.ID_CODIGO', 'T_CODIGOS.ID')
            ->leftJoin('T_C_TIPOS', 'T_CODIGOS.ID_C_TIPO', 'T_C_TIPOS.ID')
            ->where(function ($q) {
                $q->where('T_C_TIPOS.TIPO', '!=', 'CUSHION');
                $q->where('T_C_TIPOS.TIPO', '!=', 'BACK');
                $q->where('T_C_TIPOS.TIPO', '!=', 'SET');
            })
            ->where('T_REGISTROS.ID', '>', $idFG)
            ->orderBy('T_REGISTROS.ID', 'ASC')
            ->get();

        if (count($elementosPendientes) > 0) {
            $stockService->setFinalizado(true);
            $stockService->generaMovimiento(WmsUnidades::DOOR_TRIM);
        }

        if (count($elementosPendientes) > 0) {
            $posicionElemento = $stockService->obtenerPosicionDefault(Depositos::ARHRDT);
            try {
                foreach ($elementosPendientes as $elemento) {
                    $unidad = null;

                    if (trim($elemento->TIPO) == 'H/R') {
                        $unidad = WmsUnidades::HEAD_REST;
                    } else if (trim($elemento->TIPO) == 'A/R') {
                        $unidad = WmsUnidades::ARM_REST;
                    } else if (trim($elemento->TIPO) == 'DOOR ORNAMENT' || trim($elemento->TIPO) == 'DOOR ARM REST') {
                        $unidad = WmsUnidades::DOOR_TRIM;
                        // } else if ($elemento->TIPO == 'DOOR ARM REST') {
                        // $unidad = WmsUnidades::DOOR_TRIM;
                    }

                    // $existe = MovimientosContenido::where('ref_id', $elemento->ID)->first();

                    $stockService->insertaDetalle(
                        $elemento->ID_CODIGO,
                        intval($elemento->CANT),
                        $posicionElemento->id,
                        null,
                        $unidad,
                        $elemento->ID,
                        true
                    );

                    $idFG = $elemento->ID;
                }

                Configuracion::where('clave', 'id_actualizacion_elementos')->update(['valor' => $idFG]);
            } catch (\Throwable $th) {
                Configuracion::where('clave', 'id_actualizacion_elementos')->update(['valor' => $idFG]);

                Log::error("SyncElementosFunda : " . $th->getMessage());
                return null;
            }
        }

        return $this->setResponse([]);
    }

    public function syncEtiquetasBack() {
        $idFG = Configuracion::where('clave', 'fh_actualizacion_etiqueta_back')->first();
        $fechaFabricacion = DateTime::createFromFormat('Y-m-d H:i:s.u', $idFG->valor);

        //RECORRO LOS KANBAN DE FG PENDIENTES
        $etiquetasPendientes = TEtiquetaAirbag::where('HORA_FAB', '>=', $fechaFabricacion->format('Y-d-m H:i:s'))
            ->orderBy('HORA_FAB', 'ASC')
            // ->take(00)
            ->get();

        try {
            foreach ($etiquetasPendientes as $etiquetaPendiente) {

                $qr = $etiquetaPendiente->ID;
                $existeEtiqueta = EpEtiqueta::select('id')->where('qr', $qr)->first();

                if (!$existeEtiqueta) {
                    EtiquetaService::creaEtiquetaAirbagDesdeBaseSAR($qr);
                }

                $nuevaFecha = $etiquetaPendiente->HORA_FAB;
            }

            $fecha = DateTime::createFromFormat('Y-m-d H:i:s.u', $nuevaFecha);
            Configuracion::where('clave', 'fh_actualizacion_etiqueta_back')->update(['valor' => $fecha->format('Y-m-d H:i:s.u')]);
        } catch (\Throwable $th) {
            Log::error("syncEtiquetasBack : " . $th->getMessage());
            return null;
        }

        return $this->setResponse([]);
    }
}
