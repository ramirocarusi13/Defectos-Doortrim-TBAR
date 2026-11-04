<?php

namespace App\Http\Controllers;

use App\Http\EstadoFalla;
use App\Http\EstadosQr;
use App\Models\EpEtiqueta;
use App\Models\FallasInformadas;
use App\Models\Partes;
use App\Services\EtiquetaService;
use App\Services\FallaService;
use App\Services\ScrapService;
use App\Services\TiendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FallasInformadasController extends Controller {

    public function filter(Request $request) {
        $fallaService = new FallaService();
        return $this->setResponse($fallaService->filtrar($request));
    }

    public function storeFallaInterna(Request $request) {
        // Log::alert($request);
        $linea = $request->lineaActiva;
        $user = $request->user;
        $defectos = $request->piezas;
        $conScrap = $request->conScrap;

        $fallaService = new FallaService;

        foreach ($defectos as $defecto) {
            $falla = $defecto['falla']['id'];
            $operacion = $defecto['data']['operacion']['id'];
            $tipoLinea = ($defecto['data']['linea'] == '' || $defecto['data']['linea'] == 'null' || is_null($defecto['data']['linea'])) ? 'M' : $defecto['data']['linea'];
            $member = $defecto['data']['member']['id'];
            $cantidad = intval($defecto['cantidad']);

            // if ($conScrap == 1) {
            //     $tiendaService = new TiendaService();
            //     $tiendaService->crearPedido($user, $falla, $linea);
            // }

            if ($cantidad == 0) {
                $cantidad = 1;
            }

            // $tiendaService->agregaPiezas($defecto['piezas'], $cantidad);

            for ($i = 0; $i < $cantidad; $i++) {
                $creaFallas = $fallaService->creaFallaDesdePiezas(
                    $defecto['piezas'],
                    $falla,
                    $operacion,
                    $member,
                    $linea,
                    $tipoLinea,
                    $user,
                    $conScrap
                );
            }

            if (!$creaFallas) {
                return $this->setResponse([], 'Ocurrio un error. Comuníquese con el encargado de sistemas.', true);
            }
        }

        return $this->setResponse([]);
    }

    public function cancelarCargaOperarioRetrabajo(Request $request) {

        $fallaService = new FallaService();
        return $this->setResponse($fallaService->cancelarDatosRetrabajo($request));
    }

    public function getFallasSinEtiqueta(Request $request) {
        $fallas = FallaService::getFallasPendientesSinEtiqueta($request->linea);
        return $this->setResponse($fallas->toArray());
    }

    public function getFallasScrapPendientes() {
        $fallas = FallaService::getFallasPendientesScrap();
        return $this->setResponse($fallas->toArray());
    }

    public function store(Request $request) {

        $esSinEtiqueta = $request?->sinetiqueta;

        // Log::alert($request);
        try {
            if (is_array($request?->linea)) {
                if (isset($request?->linea['codigo'])) {
                    $linea = $request?->linea['codigo'];
                } else {
                    $linea = $request?->linea;
                }
            } else {
                $linea = $request?->linea;
            }
        } catch (\Throwable $th) {
            //throw $th;
            $linea = $request?->linea;
        }

        // $linea = $request?->linea['codigo'];

        if ($esSinEtiqueta) {
            $fallaService = new FallaService;
            $ladoId = $request?->ladoId;
            $tipoId = $request?->tipoId;
            $modelo = $request?->modelo;

            $creaEtiquetas = $fallaService->creaFallaSinEtiqueta($request->user, $ladoId, $tipoId, $linea, $modelo);

            if ($creaEtiquetas) {
                return $this->setResponse([], "Actualizado correctamente!");
            }

            return $this->setResponse([], "Ocurrió un error al crear la falla");
        }

        // $linea = $request?->linea;
        $etiqueta = EtiquetaService::getEtiquetaPorQr($request->qr);

        if (!$etiqueta) {
            return $this->setResponse([], "La etiqueta ingresada no existe.", true);
        }

        $fallaService = new FallaService;

        $creaEtiquetas = $fallaService->crearFallaDesdeCirculos($request->circles, $request->user, $request->qr, $linea);

        //ACTUALIZO POR SI IMPRIMIERON PARA OTRA LINEA
        EpEtiqueta::where('id', $etiqueta->id)->update(['linea' => $linea, 'linea_real' => $linea]);

        if ($creaEtiquetas) {
            if ($request->scrapFunda) {

                try {
                    $funda = Partes::where('codigo', $etiqueta->codigo)->first();
                    if (!$funda) {
                        $funda = Partes::where('codigo', substr($etiqueta->codigo, 0, strlen($etiqueta->codigo) - 3))->first();
                    }

                    $scrapService = new ScrapService;
                    $scrapeo = $scrapService->generaScrapDeFundaCompleta($funda, $request->user, ($linea ? $linea : $etiqueta?->linea_real), $creaEtiquetas?->id);

                    if ($scrapeo) {
                        EpEtiqueta::where('id', $etiqueta->id)->update(['estado' => EstadosQr::SCRAP]);
                        // FallasInformadas::where('id', $creaEtiquetas?->id)->update(['estado' => EstadoFalla::SCRAP]);
                        FallasInformadas::where('qr', $request->qr)->where(function ($q) {
                            $q->where('estado', EstadoFalla::PENDIENTE);
                            $q->orWhere('estado', EstadoFalla::RECHAZADO);
                        })->update(['estado' => EstadoFalla::SCRAP]);
                    }
                } catch (\Throwable $th) {
                    //throw $th;
                    Log::error("FallasInformadasController::store : " . $th->getMessage());
                }
            }

            return $this->setResponse([], "Actualizado correctamente!");
        } else {
            return $this->setResponse([], "Ocurrió un error al crear las fallas");
        }
    }

    public function cambiarDatosRetrabajo(Request $request) {
        // Log::alert($request);
        $fallaService = new FallaService;
        $fallaService->cambiaDatosRetrabajo($request->id, $request->operario, $request->main ? 'MAIN' : 'SUB', $request->userAutorizante, $request->operacion, $request->linea);

        return $this->setResponse([]);
    }

    public function cambiarEstadoRetrabajo(Request $request) {
        $fallaService = new FallaService;
        $errorCambio = $fallaService->cambiaEstadoRetrabajo($request->id, $request->user, $request->estado);

        if ($errorCambio) {
            return $this->setResponse([], $errorCambio, true);
        } else {
            return $this->setResponse([]);
        }
    }

    public function getFmds(Request $request) {
        $fallaService = new FallaService;
        $fallas = $fallaService->fallasFMDS($request);

        return $this->setResponse($fallas);
    }

    public function getFallasAndon(Request $request) {
        $fallaService = new FallaService;
        $fallas = $fallaService->getFallasParaAndon($request);

        return $this->setResponse($fallas);
    }
}
