<?php

namespace App\Services;

use App\Models\LectraEstado;
use App\Models\PlanCorte;
use DateTime;
use Illuminate\Support\Facades\Log;

class LectraService {

    private $lectras = [1, 2, 3, 4];

    private function getInicioLectra(): DateTime {
        //LOS TURNOS INICIAN 6:13 y 15:53

        $inicio = new DateTime(date('Y-m-d') . ' 06:13:00');
        return $inicio;
    }

    private function getPlanCorte(DateTime $fecha) {
        $planCorte = PlanCorte::where('fecha', '=', $fecha->format('d/m/Y'))->first();
        return $planCorte;
    }

    private function getDadosPlanificadosLectra(string $lectra) {
        $hoy = new DateTime();
        $planCorte = $this->getPlanCorte($hoy);

        $dadosPlanificados = LectraEstado::with('dado.material')
            ->where('lectra', $lectra)
            ->where(function ($q) use ($hoy, $planCorte) {
                $q->where(function ($qr) {
                    $qr->where('inicio', '!=', null);
                    $qr->where('fin', null);
                });
                $q->where(function ($qr) {
                    $qr->where('fin',  null);
                    $qr->where('inicio',  null);
                });
                $q->orWhere('inicio', '>=', $hoy->format('Y-m-d') . ' 05:00:00');

                if ($planCorte) {
                    $q->orWhere('operacion', $planCorte->operacion);
                }
            })
            ->orderByRaw('COALESCE(inicio, GETDATE()) ASC, id asc')
            ->get();

        return $dadosPlanificados;
    }

    private function getTiempoLectraSiNoExiste($lectraActual, $existe): array {

        try {
            $tiempos = $existe->{"t_lectra" . $lectraActual};
            // $tiempos = explode(":", $existe->{"t_lectra" . $lectraActual});

            if (is_null($tiempos) || $tiempos == '') {
                if ($lectraActual == 1) {
                    $tiempos = $existe->{"t_lectra2"};
                } else if ($lectraActual == 2) {
                    $tiempos = $existe->{"t_lectra1"};
                } else if ($lectraActual == 3) {
                    $tiempos = $existe->{"t_lectra4"};
                } else if ($lectraActual == 4) {
                    $tiempos = $existe->{"t_lectra3"};
                }

                if (is_null($tiempos) || $tiempos == '') {
                    $tiempos = "00:00:00";
                }
            }
        } catch (\Throwable $th) {
            $tiempos = "00:00:00";
        }

        return explode(":", $tiempos);
    }

    private function getModelo($dado) {
        if (is_null($dado->modelo)) {
            //CUANDO ES PARATE DE LECTRA
            return $dado->dado->dado;
        } else {
            return $dado->modelo;
        }
    }

    private function sumarTiempo(string $inicial, int $hours = 0, int $minutes = 0, int $seconds = 0, $format = 'Y-m-d H:i:s'): DateTime {

        if ($inicial) {
            $fecha = DateTime::createFromFormat($format, $inicial);
        } else {
            $fecha = new DateTime();
        }

        try {
            $fecha->modify("+" . $hours . " hours");
            $fecha->modify("+" . $minutes . " minutes");
            $fecha->modify("+" . $seconds . " seconds");
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("LectraController::sumarTiempo: " . $th->getMessage());
        }

        return $fecha;
    }

    public function estadoLectra() {

        $fechaPlanFinDado = null;
        $fechaRealFinDado = null;

        foreach ($this->lectras as $lectra) {
            $fechaInicioLectra = $this->getInicioLectra();
            //OBTENGO LOS DADOS PLANIFICADOS PARA EL DIA
            $dadosPlanificados = $this->getDadosPlanificadosLectra($lectra);

            foreach ($dadosPlanificados as $dado) {

                $duracionDado = $this->getTiempoLectraSiNoExiste($lectra, $dado->dado);
                $modelo = $this->getModelo($dado);
                $dadoActual = $dado->dado->dado;

                //SI EL DADO AÚN NO INICIO
                if (is_null($dado->inicio)) {
                    //PLAN -----------------------------------------------
                    //SI ES EL PRIMER DADO, TOMO LA FECHA DE LECTRA, SI NO, EL FIN DEL ANTERIOR PARA EL PLAN
                    $fechaPlanInicioDado = is_null($fechaPlanFinDado) ? $fechaInicioLectra : $fechaPlanFinDado;

                    //PARA EL PLAN, LA FECHA DE FIN ES EL INICIO + DURACION + 2 MINUTOS TENDIDO
                    $fechaPlanFinDado = $this->sumarTiempo($fechaPlanInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);

                    //REAL -----------------------------------------------
                    $fechaRealInicioDado = is_null($fechaRealFinDado) ? $fechaPlanFinDado : $fechaRealFinDado;
                    $fechaRealFinDado = $this->sumarTiempo($fechaRealInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);
                } else {
                    //SI EL DADO YA ESTÁ EN CURSO
                    if (!is_null($fechaPlanFinDado)) {
                        /**MI INICIO DE PLAN, ES EL FIN DEL PLAN ANTERIOR */
                        $fechaPlanInicioDado = $fechaPlanFinDado;
                    } else {
                        if (!is_null($fechaInicioLectra)) {
                            $fechaPlanInicioDado = $fechaInicioLectra;
                        } else {
                            /**SI ES EL PRIMER DADO, TOMO EL INICIO REAL */
                            $fechaPlanInicioDado = new DateTime($dado->inicio); //, new \DateTimeZone('America/Argentina/Buenos_Aires'));
                        }
                    }

                    $lFechaInicio = new DateTime($dado->inicio);

                    if ($lFechaInicio->format('d') < date('d')) {
                        $fechaRealInicioDado = new DateTime(date('Y-m-d') . '06:13:00'); //, new \DateTimeZone('America/Argentina/Buenos_Aires'));                                
                    } else {
                        $fechaRealInicioDado = new DateTime($dado->inicio);
                    }

                    if (is_null($fechaInicioLectra)) {
                        /**REGISTRO COMO INICIO DE LECTRA, EL PRIMER CORTE CON INICIO */
                        $fechaInicioLectra = $fechaPlanInicioDado;
                    }

                    /**TOMO COMO PLAN, LA FECHA DE INICIO + EL TIEMPO DEL DADO + 2 MINUTOS */
                    $fechaPlanFinDado = $this->sumarTiempo($fechaPlanInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);

                    if (is_null($dado->fin)) {
                        /**SI EL DADO NO TERMINO AÚN, TOMO COMO REAL, EL INICIO + LA DURACIÓN */
                        $fechaRealActual = new DateTime();
                        $fechaRealFinDado = $this->sumarTiempo($fechaRealInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);

                        if ($fechaRealFinDado < $fechaRealActual) {
                            $fechaRealFinDado = $this->sumarTiempo($fechaRealActual->format('Y-m-d H:i:s'), 0, 5, 0);
                        }
                    } else {
                        /**SI EL DADO YA TERMINO, TOMO COMO REAL LA FECHA REGISTRADA */
                        $fechaRealFinDado = new DateTime($dado->fin);

                        /**REGISTRO LA DURACIÓN REAL DEL DADO */
                        $interval = $fechaRealFinDado->diff($fechaRealInicioDado);
                        $duracionRealDado = str_pad($interval->format('%H'),  2, "0", STR_PAD_LEFT) . ':' . str_pad($interval->format('%i'), 2, "0", STR_PAD_LEFT);
                    }
                }
            }
        }
    }
}
