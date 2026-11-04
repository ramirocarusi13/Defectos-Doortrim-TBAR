<?php

namespace App\Services;

use App\Http\Depositos;
use App\Http\TipoItemDespacho;
use App\Http\WmsUnidades;
use App\Models\DespachosItems;
use App\Models\DespachosPedido;
use App\Models\MArmRest;
use App\Models\MDoorTrim;
use App\Models\MHeadRest;
use App\Models\Modelos;
use App\Models\MovimientosContenido;
use App\Models\StockKanbans;
use App\Models\Ubicaciones;
use Illuminate\Support\Facades\Log;


class DespachoService {

    public $despachoId = null;
    public ?Ubicaciones $posicionDespacho;
    public ?Ubicaciones $posicionElementos;
    public ?StockService $stockService;



    public function __construct(int|null $despachoId) {
        $this->despachoId = $despachoId;

        $this->stockService = new StockService;

        $this->posicionDespacho = $this->stockService->obtenerPosicionDefault(Depositos::DESPACHOS);
        $this->posicionElementos = $this->stockService->obtenerPosicionDefault(Depositos::ARHRDT);
    }

    static function verificaFIFOEnDespacho(int $despachoId) {
        $notificar = [];

        $items = DespachosItems::where('despacho_id', $despachoId)
            ->where(function ($q) {
                $q->where('deposito_id', Depositos::RACKS);
                $q->orWhere('deposito_id', Depositos::TEMPORAL_A);
            })
            ->orderBy('modelo', 'DESC')
            ->get();

        $excluir = [];

        if ($items) {

            $excluirUbicaciones = DespachosItems::select('posicion')->where('despacho_id', $despachoId)
                ->where(function ($q) {
                    $q->where('deposito_id', Depositos::RACKS);
                    $q->orWhere('deposito_id', Depositos::TEMPORAL_A);
                })
                ->orderBy('modelo', 'DESC')
                ->get();

            foreach ($items as $item) {
                //Verifico si debería haber sacado un kanban anterior
                $antiguo = StockKanbans::where('modelo', $item->modelo)
                    ->where('cantidad', '>', 0)
                    ->whereNotIn('ref', $excluir)
                    ->whereNotIn('ubicacion', $excluirUbicaciones)
                    ->where('ref', '<', $item->kanban)
                    ->where(function ($q) {
                        $q->where('deposito_id', Depositos::RACKS);
                        $q->orWhere('deposito_id', Depositos::TEMPORAL_A);
                    })
                    ->orderBy('ref', 'ASC')->first();

                if ($antiguo) {
                    //INFORMO
                    array_push($notificar, ['original' => $item->kanban, 'antiguo' => $antiguo->ref, 'modelo' => $item->modelo, 'pos_original' => $item->posicion, 'pos_antiguo' => $antiguo->ubicacion]);
                    array_push($excluir, $antiguo->ref);
                }
            }
        }

        Log::alert($notificar);
    }

    public function obtenerItemsFinalizadosDespacho() {
        $items = DespachosItems::where('despacho_id', $this->despachoId)
            ->where(function ($q) {
                $q->where('pickeado', true);
                $q->orWhere('tipo', TipoItemDespacho::DOOR_TRIM);
            })
            ->where('estado_calidad', 'aprobado')
            ->get();

        return $items;
    }

    private function egresaElementosModelo(string $modeloNombre) {
        $modelo = Modelos::where('nombre', $modeloNombre)->first();
        // Log::alert($modelo);
        if (!is_null($modelo?->hr) && $modelo?->hr != '-') {
            //DOY DE BAJA LA CABECERA
            $headRest = MHeadRest::where('nombre', $modelo?->hr)->first();
            if ($headRest) {
                $this->stockService->insertaDetalle($headRest->codigo, -20, $this->posicionElementos->id, null, WmsUnidades::HEAD_REST);
            }
        }

        if (!is_null($modelo?->ctrhr) && $modelo?->ctrhr != '-') {
            //DOY DE BAJA LA CABECERA CENTRAL
            $headRest = MHeadRest::where('nombre', $modelo?->ctrhr)->first();
            if ($headRest) {
                $this->stockService->insertaDetalle($headRest->codigo, -10, $this->posicionElementos->id, null, WmsUnidades::HEAD_REST);
            }
        }

        if (!is_null($modelo?->ar) && $modelo?->ar != '-') {
            //DOY DE BAJA EL APOYA BRAZOS
            $armRest = MArmRest::where('nombre', $modelo?->ar)->first();
            if ($armRest) {
                $this->stockService->insertaDetalle($armRest->codigo, -10, $this->posicionElementos->id, null, WmsUnidades::ARM_REST);
            }
        }
    }

    private function obtenerDoorTrimPedido($modelo) {
        return DespachosPedido::where('despacho_id', $this->despachoId)->where('modelo', $modelo)->where('tipo', TipoItemDespacho::DOOR_TRIM)->first();
    }

    public function egresoStockItems() {
        $items = $this->obtenerItemsFinalizadosDespacho();

        foreach ($items as $item) {
            // Log::alert($item);
            if ($item->tipo == TipoItemDespacho::COVER) {
                // Log::alert("PASO COVER");
                $posicionExistente = MovimientosContenido::with('kanban.modelo')->where('ref', $item->kanban)->where('ubicacion_id', $this->posicionDespacho->id)->where('cantidad', '>', 0)->first();

                if ($posicionExistente) {
                    $this->stockService->insertaDetalle($item->kanban, -1, $this->posicionDespacho->id, $posicionExistente->lote, WmsUnidades::KANBAN);
                    // Log::alert($item->modelo);
                    //POR CADA MODELO DOY DE BAJA SUS PIEZAS (A/R, H/R Y CTRHR)
                    $this->egresaElementosModelo($item->modelo);
                }
            } else if ($item->tipo == TipoItemDespacho::DOOR_TRIM) {
                // Log::alert("PASO DOORTRIM");

                $doorTrim = MDoorTrim::where('nombre', $item->modelo)->first();
                $pedido = $this->obtenerDoorTrimPedido($item->modelo);

                if ($doorTrim && intval($pedido?->pedido) > 0) {
                    $this->stockService->insertaDetalle($doorTrim->codigo, (intval($pedido->pedido) * intval($doorTrim->lote)) * -1, $this->posicionElementos->id, null, WmsUnidades::DOOR_TRIM);
                }
            }
        }
    }
}
