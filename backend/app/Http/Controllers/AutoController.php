<?php

namespace App\Http\Controllers;

use App\Services\UtilidadesService;
use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutoController extends Controller {


    public function creaStockKanban() {
        $utService = new UtilidadesService();

        $fechaInicio = new DateTime();
        // $fechaFin = new DateTime(); 
        // $fechaInicio->modify('-35 day'); 
        // $fechaFin->modify('+1 day'); 

        // $intervalo = new DateInterval('P1D');
        // $periodo = new DatePeriod($fechaInicio, $intervalo, $fechaFin);

        // foreach ($periodo as $fecha) {
        //     $utService->generaStockKanbanPorFila($fecha->format('Y-m-d'));
        // }

        $utService->generaStockKanbanPorFila($fechaInicio->format('Y-m-d'));

        return $this->setResponse([]);
    }

    public function generaVolumenDespachosPBI(UtilidadesService $utService) {

        $fechaInicio = new DateTime();
        $fechaFin = new DateTime();
        $fechaInicio->modify('-1 day');
        $fechaFin->modify('+1 day');

        $intervalo = new DateInterval('P1D');
        $periodo = new DatePeriod($fechaInicio, $intervalo, $fechaFin);

        foreach ($periodo as $fecha) {
            $utService->generaVolumenProduccionDespachoPBI($fecha->format('Y-m-d'));
        }

        return $this->setResponse([]);
    }

    public function generaDespachoPedidoEnBaseaItems(UtilidadesService $utService) {

        $despachoId = 30374;
        $utService->generaDespachoPedidoEnBaseaItems($despachoId);

        return $this->setResponse([]);
    }

    public function generaTemporalHoraHoraEficienciaProduccion(UtilidadesService $utService) {
        $utService->generaTemporalEficienciaProduccionHoraHora();

        return $this->setResponse([]);
    }
}
