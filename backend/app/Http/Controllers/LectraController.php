<?php

namespace App\Http\Controllers;

use App\Http\Estados;
use App\Http\FallasLectra;
use App\Http\Kanban;
use App\Models\DespachosItems;
use App\Models\DadosPieza;
use App\Models\EstadoKanban;
use App\Models\InicioLectra;
use App\Models\Kanbans;
use App\Models\KanbansReemplazo;
use App\Models\LectraEstado;
use App\Models\LogAbastecimiento;
use App\Models\LogEstadosKanbans;
use App\Models\LogPlanCostura;
use App\Models\MaterialesPiezas;
use App\Models\ModeloDado;
use App\Models\ModeloKanbanPadre;
use App\Models\Modelos;
use App\Models\ModelosCompartidos;
use App\Models\PcPlanProduccion;
use App\Models\PlanCorte;
use App\Models\Sar\TKanban;
use App\Models\Sar\TKanbanMaterial;
use App\Models\TmpLectra;
use DateInterval;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LectraController extends Controller {

    public function actualizarTiemposCorte(Request $request) {

        $items = $request->items;

        foreach ($items as $item) {
            // Log::alert($item);
            LectraEstado::where('id', $item['id'])
                ->update([
                    'inicio'    => $item['inicio'],
                    'fin'       => $item['fin'],
                    'demora'    => intval($item['demora']),
                ]);
        }

        return $this->setResponse([]);
    }

    private function stringAMinutos($tiempo) {
        list($horas, $minutos, $segundos) = explode(':', $tiempo);
        return ($horas * 60) + $minutos + intval($segundos / 60);
    }

    public function testDadosTiempo() {
        $horaBase = Carbon::createFromTimeString('06:13');

        $planificacion = [];
        $real = [];
        $lectra = 1;

        $hoy = new DateTime();
        $planCorte = PlanCorte::where('fecha', '=', $hoy->format('d/m/Y'))->first();

        $dados = LectraEstado::with('dado.material')
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
            ->get()->toArray();


        foreach ($dados as $index => $dado) {
            // PLANIFICACIÓN
            $inicioEstimado = $index === 0
                ? $horaBase->copy()
                : $planificacion[$index - 1]['fin_estimado_ins'];

            $finEstimado = $inicioEstimado->copy()->addMinutes($this->stringAMinutos($dado['dado']['t_lectra1']));

            $planificacion[] = [
                'orden'             => $dado['id'],
                'inicio_estimado'   => $inicioEstimado->format('H:i'),
                'fin_estimado'      => $finEstimado->format('H:i:s'),
                'fin_estimado_ins'  => $finEstimado,
                'dado'              => $dado['dado']['dado']
            ];

            // REAL
            $inicioReal = null;
            $finReal = null;
            $atrasado = false;
            $minutosAtraso = 0;

            if (!empty($dado['inicio'])) {
                $inicioReal = Carbon::parse($dado['inicio']);

                if (!empty($dado['fin'])) {
                    $finReal = Carbon::parse($dado['fin']);
                } else {
                    $finReal = $inicioReal->copy()->addMinutes($this->stringAMinutos($dado['dado']['t_lectra1']));
                }

                // Comparamos fin estimado vs real
                if ($inicioReal->greaterThan($inicioEstimado)) {
                    $atrasado = true;
                    $minutosAtraso = (int) $inicioEstimado->diffInMinutes($inicioReal, true);
                }
            } else {
                // Si no hay tiempo real, estimamos a partir del anterior real o base
                $inicioReal = $index === 0
                    ? Carbon::createFromDate(date('Y'), date('m'), date('d')) // $horaBase->copy()
                    : $real[$index - 1]['fin_real_ins'];

                if ($inicioReal->greaterThan($inicioEstimado)) {
                    $atrasado = true;
                    $minutosAtraso = (int) $inicioEstimado->diffInMinutes($inicioReal, true);
                }

                if ($atrasado && $index > 0) {
                    $inicioReal = $inicioReal->copy()->addMinutes($minutosAtraso);
                }

                $finReal = $inicioReal->copy()->addMinutes($this->stringAMinutos($dado['dado']['t_lectra1']));
            }

            $real[] = [
                'orden'             => $dado['id'],
                'inicio_real'       => $inicioReal->format('H:i'),
                'fin_real'          => $finReal->format('H:i'),
                'fin_real_ins'      => $finReal,
                'atrasado'          => $atrasado,
                'minutos_atraso'    => $minutosAtraso,
                'dado'              => $dado['dado']['dado']
            ];
        }

        return [
            'planificacion' => $planificacion,
            'real' => $real,
        ];
    }

    public function actualizarEstadoDado(Request $request) {
        $id = $request->id;
        $campo = $request->campo;
        $valor = $request->valor;

        $existente = LectraEstado::where('id', $id)->first();

        if (!$existente) {
            return $this->setResponse([], 'No existe');
        }

        if ($campo === 'es_reposicion') {
            $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $valor = is_null($valor) ? false : $valor;
        }

        $data = [
            $campo => $valor
        ];

        LectraEstado::where('id', $id)->update($data);

        return $this->setResponse([]);
    }

    public function quitarModeloPendiente(Request $request) {

        //Elimina un modelo pendiente de planificacion
        $modelo = $request->modelo;
        //Busco los kanban de ese modelo con estado pendiente de planificacion
        $kanbans = Kanbans::with(['estado.estado', 'modelo.lineas'])
            ->whereHas('modelo', function ($q) use ($modelo) {
                $q->where('nombre', $modelo);
            })
            ->whereHas('estado', function ($query) {
                $query->where('estado_id', Estados::EN_PLANIFICACION);
            })
            ->get();

        foreach ($kanbans as $k) {
            if ($k->estado->estado_id == Estados::EN_PLANIFICACION) {
                Kanbans::where('id', $k->id)->update(['operacion' => null]);
                Kanban::changeStatus($k, Estados::RECHAZADO, null);
            }
        }

        return $this->setResponse([]);
    }

    private function verificaRangoParate(DateTime $inicio, DateTime $fin, $finReal = null) {

        $nuevoFin = $fin;
        $fechaHoraActual = new DateTime();
        $fechaSiguiente = new DateTime();
        $fechaSiguiente->add(new DateInterval('P1D'));

        $fechas = [
            'i1' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') . ' 15:10'), // 12/09/2024 15:10
            'f1' => DateTime::createFromFormat('d/m/Y H:i',  $fechaHoraActual->format('d/m/Y') . ' 15:40'), // 12/09/2024 15:40
            'i2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 00:50'), // 13/09/2024 00:50
            'f2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 05:59'), // 13/09/2024 05:59
            'i3' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') .  ' 00:50'), // 12/09/2024 00:50
        ];

        //INICIO 12/09/2024 06:44:06
        //FIN 12/09/2024 06:44:08

        //ESTA EN EL PARATE DE LA NOCHE?
        if (($inicio < $fechas['i1'] && $fin > $fechas['f1'] || (($inicio < $fechas['i1'] && $fechaHoraActual > $fechas['i1']) && is_null($finReal)))) {
            //12/09/2024 06:44:06 < 12/09/2024 15:10 && 12/09/2024 06:44:08 > 12/09/2024 15:40
            //O
            //12/09/2024 06:44:06 < 12/09/2024 15:10 && 12/09/2024 21:09:08 > 12/09/2024 15:40
            $nuevoFin = $this->sumarTiempo($fin->format('Y-m-d H:i:s'), 0, 30, 0);
        }

        if (($inicio < $fechas['i3'] && $fin < $fechas['i2']) ||  ($inicio < $fechas['i3'] && $fin > $fechas['f2']) || ($inicio < $fechas['i3'] && ($fechaHoraActual > $fechas['i3']) && is_null($finReal))) {
            $nuevoFin = $this->sumarTiempo($fin->format('Y-m-d H:i:s'), 0, 310, 0);
        }

        return $nuevoFin;
    }

    /**
     * Verifica si la fecha hora esta en el rango de algun parate, si es asi, suma el tiempo de parate
     */
    private function sumaHorarioParate(Datetime $fecha) {
        // Log::alert("HORA EN SUMA : " . $fecha->format('d/m/Y H:i:s'));
        if (intval($fecha->format('H')) > 0 && intval($fecha->format('H')) < 6) {
            //SI EL CORTE TERMINA EN CAMBIO DE TURNO NOCTURNO, LE SUMO LAS HORAS DE PARATE
            // Log::alert("SUMO PARATE 1");
            $fecha = $this->sumarTiempo($fecha->format('Y-m-d H:i:s'), 0, 310, 0);
        } else if (intval($fecha->format('H')) == 15 && (intval($fecha->format('i')) > 10 && intval($fecha->format('i')) < 40)) {
            //SI EL CORTE TERMINA EN CAMBIO DE TURNO, LE SUMO LOS MINUTOS DE PARATE                    
            // Log::alert("SUMO PARATE 2");
            $fecha = $this->sumarTiempo($fecha->format('Y-m-d H:i:s'), 0, 30, 0);
        }
        // Log::alert("HORA SUMADA : " . $fecha->format('d/m/Y H:i:s'));
        // Log::alert("================");
        return $fecha;
    }

    /**
     * Esta función crea en una tabla temporal el plan de la lectra. Se ejecuta cada vez que se actualiza el plan de corte
     */
    public function armaTemporalPlan() {
        $lectras = [1, 2, 3, 4];
        $planificacion = [];
        $ayer = new DateTime();
        $hoy = new DateTime();

        $ayer->sub(new DateInterval('P1D'));
        $fechaInicioLectra = null;
        $modelo = "";
        $fechaPlanFinDado = null;
        // TmpLectra::where('id', '>', 0)->delete();


        try {
            $planCorte = PlanCorte::where('fecha', '=', $hoy->format('d/m/Y'))->first();

            foreach ($lectras as $lectra) {
                $fechaPlanFinDado = null;

                //LOS TURNOS ARRANCAN 6:13 y 15:53
                $fechaInicioLectra = new DateTime(date('Y-m-d') . '06:13:00');

                $dadosLectra = LectraEstado::with('dado.material')
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

                foreach ($dadosLectra as $dado) {

                    $duracionDado = $this->getTiempoLectraSiNoExiste($lectra, $dado->dado);

                    $modelo = $dado->modelo;
                    $dadoActual = $dado->dado->dado;

                    if (is_null($modelo)) {
                        /**CUANDO ES UN ESTADO DE PARATE DE LA LECTRA */
                        $modelo = $dado->dado->dado;
                    }

                    if (!is_null($dado->inicio)) {
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

                        if (is_null($fechaInicioLectra)) {
                            /**REGISTRO COMO INICIO DE LECTRA, EL PRIMER CORTE CON INICIO */
                            $fechaInicioLectra = $fechaPlanInicioDado;
                        }

                        /**TOMO COMO PLAN, LA FECHA DE INICIO + EL TIEMPO DEL DADO + 2 MINUTOS */
                        $fechaPlanFinDado = $this->sumarTiempo($fechaPlanInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);
                    } else {
                        if (is_null($fechaInicioLectra)) {
                            $fechaInicioLectra = new DateTime();
                        }

                        /**SI EL DADO AUN NO INICIO */
                        if (is_null($fechaPlanFinDado)) {
                            $fechaPlanInicioDado = $fechaInicioLectra;
                        } else {
                            $fechaPlanInicioDado = $fechaPlanFinDado;
                        }

                        $fechaPlanFinDado = $this->sumarTiempo($fechaPlanInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);
                    }

                    /**DE TODAS LAS FECHAS, VERIFICO SI ESTAN EN PARATE */
                    $fechaPlanFinDado = $this->sumaHorarioParate($fechaPlanFinDado);
                    $fechaPlanInicioDado = $this->sumaHorarioParate($fechaPlanInicioDado);

                    $tmpDado = [
                        'dado'                  => $dadoActual,
                        'lectra'                => $lectra,
                        'group'                 => 'P-' . $lectra,
                        'modelo'                => $modelo,
                        'inicio'                => $fechaPlanInicioDado->format('Y-m-d H:i:s'),
                        'horaInicio'            => $fechaPlanInicioDado->format('H:i'),
                        'horaFin'               => $fechaPlanFinDado->format('H:i'),
                        'fin'                   => $fechaPlanFinDado->format('Y-m-d H:i:s'),
                        'demora'                => 0,
                    ];

                    //AGREGO A PLAN    
                    // TmpLectra::create($tmpDado);
                    array_push($planificacion, $tmpDado);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("LectraController::getEstadoLectras4 : " . $th->getMessage());
        }

        return $this->setResponse([
            'planificacion' => $planificacion,
        ]);
    }

    public function getEstadoLectrasReal() {
        $lectras = [1, 2, 3, 4];
        $planificacion = [];
        $ayer = new DateTime();
        $hoy = new DateTime();
        $ayer->sub(new DateInterval('P1D'));
        $fechaInicioLectra = null;
        $modelo = "";
        $fechaPlanFinDado = null;
        $fechaRealFinDado = null;
        $orden = 0;
        $demoraDado = 0;
        $demoraTT = 0;
        $demoraTM = 0;
        $duracionRealDado = '00:00';
        $primerDadoModelo = false;
        $anteriorAbastecido = false;
        $esPrimerCiclo = true;
        $inicioLectraProgramado = false;

        try {
            $planCorte = PlanCorte::where('fecha', '=', $hoy->format('d/m/Y'))->first();

            foreach ($lectras as $lectra) {
                $fechaPlanFinDado = null;
                $primerDadoModelo = false;
                $anteriorAbastecido = false;
                $fechaRealFinDado = null;
                $eraNuloElInicio = false;
                $esPrimerCiclo = true;
                $esPrimerDadoLectra = true;

                //LOS TURNOS ARRANCAN 6:13 y 15:53

                $fechaInicioLectra = new DateTime(date('Y-m-d') . '06:13:00');

                $dadosLectra = LectraEstado::with('dado.material')
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

                $dadosReposicion = DadosPieza::with([
                    'material',
                    'modelo',
                    'pieza.modelo',
                    'kanbanReemplazo.pieza',
                    'kanbanReemplazo.kanban'
                ])
                    ->whereIn(
                        'pieza_id',
                        $dadosLectra->where('es_reposicion', true)
                            ->pluck('pieza_id')
                            ->filter()
                            ->unique()
                            ->values()
                    )
                    ->get()
                    ->keyBy('pieza_id');

                foreach ($dadosLectra as $dado) {
                    $infoDado = $dado->es_reposicion
                        ? $dadosReposicion->get($dado->pieza_id)
                        : $dado->dado;

                    $demoraTT = 0;
                    $demoraTM = 0;

                    if ($modelo != "" && $modelo != $dado->modelo) {
                        $orden = $orden + 1;

                        if (is_null($dado->inicio) || $dado->inicio == '') {
                            if (!$dado->abastecido || is_null($dado->abastecido)) {
                                $primerDadoModelo = true;
                                $anteriorAbastecido = false;
                            } else {
                                $anteriorAbastecido = true;
                                $primerDadoModelo = false;
                            }
                        } else {
                            $primerDadoModelo = false;
                            $anteriorAbastecido = true;
                        }
                    } else {
                        if (is_null($dado->inicio) || $dado->inicio == '') {

                            if (!$dado->abastecido || is_null($dado->abastecido)) {

                                if (!$anteriorAbastecido) {
                                    if ($modelo == '' || is_null($modelo)) {
                                        $primerDadoModelo = true;
                                    } else {
                                        $primerDadoModelo = false;
                                    }
                                } else {
                                    $anteriorAbastecido = false;
                                    $primerDadoModelo = true;
                                }
                            } else {
                                $primerDadoModelo = false;
                            }
                        } else {
                            $primerDadoModelo = false;
                        }
                    }

                    $duracionDado = $this->getTiempoLectraSiNoExiste($lectra, $dado->dado);

                    $modelo = $dado->modelo;
                    $dadoActual = $dado->dado->dado;

                    if (is_null($modelo)) {
                        /**CUANDO ES UN ESTADO DE PARATE DE LA LECTRA */
                        $modelo = $dado->dado->dado;
                    }

                    if (!is_null($dado->inicio)) {
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
                    } else {
                        if (is_null($fechaInicioLectra)) {
                            $fechaInicioLectra = new DateTime();
                            $eraNuloElInicio = true;
                        }

                        /**SI EL DADO AUN NO INICIO */
                        if (is_null($fechaPlanFinDado)) {
                            $fechaPlanInicioDado = $fechaInicioLectra;
                        } else {
                            $fechaPlanInicioDado = $fechaPlanFinDado;
                        }

                        $fechaPlanFinDado = $this->sumarTiempo($fechaPlanInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);

                        if (!is_null($fechaRealFinDado)) {
                            $fechaRealInicioDado = $fechaRealFinDado;
                        } else {
                            $fechaRealInicioDado = $fechaPlanFinDado;
                        }

                        $fechaActual = new DateTime();

                        if ($fechaRealInicioDado < $fechaActual) {
                            $fechaRealInicioDado = $fechaActual;
                        }

                        $fechaRealFinDado = $this->sumarTiempo($fechaRealInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);

                        if ($inicioLectraProgramado && $esPrimerCiclo) {
                            $fechaRealInicioDado = $fechaInicioLectra;
                            $fechaRealFinDado = $fechaPlanFinDado;
                            $esPrimerCiclo = false;
                            $eraNuloElInicio = false;
                        } else {
                            if ($eraNuloElInicio) {
                                $fechaRealFinDado = $fechaPlanFinDado;
                                $fechaRealInicioDado = $fechaPlanInicioDado;
                                $eraNuloElInicio = false;
                            }
                        }
                    }

                    /**VERIFICO SI EL DADO DEMORO MÁS DE LO QUE DEBÍA */
                    /**AL INICIO REAL, LE SUMO EL TIEMPO DE DEMORA DEL DADO + 2 */
                    $finEsperadoDado = $this->sumarTiempo($fechaRealInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);

                    if ($esPrimerDadoLectra) {
                        $esPrimerDadoLectra = false;
                        //CALCULO EL TIEMPO ENTRE EL INICIO PLANEADO Y EL INICIO REAL PARA SUMARLO AL GAP DEL DADO
                        if ($fechaRealInicioDado > $fechaPlanInicioDado) {
                            $intervaloGap = $fechaPlanInicioDado->diff($fechaRealInicioDado);
                            $horasGap = $intervaloGap->format('%H');
                            $minutosGap = $intervaloGap->format('%i');
                            $diasGap = $intervaloGap->format('%d');
                            $horasGap = (intval($horasGap) + ($diasGap * 24)) * 60;
                            $demoraDado = $demoraDado + intval($minutosGap) + $horasGap; //EN MINUTOS
                            $demoraTM = $demoraTM + intval($minutosGap) + $horasGap;
                        }
                    }

                    if ($finEsperadoDado < $fechaRealFinDado) {
                        //CALCULO GAP DEL DADO EN CUANTO AL FIN ESPERADO Y EL FIN REAL
                        $intervaloGap = $finEsperadoDado->diff($fechaRealFinDado);
                        $horasGap = $intervaloGap->format('%H');
                        $minutosGap = $intervaloGap->format('%i');
                        $diasGap = $intervaloGap->format('%d');

                        //SI ES SABADO O DOMINGO LO OMITO
                        //TODO CORREGIR ESTO
                        if ($diasGap > 2) {
                            $diasGap = 1;
                        }

                        $horasGap = (intval($horasGap) + ($diasGap * 24)) * 60;
                        $demoraDado = $demoraDado + intval($minutosGap) + $horasGap; //EN MINUTOS

                        //VERIFICO SI LA DEMORA CORRESPONDE A UN TURNO O A OTRO
                        //TURNO MAÑANA
                        $finTM = new DateTime(date('Y-m-d') . ' 06:13:00');
                        if ($finEsperadoDado <= $finTM) {
                            $demoraTT = $demoraTT + intval($minutosGap) + $horasGap;
                        } else {
                            $demoraTM = $demoraTM + intval($minutosGap) + $horasGap;
                        }
                    }

                    /**DE TODAS LAS FECHAS, VERIFICO SI ESTAN EN PARATE */
                    $fechaPlanFinDado = $this->sumaHorarioParate($fechaPlanFinDado);
                    $fechaRealFinDado = $this->sumaHorarioParate($fechaRealFinDado);
                    $fechaPlanInicioDado = $this->sumaHorarioParate($fechaPlanInicioDado);
                    $fechaRealInicioDado = $this->sumaHorarioParate($fechaRealInicioDado);

                    //Obtengo el dado
                    $materialAUtilizar = null;
                    $dadoTemporal = ModeloKanbanPadre::where('dado', $dadoActual)->get();

                    if (count($dadoTemporal) == 1) {
                        $dadoAUtilizar = $dadoTemporal[0];
                        $materialAUtilizar = MaterialesPiezas::where('id', $dadoAUtilizar->material_id)->first();
                    } else if (count($dadoTemporal) > 1) {
                        if (strlen($modelo) > 6) {
                            //COMPARTIDO
                            $compartido = ModelosCompartidos::where('name', $modelo)->first();
                            if ($compartido) {
                                $dadoAUtilizar = ModeloKanbanPadre::where('dado', $dadoActual)->where('compartido_id', $compartido->id)->first();
                                $materialAUtilizar = MaterialesPiezas::where('id', $dadoAUtilizar->material_id)->first();
                            }
                        } else {
                            $modTemp = Modelos::where('nombre', $modelo)->first();
                            if ($modTemp) {
                                $dadoAUtilizar = ModeloKanbanPadre::where('dado', $dadoActual)->where('modelo_id', $modTemp->id)->first();
                                $materialAUtilizar = MaterialesPiezas::where('id', $dadoAUtilizar->material_id)->first();
                            }
                        }
                    }

                    //AGREGO A REAL
                    array_push($planificacion, [
                        'modelo'                => $modelo,
                        'inicio'                => $fechaRealInicioDado->format('Y-m-d H:i:s'),
                        'horaInicio'            => $fechaRealInicioDado->format('H:i'),
                        'horaFin'               => $fechaRealFinDado->format('H:i'),
                        'fin'                   => $fechaRealFinDado->format('Y-m-d H:i:s'),
                        'hora_fin_plan'         => $fechaPlanFinDado->format('H:i'),
                        'lectra'                => $lectra,
                        'primerDadoModelo'      => $primerDadoModelo,
                        'demora'                => $demoraDado,
                        'duracion'              => $duracionRealDado,
                        'duracion_real'         => $duracionDado[0] . ':' . $duracionDado[1],
                        'fecha_abastecido'      => $dado->fecha_abastecido,
                        'group'                 => 'R-' . $lectra,
                        'dado'                  => $dadoActual,
                        'operacion'             => $dado->operacion,
                        'material'              => $materialAUtilizar, //$dado->dado->material,
                        'fin_real_dado'         => $dado->fin, //SI EL DADO REALMENTE TERMINO
                        'inicio_real_dado'      => $dado->inicio, //SI EL DADO REALMENTE INICIO
                        'fin_estimado'          => $dado->fin_estimado,
                        'abastecido'            => $dado->abastecido,
                        'demora_total_acum'     => $this->diferenciaFechas($fechaPlanFinDado, $fechaRealFinDado),
                        'orden'                 => $orden,
                        'id_lectra_estado'      => $dado->id,
                        'data_dado'             => $dadoAUtilizar, //$dado->dado,
                        'demoraTM'              => $demoraTM,
                        'demoraTT'              => $demoraTT,
                    ]);

                    $demoraDado = 0;
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("LectraController::getEstadoLectras4 : " . $th->getMessage());
        }

        $planDados = $planificacion;

        // $plan = array_filter($planDados, function ($k, $v) use ($lectra) {
        //     return $k['lectra'] == 1; //&& $k['group'] == 'P-3';
        // }, ARRAY_FILTER_USE_BOTH);

        // Log::alert($planificacion);

        $planificacion = $this->normalizaPlanificacion($planificacion);
        $planificacion = $this->agregaParateTimeLine($planificacion);
        $planLectra = $this->getPlanLectraModeloGap($planDados);

        return $this->setResponse([
            'planificacion' => $planificacion,
            'dados'         => $planDados,
            'planLectra'    => $planLectra
        ]);
    }

    private function getVentanaTurnoAbastecimiento(?DateTime $now = null): array {
        $now = $now ?: new DateTime();
        $today = (clone $now)->format('Y-m-d');

        $h0530 = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' 05:30:00');
        $h1525 = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' 15:25:00');

        if ($now >= $h0530 && $now < $h1525) {
            return [
                'turno' => 'MANANA',
                'inicio' => $h0530,
                'fin' => $h1525
            ];
        }

        if ($now >= $h1525) {
            $fin = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' 01:00:00');
            $fin->add(new DateInterval('P1D'));
            return [
                'turno' => 'TARDE_NOCHE',
                'inicio' => $h1525,
                'fin' => $fin
            ];
        }

        // Entre 00:00 y 05:29 conservamos la ventana del turno tarde/noche anterior.
        $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' 15:25:00');
        $inicio->sub(new DateInterval('P1D'));
        $fin = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' 01:00:00');

        return [
            'turno' => 'TARDE_NOCHE',
            'inicio' => $inicio,
            'fin' => $fin
        ];
    }

    public function getEstadoLectras4(Request $request) {
        $lectras = [1, 2, 3, 4];
        $planificacion = [];
        $ayer = new DateTime();
        $hoy = new DateTime();
        $ayer->sub(new DateInterval('P1D'));
        $fechaInicioLectra = null;
        $modelo = "";
        $fechaPlanFinDado = null;
        $fechaRealFinDado = null;
        $orden = 0;
        $demoraDado = 0;
        $demoraTT = 0;
        $demoraTM = 0;
        $duracionRealDado = '00:00';
        $primerDadoModelo = false;
        $anteriorAbastecido = false;
        $esPrimerCiclo = true;
        $inicioLectraProgramado = false;
        $scope = strtolower(trim((string) $request->query('scope', '')));
        $esScopeAbastecimiento = $scope === 'abastecimiento';
        $ventanaTurno = $esScopeAbastecimiento ? $this->getVentanaTurnoAbastecimiento($hoy) : null;

        try {
            $planCorte = PlanCorte::where('fecha', '=', $hoy->format('d/m/Y'))->first();

            foreach ($lectras as $lectra) {
                $fechaPlanFinDado = null;
                $primerDadoModelo = false;
                $anteriorAbastecido = false;
                $fechaRealFinDado = null;
                $eraNuloElInicio = false;
                $esPrimerCiclo = true;
                $esPrimerDadoLectra = true;

                //LOS TURNOS ARRANCAN 6:13 y 15:53

                $dataInicio = InicioLectra::where('lectra', $lectra)->where('fecha', date('Y-m-d'))->first();

                if ($dataInicio) {
                    $fechaInicioLectra = new DateTime(date('Y-m-d') . $dataInicio->hora . ':00');
                    $inicioLectraProgramado = true;
                } else {
                    $fechaInicioLectra = null;
                    $fechaInicioLectra = new DateTime(date('Y-m-d') . '06:13:00');
                }

                if ($esScopeAbastecimiento && !is_null($ventanaTurno)) {
                    $inicioVentana = $ventanaTurno['inicio']->format('Y-m-d H:i:s');
                    $finVentana = $ventanaTurno['fin']->format('Y-m-d H:i:s');

                    $dadosLectra = LectraEstado::with('dado.material')
                        ->where('lectra', $lectra)
                        ->where(function ($q) use ($inicioVentana, $finVentana) {
                            $q->whereBetween('created_at', [$inicioVentana, $finVentana]);
                            $q->orWhereBetween('inicio', [$inicioVentana, $finVentana]);
                            $q->orWhereBetween('fin', [$inicioVentana, $finVentana]);
                            $q->orWhere(function ($overlap) use ($inicioVentana, $finVentana) {
                                $overlap->where('created_at', '<=', $finVentana)
                                    ->where(function ($x) use ($inicioVentana) {
                                        $x->whereNull('fin')
                                            ->orWhere('fin', '>=', $inicioVentana);
                                    });
                            });
                        })
                        ->orderByRaw('COALESCE(inicio, GETDATE()) ASC, id asc')
                        ->get();
                } else {
                    $dadosLectra = LectraEstado::with('dado.material')
                        ->where('lectra', $lectra)
                        // ->where('dado',null)
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
                }

                $dadosReposicion = DadosPieza::with([
                    'material',
                    'modelo',
                    'pieza.modelo',
                    'kanbanReemplazo.pieza',
                    'kanbanReemplazo.kanban'
                ])
                    ->whereIn(
                        'pieza_id',
                        $dadosLectra->where('es_reposicion', true)
                            ->pluck('pieza_id')
                            ->filter()
                            ->unique()
                            ->values()
                    )
                    ->get()
                    ->keyBy('pieza_id');

                foreach ($dadosLectra as $dado) {
                    $infoDado = $dado->es_reposicion
                        ? $dadosReposicion->get($dado->pieza_id)
                        : $dado->dado;

                    $demoraTT = 0;
                    $demoraTM = 0;

                    if ($modelo != "" && $modelo != $dado->modelo) {
                        $orden = $orden + 1;

                        if (is_null($dado->inicio) || $dado->inicio == '') {
                            if (!$dado->abastecido || is_null($dado->abastecido)) {
                                $primerDadoModelo = true;
                                $anteriorAbastecido = false;
                            } else {
                                $anteriorAbastecido = true;
                                $primerDadoModelo = false;
                            }
                        } else {
                            $primerDadoModelo = false;
                            $anteriorAbastecido = true;
                        }
                    } else {
                        if (is_null($dado->inicio) || $dado->inicio == '') {

                            if (!$dado->abastecido || is_null($dado->abastecido)) {

                                if (!$anteriorAbastecido) {
                                    if ($modelo == '' || is_null($modelo)) {
                                        $primerDadoModelo = true;
                                    } else {
                                        $primerDadoModelo = false;
                                    }
                                } else {
                                    $anteriorAbastecido = false;
                                    $primerDadoModelo = true;
                                }
                            } else {
                                $primerDadoModelo = false;
                            }
                        } else {
                            $primerDadoModelo = false;
                        }
                    }

                    $duracionDado = $this->getTiempoLectraSiNoExiste($lectra, $infoDado);

                    $modelo = $dado->modelo;
                    $dadoActual = $infoDado?->dado;

                    if (is_null($modelo)) {
                        /**CUANDO ES UN ESTADO DE PARATE DE LA LECTRA */
                        $modelo = $infoDado?->dado;
                    }

                    if (!is_null($dado->inicio)) {
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
                    } else {
                        if (is_null($fechaInicioLectra)) {
                            $fechaInicioLectra = new DateTime();
                            $eraNuloElInicio = true;
                        }

                        /**SI EL DADO AUN NO INICIO */
                        if (is_null($fechaPlanFinDado)) {
                            $fechaPlanInicioDado = $fechaInicioLectra;
                        } else {
                            $fechaPlanInicioDado = $fechaPlanFinDado;
                        }

                        $fechaPlanFinDado = $this->sumarTiempo($fechaPlanInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);

                        if (!is_null($fechaRealFinDado)) {
                            $fechaRealInicioDado = $fechaRealFinDado;
                        } else {
                            $fechaRealInicioDado = $fechaPlanFinDado;
                        }

                        $fechaActual = new DateTime();

                        if ($fechaRealInicioDado < $fechaActual) {
                            $fechaRealInicioDado = $fechaActual;
                        }

                        $fechaRealFinDado = $this->sumarTiempo($fechaRealInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);

                        if ($inicioLectraProgramado && $esPrimerCiclo) {
                            // $fechaRealInicioDado = $fechaInicioLectra;
                            // $fechaRealFinDado = $fechaPlanFinDado;
                            $esPrimerCiclo = false;
                            $eraNuloElInicio = false;
                        } else {
                            if ($eraNuloElInicio) {
                                $fechaRealFinDado = $fechaPlanFinDado;
                                $fechaRealInicioDado = $fechaPlanInicioDado;
                                $eraNuloElInicio = false;
                            }
                        }
                    }

                    /**VERIFICO SI EL DADO DEMORO MÁS DE LO QUE DEBÍA */
                    /**AL INICIO REAL, LE SUMO EL TIEMPO DE DEMORA DEL DADO + 2 */
                    $finEsperadoDado = $this->sumarTiempo($fechaRealInicioDado->format('Y-m-d H:i:s'), $duracionDado[0], intval($duracionDado[1]) + 2, $duracionDado[2]);

                    if ($esPrimerDadoLectra) {
                        $esPrimerDadoLectra = false;
                        //CALCULO EL TIEMPO ENTRE EL INICIO PLANEADO Y EL INICIO REAL PARA SUMARLO AL GAP DEL DADO
                        if ($fechaRealInicioDado > $fechaPlanInicioDado) {
                            $intervaloGap = $fechaPlanInicioDado->diff($fechaRealInicioDado);
                            $horasGap = $intervaloGap->format('%H');
                            $minutosGap = $intervaloGap->format('%i');
                            $diasGap = $intervaloGap->format('%d');
                            $horasGap = (intval($horasGap) + ($diasGap * 24)) * 60;
                            $demoraDado = $demoraDado + intval($minutosGap) + $horasGap; //EN MINUTOS
                            $demoraTM = $demoraTM + intval($minutosGap) + $horasGap;
                        }
                    }

                    if ($finEsperadoDado < $fechaRealFinDado) {
                        //CALCULO GAP DEL DADO EN CUANTO AL FIN ESPERADO Y EL FIN REAL
                        $intervaloGap = $finEsperadoDado->diff($fechaRealFinDado);
                        $horasGap = $intervaloGap->format('%H');
                        $minutosGap = $intervaloGap->format('%i');
                        $diasGap = $intervaloGap->format('%d');

                        //SI ES SABADO O DOMINGO LO OMITO
                        //TODO CORREGIR ESTO
                        if ($diasGap > 2) {
                            $diasGap = 1;
                        }

                        $horasGap = (intval($horasGap) + ($diasGap * 24)) * 60;
                        $demoraDado = $demoraDado + intval($minutosGap) + $horasGap; //EN MINUTOS

                        //VERIFICO SI LA DEMORA CORRESPONDE A UN TURNO O A OTRO
                        //TURNO MAÑANA
                        $finTM = new DateTime(date('Y-m-d') . ' 06:13:00');
                        if ($finEsperadoDado <= $finTM) {
                            $demoraTT = $demoraTT + intval($minutosGap) + $horasGap;
                        } else {
                            $demoraTM = $demoraTM + intval($minutosGap) + $horasGap;
                        }
                    }

                    /**DE TODAS LAS FECHAS, VERIFICO SI ESTAN EN PARATE */
                    $fechaPlanFinDado = $this->sumaHorarioParate($fechaPlanFinDado);
                    $fechaRealFinDado = $this->sumaHorarioParate($fechaRealFinDado);
                    $fechaPlanInicioDado = $this->sumaHorarioParate($fechaPlanInicioDado);
                    $fechaRealInicioDado = $this->sumaHorarioParate($fechaRealInicioDado);

                    //Obtengo el dado
                    $materialAUtilizar = null;
                    $dadoAUtilizar = null;
                    if ($dado->es_reposicion) {
                        $dadoAUtilizar = $infoDado;
                        $materialAUtilizar = $infoDado?->material;
                    } else {
                        $dadoTemporal = ModeloKanbanPadre::where('dado', $dadoActual)->get();

                        if (count($dadoTemporal) == 1) {
                            $dadoAUtilizar = $dadoTemporal[0];
                            $materialAUtilizar = MaterialesPiezas::where('id', $dadoAUtilizar?->material_id)->first();
                        } else if (count($dadoTemporal) > 1) {
                            if (strlen($modelo) > 6) {
                                //COMPARTIDO
                                $compartido = ModelosCompartidos::where('name', $modelo)->first();
                                if ($compartido) {
                                    $dadoAUtilizar = ModeloKanbanPadre::where('dado', $dadoActual)->where('compartido_id', $compartido->id)->first();
                                    $materialAUtilizar = MaterialesPiezas::where('id', $dadoAUtilizar?->material_id)->first();
                                }
                            } else {
                                $modTemp = Modelos::where('nombre', $modelo)->first();
                                if ($modTemp) {
                                    $dadoAUtilizar = ModeloKanbanPadre::where('dado', $dadoActual)->where('modelo_id', $modTemp->id)->first();
                                    $materialAUtilizar = MaterialesPiezas::where('id', $dadoAUtilizar?->material_id)->first();
                                }
                            }
                        }
                    }

                    //AGREGO A PLAN                   
                    array_push($planificacion, [
                        'modelo'                => $modelo,
                        'inicio'                => $fechaPlanInicioDado->format('Y-m-d H:i:s'),
                        'horaInicio'            => $fechaPlanInicioDado->format('H:i'),
                        'horaFin'               => $fechaPlanFinDado->format('H:i'),
                        'fin'                   => $fechaPlanFinDado->format('Y-m-d H:i:s'),
                        'lectra'                => $lectra,
                        'demora'                => $demoraDado,
                        'duracion'              => str_pad($duracionDado[0],  2, "0", STR_PAD_LEFT) . ':' . str_pad($duracionDado[1], 2, "0", STR_PAD_LEFT) . ':' . str_pad($duracionDado[2], 2, "0", STR_PAD_LEFT),
                        'duracion_real'         => $duracionDado[0] . ':' . $duracionDado[1],
                        'group'                 => 'P-' . $lectra,
                        'dado'                  => $dadoActual,
                        'pieza_id'              => $dado->pieza_id,
                        'es_reposicion'         => boolval($dado->es_reposicion),
                        'operacion'             => $dado->operacion,
                        'material'              => $materialAUtilizar, //$dado->dado->material,
                        'fin_real_dado'         => $dado->fin, //SI EL DADO REALMENTE TERMINO
                        'inicio_real_dado'      => $dado->inicio, //SI EL DADO REALMENTE INICIO
                        'fin_estimado'          => $dado->fin_estimado,
                        'fecha_abastecido'      => $dado->fecha_abastecido,
                        'abastecido'            => $dado->abastecido,
                        'orden'                 => $orden,
                        'primerDadoModelo'      => $primerDadoModelo,
                        'id_lectra_estado'      => $dado->id,
                        'hora_fin_plan'         => $fechaPlanFinDado->format('H:i'),
                        'data_dado'             => $dadoAUtilizar, //$dado->dado,
                        'demoraTM'              => $demoraTM,
                        'demoraTT'              => $demoraTT,
                    ]);

                    LectraEstado::where('id', $dado->id)
                        ->update([
                            'inicio_plan'    => $fechaPlanInicioDado->format('Y-m-d H:i:s'),
                            'fin_plan'       => $fechaPlanFinDado->format('Y-m-d H:i:s')
                        ]);


                    //AGREGO A REAL
                    array_push($planificacion, [
                        'modelo'                => $modelo,
                        'inicio'                => $fechaRealInicioDado->format('Y-m-d H:i:s'),
                        'horaInicio'            => $fechaRealInicioDado->format('H:i'),
                        'horaFin'               => $fechaRealFinDado->format('H:i'),
                        'fin'                   => $fechaRealFinDado->format('Y-m-d H:i:s'),
                        'hora_fin_plan'         => $fechaPlanFinDado->format('H:i'),
                        'lectra'                => $lectra,
                        'primerDadoModelo'      => $primerDadoModelo,
                        'demora'                => $demoraDado,
                        'duracion'              => $duracionRealDado,
                        'duracion_real'         => $duracionDado[0] . ':' . $duracionDado[1],
                        'fecha_abastecido'      => $dado->fecha_abastecido,
                        'group'                 => 'R-' . $lectra,
                        'dado'                  => $dadoActual,
                        'pieza_id'              => $dado->pieza_id,
                        'es_reposicion'         => boolval($dado->es_reposicion),
                        'operacion'             => $dado->operacion,
                        'material'              => $materialAUtilizar, //$dado->dado->material,
                        'fin_real_dado'         => $dado->fin, //SI EL DADO REALMENTE TERMINO
                        'inicio_real_dado'      => $dado->inicio, //SI EL DADO REALMENTE INICIO
                        'fin_estimado'          => $dado->fin_estimado,
                        'abastecido'            => $dado->abastecido,
                        'demora_total_acum'     => $this->diferenciaFechas($fechaPlanFinDado, $fechaRealFinDado),
                        'orden'                 => $orden,
                        'id_lectra_estado'      => $dado->id,
                        'data_dado'             => $dadoAUtilizar, //$dado->dado,
                        'demoraTM'              => $demoraTM,
                        'demoraTT'              => $demoraTT,
                    ]);

                    $demoraDado = 0;
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("LectraController::getEstadoLectras4 : " . $th->getMessage());
        }

        $planDados = $planificacion;

        // $plan = array_filter($planDados, function ($k, $v) use ($lectra) {
        //     return $k['lectra'] == 1; //&& $k['group'] == 'P-3';
        // }, ARRAY_FILTER_USE_BOTH);

        // Log::alert($planificacion);

        $planificacion = $this->normalizaPlanificacion($planificacion);
        $planificacion = $this->agregaParateTimeLine($planificacion);
        $planLectra = $this->getPlanLectraModeloGap($planDados);

        return $this->setResponse([
            'planificacion'             => $planificacion,
            'dados'                     => $planDados,
            'planLectra'                => $planLectra,
            'turno_abastecimiento'      => $esScopeAbastecimiento && !is_null($ventanaTurno) ? $ventanaTurno['turno'] : null,
            'ventana_turno'             => $esScopeAbastecimiento && !is_null($ventanaTurno) ? [
                'inicio' => $ventanaTurno['inicio']->format('Y-m-d H:i:s'),
                'fin' => $ventanaTurno['fin']->format('Y-m-d H:i:s')
            ] : null,
            // 'stockBuffer'               => $stockBufferCorte,
            // 'stockBufferHoy'            => $stockBufferCorteHoy,
            // 'stockBufferCorteHoyModelo' => $stockBufferCorteHoyModelo,
        ]);
    }

    public function getEstadoLectras3() {
        // $lectras = [1];
        $lectras = [1, 2, 3, 4];
        $planificacion = [];
        $planDados = [];
        $demoraModelo = 0;
        $orden = 0;
        // $fecha = date("d/m/Y");
        $ayer = new DateTime();
        $dado = null;
        $modeloActual = null;
        $fechaInicioLectra = null;
        $fechaInicioRealDado = null;
        $fechaFinDado = null;
        $fechaPlanificadaFin = new DateTime();
        $ayer->sub(new DateInterval('P1D'));

        try {
            foreach ($lectras as $lectra) {
                $demora = 0;
                $fechaInicioLectra = null;
                $fechaFinDado = null;
                $fechaPlanificadaFin = new DateTime();
                // $horaDeberiaHaberTerminado = null;
                $demoraPorParate = 0;

                //OBTENGO LOS DADOS
                $dados = LectraEstado::with('dado.material')
                    ->where('lectra', $lectra)
                    ->where(function ($q) use ($ayer) {
                        $q->where('inicio', '>=', $ayer->format('Y-m-d') . ' 20:00:00');
                        $q->orWhere('inicio', null);
                    })
                    ->orderByRaw('COALESCE(inicio, GETDATE()) ASC, id asc')
                    ->get();


                foreach ($dados as $dado) {
                    try {
                        //OBTENGO LOS TIEMPOS DEL DADO
                        $times = $this->getTiempoLectraSiNoExiste($lectra, $dado->dado);

                        if ($modeloActual != "" && $modeloActual != $dado->modelo) {
                            $orden = $orden + 1;
                            $demoraModelo = 0;
                        }

                        $modeloActual = $dado->modelo;
                        if (is_null($modeloActual)) {
                            $modeloActual = $dado->dado->dado;
                        }

                        $dadoActual = $dado->dado->dado;

                        if (!is_null($fechaInicioLectra)) {
                            //LE SUMO EL TIEMPO DEL DADO
                            $fechaInicioLectra = $fechaPlanificadaFin;
                        }

                        //SI EL DADO ESTA INICIADO
                        if (!is_null($dado->inicio)) {
                            $fechaInicioDado = new DateTime($dado->inicio);

                            //ESTO ES EL COMIENZO DEL PRIMER DADO LEIDO
                            if (is_null($fechaInicioLectra)) {
                                $fechaInicioLectra = $fechaInicioDado;
                            }

                            //SI FINALIZO, TOMO LA FECHA, SI NO TOMO LA ACTUAL
                            $fechaFinDado = (!is_null($dado->fin)) ? new DateTime($dado->fin) : new DateTime($dado->inicio); // ($dado->inicio ? $fechaInicioDado->modify("+" . $times[0] . " hours")->modify("+" . $times[1] . " minutes") : new DateTime());
                            $fechaActualRealDuracion = $fechaFinDado;

                            if (!is_null($dado->fin)) {
                                //OBTENGO DURACION DE CORTE REAL
                                $interval = $fechaFinDado->diff($fechaInicioDado);
                                $duracionRealDado = [
                                    'horas'     => $interval->format('%H'),
                                    'minutos'   => $interval->format('%i'),
                                    'segundos'  => $interval->format('%s'),
                                    'fecha'     => $fechaActualRealDuracion, //new DateTime($interval->format('%d/%m/%Y %H:%i:%s')),
                                ];
                            } else {
                                $fechaFinDado =  new DateTime(); // $this->sumarTiempo($fechaFinDado->format('Y-m-d H:i:s'), intval($times[0]), intval($times[1]), intval($times[2]));
                                // $fechaFinDado =  $this->sumarTiempo($fechaFinDado->format('Y-m-d H:i:s'), intval($times[0]), intval($times[1]), intval($times[2]));

                                $duracionRealDado = [
                                    'horas'     => $times[0],
                                    'minutos'   => $times[1],
                                    'segundos'  => $times[2],
                                    'fecha'     => $fechaFinDado, //new DateTime($interval->format('%d/%m/%Y %H:%i:%s')),
                                    // 'fecha'     => new DateTime(), //$fechaFinDado, //new DateTime($interval->format('%d/%m/%Y %H:%i:%s')),
                                ];
                            }

                            // if (is_null($dado->fin_estimado)) {
                            if (!is_null($dado->fin)) {
                                $horaDeberiaHaberTerminado = new DateTime($dado->fin);
                            } else {
                                $horaDeberiaHaberTerminado = $this->sumarTiempo($fechaInicioDado->format('Y-m-d H:i:s'), intval($times[0]), intval($times[1]), intval($times[2]));
                            }
                            // } else {
                            //     $horaDeberiaHaberTerminado = DateTime::createFromFormat('Y-m-d H:i:s.v', $dado->fin_estimado);
                            // }

                            if (is_null($dado->fin)) {
                                $horaDeberiaHaberTerminado = $this->verificaRangoParate($fechaInicioDado, $horaDeberiaHaberTerminado);
                                $horaDeberiaHaberTerminado = $this->sumaHorarioParate($horaDeberiaHaberTerminado);
                                // $fechaFinDado = $horaDeberiaHaberTerminado; //??
                            }

                            $fechaActual = new DateTime();
                            // $interval2 = $fechaActual->diff($horaDeberiaHaberTerminado);

                            //COMPARO TIEMPO DEMORA REAL CON TIEMPO TEORICO DE DADO
                            //PARA OBTENER SI DEMORO MÁS DE LO QUE DEBERÍA
                            if ($duracionRealDado['fecha'] > $horaDeberiaHaberTerminado) {

                                $intervalDiff = $duracionRealDado['fecha']->diff($horaDeberiaHaberTerminado);

                                $horasDiferencia = $intervalDiff->format('%H');
                                $minutosDiferencia = $intervalDiff->format('%i');
                                $diasDiferencia = $intervalDiff->format('%d');

                                $horasDiferencia = (intval($horasDiferencia) + ($diasDiferencia * 24)) * 60; //EN MINUTOS
                                $demora = $demora + intval($minutosDiferencia) + $horasDiferencia;

                                //VERIFICO SI EL HORARIO ESTA ENTRE LOS PARATES
                                $fechaHoraActual = new DateTime();
                                $fechaSiguiente = new DateTime();
                                $fechaSiguiente->add(new DateInterval('P1D'));
                                $fechas = [
                                    'i1' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') . ' 15:10'),
                                    'f1' => DateTime::createFromFormat('d/m/Y H:i',  $fechaHoraActual->format('d/m/Y') . ' 15:40'),
                                    'i2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 00:50'),
                                    'f2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 05:59'),
                                    'i3' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') .  ' 00:50'),
                                ];

                                if (($fechaInicioDado < $fechas['i1'] && $fechaFinDado > $fechas['f1']) || ($fechaInicioDado < $fechas['i1'] && ($fechaHoraActual > $fechas['i1']) && is_null($dado->fin))) {
                                    //Esta dentro del parate de cambio de turno. Restar 30 min.
                                    // if ($lectra == 4 && $dadoActual == 'AA3_AA8-K12-14') {
                                    //     Log::alert("PASO");
                                    // }
                                    // $demora = $demora - 30;
                                    // if ($demora < 0) {
                                    //     $demora = 0;
                                    // }
                                    $demoraPorParate = $demoraPorParate + 30;
                                }

                                if (($fechaInicioDado < $fechas['i3'] && $fechaFinDado < $fechas['i2']) ||  ($fechaInicioDado < $fechas['i3'] && $fechaFinDado > $fechas['f2']) || ($fechaInicioDado < $fechas['i3'] && ($fechaHoraActual > $fechas['i3']) && is_null($dado->fin))) {
                                    //if (($fechaInicioDado < $fechas['i3'] && $fechaFinDado > $fechas['f2']) || ($fechaInicioDado < $fechas['i3'] && ($fechaHoraActual > $fechas['i3']) && is_null($dado->fin))) {
                                    //Esta dentro del parate de cierre. Restar 310 minutos, equivalente a 05:10 hs.
                                    // $demora = $demora - 310;
                                    // if ($demora < 0) {
                                    //     $demora = 0;
                                    // }
                                    $demoraPorParate = $demoraPorParate + 310;
                                }
                            }

                            $fechaInicioRealDado = $fechaInicioDado;
                        } else {
                            if (is_null($fechaInicioLectra)) {
                                $fechaInicioLectra = new DateTime();
                            }

                            $fechaActual = new DateTime();

                            // if ($lectra == 1) {
                            //     Log::alert(is_null($fechaFinDado));
                            // }
                            //SI NO INICIO, ENTONCES TOMO LA FECHA DE FIN DEL ULTIMO DADO COMO INICIAL
                            $fechaInicioRealDado = is_null($fechaFinDado) ? $fechaInicioLectra : $fechaFinDado;

                            $fechaFinDado = $this->sumarTiempo($fechaInicioRealDado->format('Y-m-d H:i:s'), intval($times[0]), intval($times[1]), intval($times[2])); //AL INICIO LE SUMO LA DURACION DEL DADO

                            $fechaInicioRealDado = $this->sumaHorarioParate($fechaInicioRealDado);
                            $fechaFinDado = $this->sumaHorarioParate($fechaFinDado);
                            $horaDeberiaHaberTerminado = $fechaFinDado;

                            $horaDeberiaHaberTerminado = $this->verificaRangoParate($fechaInicioRealDado, $horaDeberiaHaberTerminado);
                            $horaDeberiaHaberTerminado = $this->sumaHorarioParate($horaDeberiaHaberTerminado);

                            $duracionRealDado = [
                                'horas'     => $times[0],
                                'minutos'   => $times[1],
                                'segundos'  => $times[2],
                                'fecha'     => $fechaFinDado
                            ];

                            if ($fechaActual > $fechaFinDado) {

                                $intervalDiff = $fechaActual->diff($horaDeberiaHaberTerminado);

                                $horasDiferencia = $intervalDiff->format('%H');
                                $minutosDiferencia = $intervalDiff->format('%i');
                                $diasDiferencia = $intervalDiff->format('%d');

                                $horasDiferencia = (intval($horasDiferencia) + ($diasDiferencia * 24)) * 60; //EN MINUTOS
                                $demora = $demora + intval($minutosDiferencia) + $horasDiferencia;

                                //VERIFICO SI EL HORARIO ESTA ENTRE LOS PARATES
                                $fechaHoraActual = new DateTime();
                                $fechaSiguiente = new DateTime();
                                $fechaSiguiente->add(new DateInterval('P1D'));
                                $fechas = [
                                    'i1' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') . ' 15:10'),
                                    'f1' => DateTime::createFromFormat('d/m/Y H:i',  $fechaHoraActual->format('d/m/Y') . ' 15:40'),
                                    'i2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 00:50'),
                                    'f2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 05:59'),
                                    'i3' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') .  ' 00:50'),
                                ];

                                if (($fechaInicioRealDado < $fechas['i1'] && $fechaFinDado > $fechas['f1']) || ($fechaInicioRealDado < $fechas['i1'] && ($fechaHoraActual > $fechas['i1']) && is_null($dado->fin))) {
                                    //Esta dentro del parate de cambio de turno. Restar 30 min.
                                    // $demora = $demora - 30;
                                    // if ($demora < 0) {
                                    //     $demora = 0;
                                    // }
                                    $demoraPorParate = $demoraPorParate + 30;
                                }

                                if (($fechaInicioRealDado < $fechas['i3'] && $fechaFinDado < $fechas['i2']) ||  ($fechaInicioRealDado < $fechas['i3'] && $fechaFinDado > $fechas['f2']) || ($fechaInicioRealDado < $fechas['i3'] && ($fechaHoraActual > $fechas['i3']) && is_null($dado->fin))) {
                                    //if (($fechaInicioDado < $fechas['i3'] && $fechaFinDado > $fechas['f2']) || ($fechaInicioDado < $fechas['i3'] && ($fechaHoraActual > $fechas['i3']) && is_null($dado->fin))) {
                                    //Esta dentro del parate de cierre. Restar 310 minutos, equivalente a 05:10 hs.
                                    // $demora = $demora - 310;
                                    // if ($demora < 0) {
                                    //     $demora = 0;
                                    // }
                                    $demoraPorParate = $demoraPorParate + 310;
                                }
                            }
                        }

                        //INICIO + DURACION
                        $fechaPlanificadaFin = $this->sumarTiempo($fechaInicioLectra->format('Y-m-d H:i:s'), intval($duracionRealDado['horas']), intval($duracionRealDado['minutos']), intval($duracionRealDado['segundos']));
                        $horaDeberiaHaberTerminado = $this->verificaRangoParate($fechaInicioLectra, $fechaPlanificadaFin);
                        $fechaPlanificadaFin = $this->sumaHorarioParate($fechaPlanificadaFin);

                        //AGREGO A PLAN                   
                        array_push($planificacion, [
                            'modelo'                => $modeloActual,
                            'inicio'                => $fechaInicioLectra->format('Y-m-d H:i:s'),
                            'horaInicio'            => $fechaInicioLectra->format('H:i'),
                            'horaFin'               => $fechaPlanificadaFin->format('H:i'),
                            'fin'                   => $fechaPlanificadaFin->format('Y-m-d H:i:s'),
                            'lectra'                => $lectra,
                            'demora'                => $demora,
                            'duracion'              => str_pad($times[0],  2, "0", STR_PAD_LEFT) . ':' . str_pad($times[1], 2, "0", STR_PAD_LEFT),
                            'group'                 => 'P-' . $lectra,
                            'dado'                  => $dadoActual,
                            'operacion'             => $dado->operacion,
                            'material'              => $dado->dado->material,
                            'fin_real_dado'         => $dado->fin, //SI EL DADO REALMENTE TERMINO
                            'inicio_real_dado'      => $dado->inicio, //SI EL DADO REALMENTE INICIO
                            'fin_estimado'          => $dado->fin_estimado,
                            'abastecido'            => $dado->abastecido,
                            'orden'                 => $orden,
                            'fin_estimado_parate'   => $horaDeberiaHaberTerminado->format('Y-m-d H:i:s'),
                            'id_lectra_estado'      => $dado->id,
                            'hora_fin_plan'         => $fechaPlanificadaFin->format('H:i'),
                            'data_dado'             => $dado->dado

                        ]);

                        $fechaFinDadoSinDemora = $fechaFinDado;
                        if (is_null($dado->fin)) {
                            // $fechaFinDado = $this->sumarTiempo($fechaFinDado->format('Y-m-d H:i:s'), 0, $demora, 0);
                            $fechaFinDado = $this->sumaHorarioParate($fechaFinDado);
                            // $fechaFinDado = $this->sumarTiempo($fechaFinDado->format('Y-m-d H:i:s'), $times[0], $times[1], $times[2]);
                        }

                        //AGREGO A REAL
                        array_push($planificacion, [
                            'modelo'                => $modeloActual,
                            'inicio'                => $fechaInicioRealDado->format('Y-m-d H:i:s'),
                            'horaInicio'            => $fechaInicioRealDado->format('H:i'),
                            'horaFin'               => $fechaFinDado->format('H:i'),
                            // 'horaFin2'              => is_null($dado->fin) ? $this->sumarTiempo($fechaFinDadoSinDemora->format('Y-m-d H:i:s'), $times[0], $times[1], $times[2])->format('H:i') : $fechaFinDado->format('H:i'),
                            'fin'                   => $fechaFinDado->format('Y-m-d H:i:s'),
                            'horaFinSinDemora'      => $fechaFinDadoSinDemora->format('H:i'),
                            'hora_fin_plan'         => $fechaPlanificadaFin->format('H:i'),
                            'lectra'                => $lectra,
                            'demora'                => $demora,
                            'duracion'              => str_pad($duracionRealDado['horas'], 2, "0", STR_PAD_LEFT) . ':' . str_pad($duracionRealDado['minutos'], 2, "0", STR_PAD_LEFT),
                            'group'                 => 'R-' . $lectra,
                            'dado'                  => $dadoActual,
                            'operacion'             => $dado->operacion,
                            'material'              => $dado->dado->material,
                            'fin_real_dado'         => $dado->fin, //SI EL DADO REALMENTE TERMINO
                            'inicio_real_dado'      => $dado->inicio, //SI EL DADO REALMENTE INICIO
                            'fin_estimado'          => $dado->fin_estimado,
                            'abastecido'            => $dado->abastecido,
                            'demora_total_acum'     => $this->diferenciaFechas($fechaPlanificadaFin, $fechaFinDado),
                            'orden'                 => $orden,
                            'fin_estimado_parate'   => $horaDeberiaHaberTerminado->format('Y-m-d H:i:s'),
                            'id_lectra_estado'      => $dado->id,
                            'data_dado'             => $dado->dado,
                        ]);
                    } catch (\Throwable $th) {
                        //throw $th;
                        Log::error('LectraController::getEstadoLectras3 : ' . $th->getMessage());
                    }
                    $demoraModelo = $demoraModelo + $demora;
                    $demoraPorParate = 0;
                    $demora = 0;
                }
            }

            $planDados = $planificacion;
            // Log::alert(json_encode($planificacion, JSON_PRETTY_PRINT));


            // $plan = array_filter($planDados, function ($k, $v) use ($lectra) {
            //     return $k['lectra'] == 1; //&& $k['group'] == 'P-3';
            // }, ARRAY_FILTER_USE_BOTH);

            // Log::alert($plan);


            $planificacion = $this->normalizaPlanificacion($planificacion);
            $planificacion = $this->agregaParateTimeLine($planificacion);
        } catch (\Throwable $th) {
            Log::error("LectraController::getEstadoLectras3 - " . $th->getMessage());
        }

        $planLectra = $this->getPlanLectraModeloGap($planDados);

        return $this->setResponse([
            'planificacion' => $planificacion,
            'dados'         => $planDados,
            'planLectra'    => $planLectra
        ]);
    }

    public function getEstadoStockBufferGraficoOld() {
        //OBTENGO LOS DATOS DE STOCK DE BUFFER CORTE
        $stockBufferCorte = LogEstadosKanbans::selectRaw('DATEPART(weekday, log_estados_kanbans.updated_at) AS DiaSemana, sum(modelos.cantidad) as sets, DAY(log_estados_kanbans.updated_at) as dia,month(log_estados_kanbans.updated_at) as mes, year(log_estados_kanbans.updated_at) as anio')
            ->where('log_estados_kanbans.estado_id', 2)
            ->whereRaw("log_estados_kanbans.updated_at>='2026-05-01 00:00:01'")
            // ->whereRaw('log_estados_kanbans.updated_at>=DATEADD(DAY, -30, GETDATE())')
            ->leftJoin('kanbans', 'kanbans.id', '=', 'kanban_id')
            ->leftJoin('modelos', 'modelos.id', '=', 'kanbans.modelo_id')
            ->groupByRaw('day(log_estados_kanbans.updated_at), month(log_estados_kanbans.updated_at),year(log_estados_kanbans.updated_at), DATEPART(weekday, log_estados_kanbans.updated_at)')
            ->orderByRaw('year(log_estados_kanbans.updated_at),month(log_estados_kanbans.updated_at),day(log_estados_kanbans.updated_at)')
            // ->havingRaw('DATEPART(weekday, log_estados_kanbans.updated_at) !=1 and DATEPART(weekday, log_estados_kanbans.updated_at) !=7')
            ->get();


        $lastSets = 0;
        foreach ($stockBufferCorte as $stockB) {
            //ELIMINO LOS KANBANS QUE TUVIERON MOVIMIENTOS RAPIDOS EN EL BUFFER, 
            //PORQUE SON LOS EXTRA QUE METEN, POR KANBAN PERDIDO

            $fechaDesdeTest = new DateTime();
            $fechaDesdeTest->setDate($stockB->anio, $stockB->mes, $stockB->dia - 1);

            $fechaHastaTest = (clone $fechaDesdeTest);
            $fechaHastaTest->add(new DateInterval('P1D'));

            $kanbansDia = DB::selectOne("select sum(cantidad) as cantidad from (
                select case when l.estado_id=2 then m.cantidad else m.cantidad *-1 end as cantidad 
                from log_estados_kanbans l left join kanbans k on l.kanban_id = k.id left join modelos m on k.modelo_id = m.id
                where (l.updated_at>='" . $fechaDesdeTest->format('Y-m-d') . " 01:00:00' 
                and l.updated_at<='" . $fechaHastaTest->format('Y-m-d') . " 00:59:59') 
                and (l.estado_id=2 or l.estado_id=3)
                ) as a");

            $kanbansIncorrectos = DB::selectOne("select sum(cantidad) as cantidad from (select sum(cantidad) as cantidad from (
                select l.kanban_id, l.updated_at as ingreso,m.nombre as modelo,m.cantidad, (select top 1 updated_at from log_estados_kanbans 
                where kanban_id= l.kanban_id and estado_id=3) as egreso 
                from log_estados_kanbans l left join kanbans k on l.kanban_id = k.id left join modelos m on k.modelo_id = m.id
                where (l.updated_at>='" . $fechaDesdeTest->format('Y-m-d') . " 01:00:00' 
                and l.updated_at<='" . $fechaHastaTest->format('Y-m-d') . " 00:59:59') 
                and l.estado_id=2
                ) as a where not egreso is null and DATEDIFF(MINUTE,ingreso,egreso)<=1
                ) as b");

            if ($lastSets > 0) {
                $lastSets = $lastSets - intval($kanbansIncorrectos->cantidad) + $kanbansDia->cantidad;
                $stockB->sets = $lastSets;
            } else {
                if ($kanbansIncorrectos) {
                    $stockB->sets = intval($stockB->sets) - intval($kanbansIncorrectos->cantidad);
                }
            }

            if ($stockB->mes == "10" &&  $stockB->dia == "01") {
                // $stockB->sets = 920 - $kanbansIncorrectos->cantidad + $kanbansDia->cantidad;
                // $lastSets = 920 - $kanbansIncorrectos->cantidad + $kanbansDia->cantidad;
            } else if ($stockB->mes == "11" &&  $stockB->dia == "10") {
                $stockB->sets = 780;
                $lastSets = 780;
            } else if ($stockB->mes == "11" &&  $stockB->dia == "11") {
                $stockB->sets = 860;
                $lastSets = 860;
            }
        }

        $stockBufferCorteHoy = EstadoKanban::selectRaw('sum(modelos.cantidad) as sets')
            ->leftJoin('kanbans', 'kanbans.id', '=', 'kanban_id')
            ->leftJoin('modelos', 'modelos.id', '=', 'kanbans.modelo_id')
            ->where('estado_id', 2)
            ->first();


        $stockBufferCorteHoyModelo = Modelos::with(['enBuffer'])->where('activo', true)->where('consumo', '>', 0)->get();

        return $this->setResponse([
            'stockBuffer'               => $stockBufferCorte,
            'stockBufferHoy'            => $stockBufferCorteHoy,
            'stockBufferCorteHoyModelo' => $stockBufferCorteHoyModelo,
        ]);
    }

    public function getEstadoStockBufferGrafico() {
        $fechaDesde = '2026-05-01 00:00:01';
        $estadoBuffer = Estados::EN_BUFFER;
        $estadoSalidaBuffer = Estados::SUB_ASSY;

        $stockBufferCorte = LogEstadosKanbans::selectRaw('DATEPART(weekday, log_estados_kanbans.updated_at) AS DiaSemana, sum(modelos.cantidad) as sets, DAY(log_estados_kanbans.updated_at) as dia, month(log_estados_kanbans.updated_at) as mes, year(log_estados_kanbans.updated_at) as anio')
            ->where('log_estados_kanbans.estado_id', $estadoBuffer)
            ->where('log_estados_kanbans.updated_at', '>=', $fechaDesde)
            ->leftJoin('kanbans', 'kanbans.id', '=', 'kanban_id')
            ->leftJoin('modelos', 'modelos.id', '=', 'kanbans.modelo_id')
            ->groupByRaw('day(log_estados_kanbans.updated_at), month(log_estados_kanbans.updated_at), year(log_estados_kanbans.updated_at), DATEPART(weekday, log_estados_kanbans.updated_at)')
            ->orderByRaw('year(log_estados_kanbans.updated_at), month(log_estados_kanbans.updated_at), day(log_estados_kanbans.updated_at)')
            ->get();

        $normalizaFechaSql = function ($fecha) {
            if ($fecha instanceof \DateTimeInterface) {
                return $fecha->format('Y-m-d');
            }

            return substr(strval($fecha), 0, 10);
        };

        $movimientosPorDia = collect(DB::select("
            select
                cast(DATEADD(hour, 23, l.updated_at) as date) as fecha,
                sum(case when l.estado_id = ? then isnull(m.cantidad, 0) else isnull(m.cantidad, 0) * -1 end) as cantidad
            from log_estados_kanbans l
            left join kanbans k on l.kanban_id = k.id
            left join modelos m on k.modelo_id = m.id
            where l.updated_at >= DATEADD(day, -1, cast(? as datetime))
                and (l.estado_id = ? or l.estado_id = ?)
            group by cast(DATEADD(hour, 23, l.updated_at) as date)
        ", [$estadoBuffer, $fechaDesde, $estadoBuffer, $estadoSalidaBuffer]))
            ->keyBy(fn($row) => $normalizaFechaSql($row->fecha));

        $incorrectosPorDia = collect(DB::select("
            select
                fecha,
                sum(cantidad) as cantidad
            from (
                select
                    cast(DATEADD(hour, 23, l.updated_at) as date) as fecha,
                    l.kanban_id,
                    max(isnull(m.cantidad, 0)) as cantidad
                from log_estados_kanbans l
                left join kanbans k on l.kanban_id = k.id
                left join modelos m on k.modelo_id = m.id
                outer apply (
                    select top 1 le.updated_at
                    from log_estados_kanbans le
                    where le.kanban_id = l.kanban_id
                        and le.estado_id = ?
                        and le.updated_at >= l.updated_at
                    order by le.updated_at
                ) egreso
                where l.updated_at >= DATEADD(day, -1, cast(? as datetime))
                    and l.estado_id = ?
                    and egreso.updated_at is not null
                    and DATEDIFF(MINUTE, l.updated_at, egreso.updated_at) <= 1
                group by cast(DATEADD(hour, 23, l.updated_at) as date), l.kanban_id
            ) incorrectos
            group by fecha
        ", [$estadoSalidaBuffer, $fechaDesde, $estadoBuffer]))
            ->keyBy(fn($row) => $normalizaFechaSql($row->fecha));

        $lastSets = 0;
        foreach ($stockBufferCorte as $stockB) {
            $fecha = DateTime::createFromFormat('Y-n-j', $stockB->anio . '-' . $stockB->mes . '-' . $stockB->dia);
            $fechaKey = $fecha ? $fecha->format('Y-m-d') : null;
            $kanbansDia = $fechaKey && isset($movimientosPorDia[$fechaKey]) ? intval($movimientosPorDia[$fechaKey]->cantidad) : 0;
            $kanbansIncorrectos = $fechaKey && isset($incorrectosPorDia[$fechaKey]) ? intval($incorrectosPorDia[$fechaKey]->cantidad) : 0;
            $stockB->sets = intval($stockB->sets);

            if ($lastSets > 0) {
                $lastSets = $lastSets - $kanbansIncorrectos + $kanbansDia;
                $stockB->sets = intval($lastSets);
            } else if ($kanbansIncorrectos > 0) {
                $stockB->sets = intval($stockB->sets) - $kanbansIncorrectos;
            }

            if ($stockB->mes == "11" &&  $stockB->dia == "10") {
                $stockB->sets = 780;
                $lastSets = 780;
            } else if ($stockB->mes == "11" &&  $stockB->dia == "11") {
                $stockB->sets = 860;
                $lastSets = 860;
            }
        }

        $stockBufferCorteHoy = EstadoKanban::selectRaw('sum(modelos.cantidad) as sets')
            ->leftJoin('kanbans', 'kanbans.id', '=', 'kanban_id')
            ->leftJoin('modelos', 'modelos.id', '=', 'kanbans.modelo_id')
            ->where('estado_id', $estadoBuffer)
            ->first();
        $stockBufferCorteHoy->sets = intval($stockBufferCorteHoy->sets);

        $stockBufferCorteHoyModelo = Modelos::with(['enBuffer'])->where('activo', true)->where('consumo', '>', 0)->get();

        return $this->setResponse([
            'stockBuffer'               => $stockBufferCorte,
            'stockBufferHoy'            => $stockBufferCorteHoy,
            'stockBufferCorteHoyModelo' => $stockBufferCorteHoyModelo,
        ]);
    }

    /**
     * Recorro el plan y obtengo los modelos que se estan cortando y el proximo
     * El estado actual de la lectra
     */
    private function getPlanLectraModeloGap($plans) {
        $lectras = [1, 2, 3, 4];
        $response = [];

        foreach ($lectras as $lectra) {
            $estadoLectra = '';
            $proximo = '';
            $horaPlan = '';
            $horaReal = '';
            $gap = '';
            $yaPasoPorModelo = false;
            $modelo = '';

            //Busco si se esta cortando un modelo en la lectra
            //O si debería, porque la lectra tiene inicio planificado


            $modeloActualCorte = LectraEstado::where('lectra', $lectra)
                ->whereRaw('not inicio is null and fin is null')->first();

            if (!$modeloActualCorte) {
                //VERIFICO SI DEBERÍA ESTAR CORTANDO
                // $dataInicio = InicioLectra::where('lectra', $lectra)->where('fecha', date('Y-m-d'))->first();
                //Si no hay nada en corte, puede estar en preparación o sin plan
                $modeloActualCorte = LectraEstado::where('lectra', $lectra)->where('inicio', null)->where('fin', null)->first();

                if ($modeloActualCorte) {
                    $estadoLectra = 'PREPARACIÓN';
                } else {
                    $estadoLectra = 'SIN PLAN';
                }
            } else {
                //Si hay cortes, está en proceso
                $estadoLectra = 'EN PROCESO';
            }

            $plan = array_filter($plans, function ($k, $v) use ($lectra) {
                return $k['lectra'] == $lectra && $k['group'] == 'R-' . $lectra;
            }, ARRAY_FILTER_USE_BOTH);

            if (count($plan) == 0) {
                $estadoLectra = 'SIN PLAN';
            } else {
                if ($modeloActualCorte) {
                    foreach ($plan as $p) {
                        if ($p['id_lectra_estado'] >= $modeloActualCorte->id || $p['modelo'] == $modeloActualCorte->modelo) {
                            if (($modeloActualCorte->dado_id == FallasLectra::DEMORA_PICKEO || $modeloActualCorte->dado_id == FallasLectra::FALLA_SISTEMA || $modeloActualCorte->dado_id == FallasLectra::FALTA_DE_TENDIDO || $modeloActualCorte->dado_id == FallasLectra::MANTENIMIENTO)) {
                                if (!is_null($p['fin'])) {
                                    $gap = '';
                                    $horaPlan = '';
                                    $horaReal = '';
                                    $yaPasoPorModelo = true;
                                    $modelo = $modeloActualCorte->dado_id;
                                    $estadoLectra = $modeloActualCorte->dado_id;
                                }
                            } else {
                                if ($p['modelo'] == $modeloActualCorte->modelo || $p['modelo'] == $modeloActualCorte->dado_id) {

                                    $yaPasoPorModelo = true;
                                    $gap = $p['demora_total_acum'];
                                    $horaPlan = $p['hora_fin_plan'];
                                    $horaReal = $p['horaFin'];
                                    $modelo = $p['modelo'];
                                } else {
                                    if ($yaPasoPorModelo && $proximo == '' && $p['modelo'] != '') {

                                        $proximo = $p['modelo'];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            array_push($response, [
                'lectra'    => $lectra,
                'estado'    => $estadoLectra,
                'modelo'    => $modelo,
                'plan'      => $horaPlan,
                'real'      => $horaReal,
                'gap'       => $gap,
                'prox'      => $proximo,
            ]);
        }
        // Log::alert($response);
        return $response;
    }

    private function diferenciaFechas($fechaFinPlan, $fechaFin) {

        if ($fechaFin > $fechaFinPlan) {
            $interval = $fechaFinPlan->diff($fechaFin);
            $hours = $interval->format('%H');
            $minutes = $interval->format('%i');
            $days = $interval->format('%d');

            $hours = (intval($hours) + ($days * 24));
            return str_pad($hours, 2, "0", STR_PAD_LEFT) . ':' . str_pad($minutes, 2, "0", STR_PAD_LEFT);
        } else {
            return '00:00';
        }
    }

    private function normalizaPlanificacion($planificacion) {
        //Recorro la planificacion unificando modelos consecutivos
        $modeloActual = null;
        $nuevoPlan = [];
        $encontro = false;

        foreach ($planificacion as $lindex => $plan) {
            $modeloActual = $plan['modelo'] . $plan['group'] . $plan['operacion'] . $plan['orden'];
            $encontro = false;
            $index = -1;

            if (count($nuevoPlan) > 0) {
                foreach ($nuevoPlan as $lindex => $nPlan) {
                    if ($nPlan['modelo'] . $nPlan['group'] . $nPlan['operacion'] . $nPlan['orden'] == $modeloActual) {
                        $encontro = true;
                        $index = $lindex;
                        break;
                    }
                }
            }

            if ($encontro) {
                $horas = 0;
                $minutos = 0;
                $duracionAnterior = explode(":", $nuevoPlan[$index]['duracion']);
                $duracion = explode(":", $plan['duracion']);

                $horas = intval($duracionAnterior[0]) + intval($duracion[0]);
                $minutos = intval($duracionAnterior[1]) + intval($duracion[1]);

                if ($minutos >= 60) {
                    $minutos = $minutos / 60;
                    $horas = $horas + intval($minutos);

                    $minutos = ($minutos - intval($minutos)) * 60;
                }

                $nuevoPlan[$index]['fin'] = $plan['fin'];
                $nuevoPlan[$index]['horaFin'] = $plan['horaFin'];
                $nuevoPlan[$index]['demora'] = $nuevoPlan[$index]['demora'] +  $plan['demora'];
                $nuevoPlan[$index]['duracion'] = str_pad($horas, 2, "0", STR_PAD_LEFT) . ":" . str_pad($minutos, 2, "0", STR_PAD_LEFT);
            } else {
                array_push($nuevoPlan, $plan);
            }
        }

        return $nuevoPlan;
    }

    private function sumarTiempo($inicial, $hours = 0, $minutes = 0, $seconds = 0, $format = 'Y-m-d H:i:s') {

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

    public function getEstadoLectras2() {

        $lectras = [1, 2, 3, 4];
        $planificacion = [];
        $fecha = date("d/m/Y");
        $ayer = new DateTime();
        $horaRealFin = null;
        $ayer->sub(new DateInterval('P1D'));
        $planCorte = PlanCorte::where('fecha', '>=', $ayer->format('d/m/Y'))->where('fecha', '<=', $fecha)->orderBy('fecha', 'ASC')->get();
        $inicioOriginal = null;
        $demoraTotal = 0;

        if (!$planCorte) {
            return $this->setResponse([
                ['lectra' => '1'],
                ['lectra' => '2'],
                ['lectra' => '3'],
                ['lectra' => '4'],
            ]);
        }

        foreach ($lectras as $lectra) {
            $inicio = null;
            $inicioOriginal = null;
            $modeloActual = null;
            $modeloAnterior = null;
            // $tieneFin = false;
            // $tieneInicio = false;
            $demoraTotal = 0;
            $horaInicio = null;

            foreach ($planCorte as $plan) {
                $demora = 0;
                $demoraPorParate = 0;

                //Obtengo el tiempo de duración del modelo segun los dados
                $dados = LectraEstado::with('dado')->where('lectra', $lectra)
                    ->where('operacion', $plan->operacion)
                    // ->orderBy('inicio', 'DESC')
                    // ->orderBy('created_at', 'ASC')
                    ->get();

                foreach ($dados as $dado) {
                    $modeloActual = $dado->modelo;
                    //Obtengo los tiempos para la lectra
                    $times = explode(":", $dado->dado->{"t_lectra" . $lectra});
                    $mod = $dado->modelo;

                    if ($dado->dado->{"t_lectra" . $lectra}) {
                        if ($dado->inicio) {
                            $tieneInicio = true;
                            if (is_null($horaInicio)) {
                                $horaInicio = new DateTime($dado->inicio);
                            }

                            $dateInicioDado = new DateTime($dado->inicio);
                            if ($dado->fin) {
                                //El dado ya termino
                                $dateFinDado = new DateTime($dado->fin);
                                $tieneFin = true;
                            } else {
                                //El dado se esta cortando
                                $dateFinDado = new DateTime();
                                $tieneFin = false;
                            }

                            $interval = $dateFinDado->diff($dateInicioDado);
                            $hours = $interval->format('%H');
                            $minutes = $interval->format('%i');

                            $newDate = new DateTime();
                            $newDate2 = new DateTime();

                            $newDate->modify("+" . intval($times[0]) . " hours");
                            $newDate->modify("+" . intval($times[1]) . " minutes");
                            $newDate->modify("+" . intval($times[2]) . " seconds");
                            $newDate->modify("+2 minutes");

                            $interval2 = $newDate2->diff($newDate);

                            $tiempoDado = $interval->format('%d/%m/%Y %H:%i:%s');
                            $tiempoActualDado = $interval2->format('%d/%m/%Y %H:%i:%s');

                            $date1 = new DateTime($tiempoDado);
                            $date2 = new DateTime($tiempoActualDado);

                            if ($date1 > $date2) {
                                $intervalDiff = $date2->diff($date1);
                                $hoursd = $intervalDiff->format('%H');
                                $minutesd = $intervalDiff->format('%i');
                                $secondsd = $intervalDiff->format('%s');
                                $daysd = $intervalDiff->format('%d');

                                $hoursd = (intval($hoursd) + ($daysd * 24)) * 60;
                                $secondsd = intval($secondsd) / 60;
                                $demora = $demora + intval($minutesd) + $hoursd;

                                $fechaHoraActual = new DateTime();
                                $fechaSiguiente = new DateTime();
                                $fechaSiguiente->add(new DateInterval('P1D'));

                                $fechas = [
                                    'i1' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') . ' 15:10'),
                                    'f1' => DateTime::createFromFormat('d/m/Y H:i',  $fechaHoraActual->format('d/m/Y') . ' 15:40'),
                                    'i2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 00:50'),
                                    'f2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 05:59'),
                                    'i3' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') .  ' 00:50'),
                                ];

                                if (($dateInicioDado < $fechas['i1'] && $dateFinDado > $fechas['f1']) || ($dateInicioDado < $fechas['i1'] && ($fechaHoraActual > $fechas['i1']) && is_null($dado->fin))) {
                                    //Esta dentro del parate de cambio de turno. Restar 30 min.
                                    $demora = $demora - 30;
                                    if ($demora < 0) {
                                        $demora = 0;
                                    }
                                    $demoraPorParate = $demoraPorParate + 30;
                                }

                                if (($dateInicioDado < $fechas['i3'] && $dateFinDado > $fechas['f2']) || ($dateInicioDado < $fechas['i3'] && ($fechaHoraActual > $fechas['i3']) && is_null($dado->fin))) {
                                    //Esta dentro del parate de cierre. Restar 370 minutos, equivalente a 05:10 hs.
                                    $demora = $demora - 310;
                                    if ($demora < 0) {
                                        $demora = 0;
                                    }
                                    $demoraPorParate = $demoraPorParate + 310;
                                }
                            }
                        } else {

                            $demora = $demora + 0;
                        }

                        if (is_null($horaInicio)) {
                            $horaInicio = new DateTime();
                        }

                        if (is_null($inicio)) {
                            $inicio = $horaInicio->format('Y-m-d H:i:s');
                            if (is_null($inicioOriginal)) {
                                $inicioOriginal =  $horaInicio->format('Y-m-d H:i:s');;
                            }
                        }

                        for ($i = 0; $i < intval($dado->dado->corte); $i++) {
                            //Sumo las horas,minutos y segundos por cada repeticion
                            $horaInicio->modify("+" . intval($times[0]) . " hours");
                            $horaInicio->modify("+" . intval($times[1]) . " minutes");
                            $horaInicio->modify("+" . intval($times[2]) . " seconds");
                        }

                        //SUMO POSICIONAMIENTO
                        $horaInicio->modify("+2 minutes");
                    }

                    $horaFin = $horaInicio;
                    $horaInicio->modify("+" . $demora . " minutes");
                    $horaRealFin = DateTime::createFromFormat('Y-m-d H:i:s', $horaInicio->format('Y-m-d H:i:s'));

                    $interval = $horaFin->diff(new DateTime($inicio));
                    $hours = $interval->format('%H');
                    $minutes = $interval->format('%i');

                    //TODO Verificar si esta dentro del rango del parate, si esta, calcular diferencia y 
                    //pasarlo para el comienzo del turno
                    // $fechaHoraInicio = DateTime::createFromFormat('Y-m-d H:i:s', $inicio); //->format('Y-m-d H:i:s');
                    $finUltiumoReal = null;

                    if ($modeloActual != $modeloAnterior || $modeloAnterior == '') {
                        if (count($planificacion) > 0) {
                            $modeloPlan = $planificacion[count($planificacion) - 2];
                            $modeloReal = $planificacion[count($planificacion) - 1];
                            $inicioOriginal = $modeloPlan['fin'];
                            $finUltiumoReal = $modeloReal['fin'];
                        }

                        // $fechaHoraParateInicio = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 15:10:00'); //->format('Y-m-d H:i:s');
                        // $fechaHoraParateFin = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 15:40:00'); //->format('Y-m-d H:i:s');
                        // $fechaHoraInicio = DateTime::createFromFormat('Y-m-d H:i:s', $inicioOriginal); //->format('Y-m-d H:i:s');

                        // if ($fechaHoraInicio >= $fechaHoraParateInicio && $fechaHoraInicio < $fechaHoraParateFin) {
                        //     Log::alert("PASO 1 - " . $modeloActual);
                        //     //Si es mayor, muevo el inicio al fin del parate
                        //     $inicioOriginal = $fechaHoraParateFin->format('Y-m-d H:i:s');

                        //     $parateInterval = $fechaHoraParateInicio->diff($fechaHoraInicio);
                        //     $parateIntervalH = $parateInterval->format('%H');
                        //     $parateIntervalM = $parateInterval->format('%i');

                        //     $horaInicio->modify("+" . $parateIntervalH . " hours");
                        //     $horaInicio->modify("+" . $parateIntervalM . " minutes");
                        // } else if ($fechaHoraInicio < $fechaHoraParateInicio && $horaInicio > $fechaHoraParateFin) {
                        //     // Log::alert("PASO 2 - " . $modeloActual);

                        //     array_push($planificacion, [
                        //         'modelo'        => $mod,
                        //         'inicio'        => $inicioOriginal,
                        //         'horaInicio'    => DateTime::createFromFormat('Y-m-d H:i:s', $inicioOriginal)->format('H:i'),
                        //         'horaFin'       => "15:40",
                        //         'fin'           => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 15:40:00')->format('Y-m-d H:i:s'),
                        //         'lectra'        => $lectra,
                        //         'duracion'      => $hours . ':' . $minutes,
                        //         'group'         => 'P-' . $lectra,
                        //         'demora'        => 0,
                        //     ]);

                        //     $inicioOriginal = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 15:40:00')->format('Y-m-d H:i:s');
                        //     $horaInicio->modify('+30 minutes');
                        // } else if ($fechaHoraInicio < $fechaHoraParateInicio && $horaInicio <= $fechaHoraParateFin) {
                        // }

                        //Lo agrego al plan
                        array_push($planificacion, [
                            'modelo'        => $mod,
                            'inicio'        => $inicioOriginal,
                            'horaInicio'    => DateTime::createFromFormat('Y-m-d H:i:s', $inicioOriginal)->format('H:i'),
                            'horaFin'       => $horaInicio->format('H:i'),
                            'fin'           => $horaInicio->format('Y-m-d H:i:s'),
                            'lectra'        => $lectra,
                            'duracion'      => $hours . ':' . $minutes,
                            'group'         => 'P-' . $lectra,
                            'demora'        => 0,
                        ]);

                        $horaRealInicio =  DateTime::createFromFormat('Y-m-d H:i:s', $inicioOriginal);
                        $horaRealInicio->modify("+" . $hours . " hours");
                        $horaRealInicio->modify("+" . $minutes . " minutes");

                        $inicioOriginal = $horaRealInicio->format('Y-m-d H:i:s');

                        if (intval($demora) > 0) {
                            $horaRealFin->modify("+" . intval($demora) . " minutes");
                            $horaInicio->modify("+" . intval($demora) . " minutes");
                            if ($demoraPorParate > 0) {
                                $horaRealFin->modify("+" . intval($demoraPorParate) . " minutes");
                                $horaInicio->modify("+" . intval($demoraPorParate) . " minutes");
                            }
                        }
                        // if ($fechaHoraInicio > $fechaHoraParateInicio && $fechaHoraInicio < $fechaHoraParateFin) {
                        //     //Si es mayor, muevo el inicio al fin del parate
                        //     $inicio = $fechaHoraParateFin->format('Y-m-d H:i:s');

                        //     $parateInterval = $fechaHoraParateInicio->diff($fechaHoraInicio);
                        //     $parateIntervalH = $parateInterval->format('%H');
                        //     $parateIntervalM = $parateInterval->format('%i');

                        //     $horaRealFin->modify("+" . $parateIntervalH . " hours");
                        //     $horaRealFin->modify("+" . $parateIntervalM . " minutes");
                        // } else if ($fechaHoraInicio < $fechaHoraParateInicio &&  $horaInicio > $fechaHoraParateFin) {

                        //     array_push($planificacion, [
                        //         'modelo'        => $mod,
                        //         'inicio'        => $inicio,
                        //         'horaInicio'    => DateTime::createFromFormat('Y-m-d H:i:s', $inicio)->format('H:i'),
                        //         'horaFin'       => "15:40",
                        //         'fin'           => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 15:10:00')->format('Y-m-d H:i:s'),
                        //         'lectra'        => $lectra,
                        //         'duracion'      => $hours . ':' . $minutes,
                        //         'group'         => 'R-' . $lectra,
                        //         'demora'        => 0,
                        //     ]);

                        //     $inicio = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 15:40:00')->format('Y-m-d H:i:s');
                        //     $horaRealFin->modify('+30 minutes');
                        // }

                        if ($finUltiumoReal) {
                            $inicio = $finUltiumoReal;
                        }

                        // if($tieneInicio && !$tieneFin){
                        //     $horaRealFin
                        // }

                        array_push($planificacion, [
                            'modelo'        => $mod,
                            'inicio'        => $inicio,
                            'horaInicio'    => DateTime::createFromFormat('Y-m-d H:i:s', $inicio)->format('H:i'),
                            'horaFin'       => $horaRealFin->format('H:i'),
                            'fin'           => $horaRealFin->format('Y-m-d H:i:s'),
                            'lectra'        => $lectra,
                            'demora'        => $demora,
                            'duracion'      => $hours . ':' . $minutes,
                            'group'         => 'R-' . $lectra
                        ]);
                    } else {
                        //Lo sumo al ultimo del plan
                        $modeloPlan = $planificacion[count($planificacion) - 2];
                        $modeloReal = $planificacion[count($planificacion) - 1];
                        $duracionPlan = explode(":", $modeloPlan['duracion']);

                        $tmpHours = $hours + $duracionPlan[0];
                        $tmpMinutes = $minutes + $duracionPlan[1];

                        if ($tmpMinutes >= 60) {
                            $tmpH = $tmpMinutes / 60;
                            $tmpHours = $tmpHours + intval($tmpH);
                            $tmpM = ($tmpH - intval($tmpH)) * 60;
                            $tmpMinutes =  $tmpM;
                        }

                        $tmpHoraFin = DateTime::createFromFormat('Y-m-d H:i:s', $modeloPlan['inicio']);
                        $tmpHoraFin->modify("+" . $tmpHours . " hours");
                        $tmpHoraFin->modify("+" . $tmpMinutes . " minutes");

                        $modeloPlan['horaFin']  = $tmpHoraFin->format('H:i');
                        $modeloPlan['fin']      = $tmpHoraFin->format('Y-m-d H:i:s');
                        $modeloPlan['duracion'] = $tmpHours . ':' . $tmpMinutes;

                        $planificacion[count($planificacion) - 2] = $modeloPlan;

                        // $tmpHoraFin = DateTime::createFromFormat('Y-m-d H:i:s', $inicio);
                        $tmpHoraFin = DateTime::createFromFormat('Y-m-d H:i:s', $modeloReal['inicio']);
                        $tmpHoraFin->modify("+" . $tmpHours . " hours");
                        $tmpHoraFin->modify("+" . $tmpMinutes . " minutes");
                        $tmpHoraFin->modify("+" . $demora . " minutes");

                        $modeloReal['horaFin']  = $tmpHoraFin->format('H:i');
                        $modeloReal['fin']      = $tmpHoraFin->format('Y-m-d H:i:s');
                        $modeloReal['demora']   = $modeloReal['demora'] + $demora;
                        $modeloReal['duracion'] = $modeloPlan['duracion'];

                        $planificacion[count($planificacion) - 1] = $modeloReal;
                    }
                    $demoraTotal = $demoraTotal + $demora;

                    $demora = 0;
                    $modeloAnterior = $modeloActual;
                    $inicio = null;
                }
            }
        }
        // Log::alert($planificacion);

        $planificacion = $this->agregaParateTimeLine($planificacion);

        return $this->setResponse($planificacion);

        // return $this->setResponse($response);
    }

    private function agregaParateTimeLine($planificacion) {
        $lectras = [1, 2, 3, 4];
        $ayer = new DateTime();
        $ayer->sub(new DateInterval('P1D'));


        foreach ($lectras as $lectra) {

            //AGREGO EL PARATE
            array_push($planificacion, [
                'modelo'        => "STOP",
                'inicio'        => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 15:10:00')->format('Y-m-d H:i:s'),
                'horaInicio'    => "15:10",
                'horaFin'       => "15:40",
                'fin'           => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 15:40:00')->format('Y-m-d H:i:s'),
                'lectra'        => $lectra,
                'duracion'      => '00:30',
                'group'         => 'P-' . $lectra,
                'demora'        => 0,
            ]);

            array_push($planificacion, [
                'modelo'        => "STOP",
                'inicio'        => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 15:10:00')->format('Y-m-d H:i:s'),
                'horaInicio'    => "15:10",
                'horaFin'       => "15:40",
                'fin'           => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 15:40:00')->format('Y-m-d H:i:s'),
                'lectra'        => $lectra,
                'duracion'      => '00:30',
                'group'         => 'R-' . $lectra,
                'demora'        => 0,
            ]);

            array_push($planificacion, [
                'modelo'        => "STOP",
                'inicio'        => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 00:50:00')->add(new DateInterval('P1D'))->format('Y-m-d H:i:s'),
                'horaInicio'    => "00:50",
                'horaFin'       => "06:00",
                'fin'           => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 06:00:00')->add(new DateInterval('P1D'))->format('Y-m-d H:i:s'),
                'lectra'        => $lectra,
                'duracion'      => '00:30',
                'group'         => 'P-' . $lectra,
                'demora'        => 0,
            ]);

            array_push($planificacion, [
                'modelo'        => "STOP",
                'inicio'        => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 00:50:00')->add(new DateInterval('P1D'))->format('Y-m-d H:i:s'),
                'horaInicio'    => "00:50",
                'horaFin'       => "06:00",
                'fin'           => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 06:00:00')->add(new DateInterval('P1D'))->format('Y-m-d H:i:s'),
                'lectra'        => $lectra,
                'duracion'      => '00:30',
                'group'         => 'R-' . $lectra,
                'demora'        => 0,
            ]);

            array_push($planificacion, [
                'modelo'        => "STOP",
                'inicio'        => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 00:50:00')->format('Y-m-d H:i:s'),
                'horaInicio'    => "00:50",
                'horaFin'       => "06:00",
                'fin'           => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 06:00:00')->format('Y-m-d H:i:s'),
                'lectra'        => $lectra,
                'duracion'      => '00:30',
                'group'         => 'P-' . $lectra,
                'demora'        => 0,
            ]);

            array_push($planificacion, [
                'modelo'        => "STOP",
                'inicio'        => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 00:50:00')->format('Y-m-d H:i:s'),
                'horaInicio'    => "00:50",
                'horaFin'       => "06:00",
                'fin'           => DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 06:00:00')->format('Y-m-d H:i:s'),
                'lectra'        => $lectra,
                'duracion'      => '00:30',
                'group'         => 'R-' . $lectra,
                'demora'        => 0,
            ]);
        }

        return $planificacion;
    }

    public function getPlanLectra($lectra) {

        $id = 1;

        $fecha = new DateTime();
        $ayer = new DateTime();

        $ayer->sub(new DateInterval('P1D'));

        // $lectraEstado = LectraEstado::with(['dado.material'])
        //     ->where('lectra', $lectra)
        //     ->where(function ($q) use ($ayer, $fecha) {
        //         $q->where('created_at', '>=', $ayer->format('Y-m-d H:i:s'));
        //         $q->where('created_at', '<=', $fecha->format('Y-m-d H:i:s'));
        //     })
        //     ->where('fin', null)
        //     ->orderBy('inicio', 'DESC')
        //     ->orderBy('id', 'ASC')
        //     ->get()->toArray();

        $ayer = new DateTime();
        $hoy = new DateTime();
        $ayer->sub(new DateInterval('P1D'));

        $lectraEstado = LectraEstado::with('dado.material')
            ->where('lectra', $lectra)
            ->where(function ($q) use ($hoy) {
                $q->where(function ($qr) {
                    $qr->where('inicio', '!=', null);
                    $qr->where('fin', null);
                });
                $q->orWhere('inicio', null);
                $q->orWhere('inicio', '>=', $hoy->format('Y-m-d') . ' 05:00:00');
            })
            ->where('created_at', '>', $ayer->format('Y-m-d') . ' 00:01:00')
            ->orderByRaw('COALESCE(inicio, GETDATE()) ASC, id asc')
            ->get();

        // Log::alert(json_encode($lectraEstado, JSON_PRETTY_PRINT));

        $dados = [];
        $datos = [];

        foreach ($lectraEstado as $dado) {
            try {

                $modelo = "";
                $esA = false;
                $esB = false;
                $esC = false;

                $inicio = null;
                $fin = null;

                $dado['dado']['inicio'] = $dado['inicio'];
                $dado['dado']['fin'] = $dado['fin'];
                $dado['dado']['idLectraEstado'] = $dado['id'];
                $dado['dado']['id'] = $dado['id'];

                $dadoActual = ModeloKanbanPadre::where('dado', $dado['dado_id'])->first();
                try {
                    $tiempos = $dadoActual->{"t_lectra" . $lectra};
                    // $tiempos = explode(":", $existe->{"t_lectra" . $lectraActual});

                    if (is_null($tiempos) || $tiempos == '') {
                        if ($lectra == 1) {
                            $tiempos = $dadoActual->{"t_lectra2"};
                        } else if ($lectra == 2) {
                            $tiempos = $dadoActual->{"t_lectra1"};
                        } else if ($lectra == 3) {
                            $tiempos = $dadoActual->{"t_lectra4"};
                        } else if ($lectra == 4) {
                            $tiempos = $dadoActual->{"t_lectra3"};
                        }

                        if (is_null($tiempos) || $tiempos == '') {
                            $tiempos = "00:00:00";
                        }
                    }
                } catch (\Throwable $th) {
                    $tiempos = "00:00:00";
                }


                array_push($dados, $dado['dado']);

                $modelo = $dado['modelo'];
                $esA = $dado['esA'];
                $esB = $dado['esB'];
                $esC = $dado['esC'];

                if (is_null($inicio)) {
                    $inicio = $dado['inicio'];
                }

                if (is_null($fin)) {
                    $fin = $dado['fin'];
                }

                array_push($datos, [
                    'duracion'          => $tiempos,
                    'modelo'            => $modelo,
                    'dados'             => $dado,
                    'id'                => $id,
                    'esA'               => boolval($esA),
                    'esB'               => boolval($esB),
                    'esCompleto'        => boolval($esC),
                    'inicio'            => $inicio,
                    'fin'               => $fin,
                ]);

                $id = $id + 1;
            } catch (\Throwable $th) {
                Log::error($th->getMessage());
            }
        }

        if ($datos) {

            return $this->setResponse($datos);
        } else {
            return $this->setResponse([]);
        }
    }

    public function getPlanificacion(Request $request) {
        $lectras = [1, 2, 3, 4];
        $id = 1;
        $response = [];

        $turno = $request->turno;
        $fecha = $request->fecha;

        $fechaActual = new DateTime();
        $ayer = DateTime::createFromFormat('d/m/Y', $fecha);
        $ayer->sub(new DateInterval('P1D'));

        $planCorte = PlanCorte::where('fecha', $fecha)->where('turno', $turno)->first();
        $planCorteAnt = PlanCorte::where('fecha', '<>', $fecha)->orderBy('id', 'DESC')->first();

        if (!$planCorte) {

            //Establezco la hora de inicio para todas las lectras
            InicioLectra::updateOrCreate(['lectra' => '1', 'fecha' => date('Y-m-d')], ['lectra' => '1', 'fecha' => date('Y-m-d'), 'hora' => '06:10']);
            InicioLectra::updateOrCreate(['lectra' => '2', 'fecha' => date('Y-m-d')], ['lectra' => '2', 'fecha' => date('Y-m-d'), 'hora' => '06:10']);
            InicioLectra::updateOrCreate(['lectra' => '3', 'fecha' => date('Y-m-d')], ['lectra' => '3', 'fecha' => date('Y-m-d'), 'hora' => '06:10']);
            InicioLectra::updateOrCreate(['lectra' => '4', 'fecha' => date('Y-m-d')], ['lectra' => '4', 'fecha' => date('Y-m-d'), 'hora' => '06:10']);

            //Verifico si estoy intentando crear un plan de la misma fecha en la que estoy actualmente
            //Tomo los dados del dia anterior, que no esten terminados y los meto en el plan actual
            if ($fecha != $fechaActual->format('d/m/Y')) {
                return $this->setResponse([], 'No existe un plan de corte con los parámetros indicados', true);
            }

            if (!$planCorteAnt) {
                return $this->setResponse([], 'No existe un plan de corte con los parámetros indicados', true);
            }

            $operacion = Str::uuid();

            $planCorte = PlanCorte::create([
                'operacion' => $operacion,
                'fecha'     => $fecha,
                'turno'     => 'TM',
            ]);



            LectraEstado::where('operacion', $planCorteAnt->operacion)
                ->where('fin', null)
                ->update([
                    'operacion'         => $planCorte->operacion,
                    'es_plan_anterior'  => true //PARA SABER QUE QUEDO DEL PLAN ANTERIOR
                ]);


            //TAMBIEN PASO LOS CORTES PENDIENTES
            $newFecha = DateTime::createFromFormat('d/m/Y', $planCorteAnt->fecha);
            LogPlanCostura::where('fecha', $newFecha->format('Y-m-d'))
                ->where('cortes_ejecutados', 0)->update(
                    ['fecha' => $fechaActual->format('Y-m-d')]
                );
        }

        $operacion = $planCorte->operacion;

        foreach ($lectras as $lectra) {
            $data = new stdClass();

            $data->lectra = $lectra;
            $datos = [];

            if ($planCorteAnt) {
                $lectraEstado = LectraEstado::with(['dado.material'])
                    ->where(function ($q) use ($operacion, $planCorteAnt) {
                        $q->where('operacion', $operacion);
                    })
                    ->where('lectra', $lectra)
                    ->orderByRaw('COALESCE(inicio, GETDATE()) ASC, id asc')
                    ->get();
            } else {
                $lectraEstado = LectraEstado::with(['dado.material'])
                    ->where(function ($q) use ($operacion) {
                        $q->where('operacion', $operacion);
                    })
                    ->where('lectra', $lectra)
                    ->orderBy('id')->get();
            }

            $dadosReposicion = DadosPieza::with([
                'material',
                'modelo',
                'pieza.modelo',
                'kanbanReemplazo.pieza',
                'kanbanReemplazo.kanban'
            ])
                ->whereIn(
                    'pieza_id',
                    $lectraEstado->where('es_reposicion', true)
                        ->pluck('pieza_id')
                        ->filter()
                        ->unique()
                        ->values()
                )
                ->get()
                ->keyBy('pieza_id');

            $dados = [];
            $modelo = "";
            $esA = false;
            $esB = false;
            $esC = false;

            $inicio = null;
            $fin = null;

            foreach ($lectraEstado as $dado) {
                $temp = []; // $dado->dado;
                $infoDado = $dado->es_reposicion
                    ? $dadosReposicion->get($dado->pieza_id)
                    : $dado->dado;

                $temp['inicio']                 = $dado->inicio;
                $temp['compartido_id']          = (!$dado->es_reposicion && !is_null($infoDado)) ? $infoDado->compartido_id : null;
                $temp['id']                     = $dado->id;
                $temp['idPapa']                 = $dado->id;
                $temp['fin']                    = $dado->fin;
                $temp['rrhh_ausentismo']        = $dado->rrhh_ausentismo;
                $temp['rrhh_rotacion']          = $dado->rrhh_rotacion;
                $temp['pr_piqueo']              = $dado->pr_piqueo;
                $temp['pr_habilidad']           = $dado->pr_habilidad;
                $temp['pr_reposicion']          = $dado->pr_reposicion;
                $temp['pr_retendido_nylon']     = $dado->pr_retendido_nylon;
                $temp['pr_falta_tendido']       = $dado->pr_falta_tendido;
                $temp['kz_setup']               = $dado->kz_setup;
                $temp['qc_defectos_proveedor']  = $dado->qc_defectos_proveedor;
                $temp['qc_problema_calidad']    = $dado->qc_problema_calidad;
                $temp['abastecido']             = $dado->abastecido;
                $temp['fin_estimado']           = $dado->fin_estimado;
                $temp['demora']                 = $dado->demora;
                $temp['id_reanudar']            = $dado->id_reanudar;
                $temp['fecha_abastecido']       = $dado->fecha_abastecido;
                $temp['pc_falta_carros']        = $dado->pc_falta_carros;
                $temp['pc_falta_material']      = $dado->pc_falta_material;
                $temp['mtto_perdida_destino']   = $dado->mtto_perdida_destino;
                $temp['mtto_cambio_cuchilla']   = $dado->mtto_cambio_cuchilla;
                $temp['mtto_falla_maquina']     = $dado->mtto_falla_maquina;
                $temp['modelo']                 = $dado->modelo;
                $temp['es_reposicion']          = boolval($dado->es_reposicion);
                $temp['esA']                    = $dado->esA;
                $temp['esB']                    = $dado->esB;
                $temp['esCompleto']             = $dado->esC;
                $temp['inicio_plan']            = $dado->inicio_plan;
                $temp['fin_plan']               = $dado->fin_plan;
                $temp['operacion']              = $operacion;
                $temp['es_plan_anterior']       = $dado->es_plan_anterior;
                $temp['material']               = !is_null($infoDado) ? $infoDado->material : null;
                $temp['t_posicionamiento']      = !is_null($infoDado) ? $infoDado->t_posicionamiento : null;
                $temp['t_lectra4']              = !is_null($infoDado) ? $infoDado->t_lectra4 : null;
                $temp['t_lectra3']              = !is_null($infoDado) ? $infoDado->t_lectra3 : null;
                $temp['t_lectra2']              = !is_null($infoDado) ? $infoDado->t_lectra2 : null;
                $temp['t_lectra1']              = !is_null($infoDado) ? $infoDado->t_lectra1 : null;
                $temp['created_at']             = !is_null($infoDado) ? $infoDado->created_at : null;
                $temp['dado']                   = !is_null($infoDado) ? $infoDado->dado : null;
                $temp['pieza_id']               = $dado->pieza_id ?? (!is_null($infoDado) ? $infoDado->pieza_id : null);
                $temp['material_id']            = !is_null($infoDado) ? $infoDado->material_id : null;
                $temp['consumo']                = !is_null($infoDado) ? $infoDado->consumo : null;
                $temp['modelo_id']              = !is_null($infoDado) ? $infoDado->modelo_id : null;
                $temp['pieza']                  = ($dado->es_reposicion && !is_null($infoDado)) ? $infoDado->pieza : null;
                $temp['kanbanReemplazo']        = ($dado->es_reposicion && !is_null($infoDado)) ? $infoDado->kanbanReemplazo : null;

                array_push($dados, $temp);
            }

            array_push($datos, [
                'modelo'        => $modelo,
                'dados'         => $dados,
                'id'            => $id,
                'esA'           => boolval($esA),
                'esB'           => boolval($esB),
                'esCompleto'    => boolval($esC),
                'inicio'        => $inicio,
                'fin'           => $fin,
                'operacion'     => $operacion
            ]);

            $data->datos = $dados;
            array_push($response, $data);
        }

        if ($response) {
            return $this->setResponse($response);
        } else {
            return $this->setResponse([]);
        }
    }

    public function getPlanificaciones() {

        $data = LectraEstado::selectRaw("operacion, FORMAT(cast(created_at as date),'dd/MM/yyyy') as fecha,FORMAT(created_at ,'HH:mm') as hora")
            ->groupBy('operacion')
            ->groupByRaw("cast(created_at as date), FORMAT(created_at ,'HH:mm')")
            ->orderByRaw("cast(created_at as date) desc, FORMAT(created_at, 'HH:mm') desc")
            ->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    private function demoraModelo($modelo, $lectra) {

        // $response = [];
        $fecha = date("d/m/Y");
        $ayer = new DateTime();

        $ayer->sub(new DateInterval('P1D'));

        $planCorte = PlanCorte::where('fecha', '>=', $ayer->format('d/m/Y'))->where('fecha', '<=', $fecha)->orderBy('fecha', 'ASC')->get();
        $startTime = "06:00:00";

        foreach ($planCorte as $plan) {
            $kanban = new stdClass();
            $demora = 0;
            $demoraPorParate = 0;

            $dado = LectraEstado::where('lectra', $lectra)->where('modelo', $modelo)->whereRaw('fin is null')
                ->where('operacion', $plan->operacion)
                ->orderBy('inicio', 'DESC')->orderBy('created_at', 'ASC')->first();

            if ($dado) {

                //SI HAY UN DADO ABIERTO, TOMO EL NUMERO DE OPERACION Y OBTENGO TODOS LOS DADOS DE LA MISMA
                $dados = LectraEstado::with(['dado'])->where('operacion', $dado->operacion)->where('lectra', $lectra)->where('modelo', $dado->modelo)->get();
                $dadosIniciados = LectraEstado::with(['dado'])->where('operacion', $dado->operacion)->where('inicio', '<>', null)->where('lectra', $lectra)->orderBy('inicio')->get();

                $time = 0;
                // $dateInit = new DateTime();
                $dateInit = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' ' . $startTime);

                if (count($dadosIniciados)) {
                    //Si ya tengo algun dado iniciado, calculo a partir del inicio, el tiempo que va a demorar todo
                    $dateEnd = new DateTime($dadosIniciados[0]->inicio);
                } else {
                    //SI todavia no inicie ningun dado, calculo desde la hora actual el tiempo estimado de demora
                    $dateEnd = new DateTime();
                }

                // $posicionamiento = null;

                foreach ($dados as $d) {
                    if ($d->dado) {
                        if ($d->dado->{"t_lectra" . $lectra}) {
                            //Obtengo los tiempos para la lectra
                            $times = explode(":", $d->dado->{"t_lectra" . $lectra});

                            for ($i = 0; $i < intval($d->dado->corte); $i++) {
                                //Sumo las horas,minutos y segundos por cada repeticion
                                $dateEnd->modify("+" . intval($times[0]) . " hours");
                                $dateEnd->modify("+" . intval($times[1]) . " minutes");
                                $dateEnd->modify("+" . intval($times[2]) . " seconds");
                            }

                            //Por cada dado verifico la demora
                            if ($d->inicio) {
                                $dateInicioDado = new DateTime($d->inicio);
                                if ($d->fin) {
                                    //El dado ya termino
                                    $dateFinDado = new DateTime($d->fin);
                                } else {
                                    //El dado se esta cortando
                                    $dateFinDado = new DateTime();
                                }

                                $interval = $dateFinDado->diff($dateInicioDado);
                                $hours = $interval->format('%H');
                                $days = $interval->format('%d');
                                $seconds = $interval->format('%s');
                                $minutes = $interval->format('%i');

                                $newDate = new DateTime();
                                $newDate2 = new DateTime();

                                $newDate->modify("+" . intval($times[0]) . " hours");
                                $newDate->modify("+" . intval($times[1]) . " minutes");
                                $newDate->modify("+" . intval($times[2]) . " seconds");

                                $interval2 = $newDate2->diff($newDate);

                                $tiempoDado = $interval->format('%d/%m/%Y %H:%i:%s');
                                $tiempoActualDado = $interval2->format('%d/%m/%Y %H:%i:%s');

                                $date1 = new DateTime($tiempoDado);
                                $date2 = new DateTime($tiempoActualDado);

                                if ($date1 > $date2) {
                                    $intervalDiff = $date2->diff($date1);
                                    $hoursd = $intervalDiff->format('%H');
                                    $minutesd = $intervalDiff->format('%i');
                                    $secondsd = $intervalDiff->format('%s');
                                    $daysd = $intervalDiff->format('%d');

                                    $hoursd = (intval($hoursd) + ($daysd * 24)) * 60;
                                    $secondsd = intval($secondsd) / 60;
                                    $demora = $demora + intval($minutesd) + $hoursd;

                                    $fechaHoraActual = new DateTime();
                                    $fechaSiguiente = new DateTime();
                                    $fechaSiguiente->add(new DateInterval('P1D'));

                                    $fechas = [
                                        'i1' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') . ' 15:10'),
                                        'f1' => DateTime::createFromFormat('d/m/Y H:i',  $fechaHoraActual->format('d/m/Y') . ' 15:40'),
                                        'i2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 00:50'),
                                        'f2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 05:59'),
                                        'i3' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') .  ' 00:50'),
                                    ];

                                    if (($dateInicioDado < $fechas['i1'] && $dateFinDado > $fechas['f1']) || ($dateInicioDado < $fechas['i1'] && ($fechaHoraActual > $fechas['i1']) && is_null($d->fin))) {
                                        //Esta dentro del parate de cambio de turno. Restar 30 min.
                                        $demora = $demora - 30;
                                        $demoraPorParate = $demoraPorParate + 30;
                                    }

                                    if (($dateInicioDado < $fechas['i3'] && $dateFinDado > $fechas['f2']) || ($dateInicioDado < $fechas['i3'] && ($fechaHoraActual > $fechas['i3']) && is_null($d->fin))) {
                                        //Esta dentro del parate de cierre. Restar 370 minutos, equivalente a 05:10 hs.
                                        $demora = $demora - 310;
                                        $demoraPorParate = $demoraPorParate + 310;
                                    }
                                }
                            } else {
                                $demora = $demora + 0;
                            }
                        }

                        //Sumo el posicionamiento
                        // if ($d->dado->t_posicionamiento && !$posicionamiento) {
                        //TODO Por ahora lo dejo fijo en 2 minutos, luego tomar desde la base
                        // $posicionamiento = "00:02:00"; //$d->dado->t_posicionamiento;
                        $dateEnd->modify("+2 minutes");
                        // }
                    }
                }

                // if ($posicionamiento) {
                //     $repes = explode(":", $posicionamiento);
                //     $dateEnd->modify("+" . intval($repes[0]) . " hours");
                //     $dateEnd->modify("+" . intval($repes[1]) . " minutes");
                //     $dateEnd->modify("+" . intval($repes[2]) . " seconds");
                // }

                $interval = $dateEnd->diff($dateInit);
                $hours = $interval->format('%H');
                $days = $interval->format('%d');
                $seconds = $interval->format('%s');
                $minutes = $interval->format('%i');

                if ($days > 0) {
                    $hours = $hours + ($days * 24);
                }

                $time = intval($seconds) * 1000;
                $time = $time + (intval($minutes) * 1000 * 60);
                $time = $time + (intval($hours) * 1000 * 60 * 60);

                $dadosIniciados = LectraEstado::with(['dado'])->where('modelo', $dado->modelo)->where('operacion', $dado->operacion)->where('inicio', '<>', null)->where('lectra', $lectra)->get();

                $kanban->dados = $dados;
                // $modelo = Modelos::where('nombre', $dado->modelo)->first();

                //OBTENGO EL TOTAL DE DADOS DEL MODELO
                // $totalDados = ModeloKanbanPadre::where('modelo_id', $modelo->id)->get()->count();
                // $inicio = LectraEstado::with(['dado'])->where('operacion', $dado->operacion)->where('inicio', '<>', null)->orderBy('inicio')->where('lectra', $lectra)->first();

                // $kanban->demora = $time;
                // $kanban->finEstimado = $dateEnd->format("H:i");
                // $kanban->demora = $demora;

                //Tengo que calcular cuanto estoy demorando por dado y en base a eso calcular si voy a tiempo o no
                //TODO Calcular si esa demora esta en el cambio de turno o en el cierre, no contabilizar
                if (count($dadosIniciados) == 0) {
                    $kanban->gap = 0;
                    $kanban->real = 0;
                } else {
                    if (intval($demora) > 0) {
                        $dateEnd->modify("+" . intval($demora) . " minutes");
                        if ($demoraPorParate > 0) {
                            $dateEnd->modify("+" . intval($demoraPorParate) . " minutes");
                        }

                        $demora_hours = 0;
                        if (intval($demora) >= 60) {
                            $demora_hours = intval($demora / 60);
                            $demora_minutes = $demora - ($demora_hours * 60);
                        } else {
                            $demora_minutes = $demora;
                        }

                        $kanban->real = $dateEnd->format("H:i");
                        $kanban->gap = sprintf("%02d", $demora_hours)  . ":" . sprintf("%02d", $demora_minutes);
                    } else {
                        $kanban->real = $dateEnd->format("H:i");
                        $kanban->gap = "0";
                    }
                }
                // $kanban->totalDados = $totalDados;
                // $kanban->dadosIniciados = $dadosIniciados;
                // $kanban->modelo = $dado->modelo;
                // $kanban->inicio = $inicio ? $inicio->inicio : null;
                // $kanban->proximo = $proximo ? $proximo->modelo : "-";
            }

            if ($kanban) {
                return ['fechaFin' => $dateEnd, 'demora' => $demora];
            }
        }
    }

    /**
     * Retorna el estado de las lectras
     */
    public function getEstadoLectras() {

        $lectras = [1, 2, 3, 4];
        $response = [];
        $fecha = new DateTime();
        $ayer = new DateTime();

        $ayer->sub(new DateInterval('P1D'));

        // $planCorte = PlanCorte::where('fecha', $fecha)->get();
        //TOMO TODO EL PLAN DESDE AYER HASTA HOY, POR SI QUEDARON CORTES SIN TERMINAR
        // $planCorte = PlanCorte::where('fecha', $fecha)->orderBy('fecha', 'ASC')->get();
        // $planCorte = PlanCorte::where('fecha', '=', $ayer->format('d/m/Y'))->orWhere('fecha', '=', $fecha->format('d/m/Y'))->orderBy('fecha', 'ASC')->get();
        $planCorte = PlanCorte::where('fecha', '>=', $ayer->format('d/m/Y'))->where('fecha', '<=', $fecha->format('d/m/Y'))->orderBy('fecha', 'ASC')->get();

        // Log::alert($planCorte);

        if (!$planCorte) {
            return $this->setResponse([
                ['lectra' => '1'],
                ['lectra' => '2'],
                ['lectra' => '3'],
                ['lectra' => '4'],
            ]);
        }

        foreach ($lectras as $lectra) {
            foreach ($planCorte as $plan) {
                $kanban = new stdClass();
                $demora = 0;
                $demoraPorParate = 0;

                $dado = LectraEstado::where('lectra', $lectra)->whereRaw('fin is null')
                    // ->where('operacion', $plan->operacion)
                    ->where(function ($q) use ($ayer, $fecha) {
                        $q->where('created_at', '>=', $ayer->format('Y-m-d H:i:s'));
                        $q->where('created_at', '<=', $fecha->format('Y-m-d H:i:s'));
                    })
                    // ->orderBy('inicio', 'DESC')->orderBy('id', 'ASC')
                    ->orderByRaw('COALESCE(inicio, GETDATE()) ASC, id asc')
                    ->first();

                // Log::alert(json_encode($dado, JSON_PRETTY_PRINT));

                if ($dado) {
                    $proximo = LectraEstado::where('lectra', $lectra)->where('fin', null)
                        ->where(function ($q) use ($dado) {
                            if (is_null($dado->modelo)) {
                                $q->where('dado_id', '<>', $dado->dado_id);
                            } else {
                                $q->where('modelo', '<>', $dado->modelo);
                                $q->orWhere('modelo', null);
                            }
                        })->orderBy('created_at')->first();

                    //SI HAY UN DADO ABIERTO, TOMO EL NUMERO DE OPERACION Y OBTENGO TODOS LOS DADOS DE LA MISMA
                    $dados = LectraEstado::with(['dado'])
                        ->where('lectra', $lectra)
                        ->where('modelo', $dado->modelo)
                        ->get();


                    $dadosIniciados = LectraEstado::with(['dado'])
                        ->where('inicio', '<>', null)
                        ->where('lectra', $lectra)
                        ->orderBy('inicio', 'DESC')->get();

                    $time = 0;
                    $dateInit = new DateTime();

                    if (count($dadosIniciados)) {
                        //Si ya tengo algun dado iniciado, calculo a partir del inicio, el tiempo que va a demorar todo
                        $dateEnd = new DateTime($dadosIniciados[0]->inicio);
                    } else {
                        //SI todavia no inicie ningun dado, calculo desde la hora actual el tiempo estimado de demora
                        $dateEnd = new DateTime();
                    }

                    foreach ($dados as $d) {
                        if ($d->dado) {
                            if ($d->dado->{"t_lectra" . $lectra}) {
                                //Obtengo los tiempos para la lectra
                                $times = explode(":", $d->dado->{"t_lectra" . $lectra});

                                for ($i = 0; $i < intval($d->dado->corte); $i++) {
                                    //Sumo las horas,minutos y segundos por cada repeticion
                                    $dateEnd->modify("+" . intval($times[0]) . " hours");
                                    $dateEnd->modify("+" . intval($times[1]) . " minutes");
                                    $dateEnd->modify("+" . intval($times[2]) . " seconds");
                                }

                                //Por cada dado verifico la demora
                                if ($d->inicio) {
                                    $dateInicioDado = new DateTime($d->inicio);
                                    if ($d->fin) {
                                        //El dado ya termino
                                        $dateFinDado = new DateTime($d->fin);
                                    } else {
                                        //El dado se esta cortando
                                        $dateFinDado = new DateTime();
                                    }

                                    $interval = $dateFinDado->diff($dateInicioDado);
                                    $hours = $interval->format('%H');
                                    $days = $interval->format('%d');
                                    $seconds = $interval->format('%s');
                                    $minutes = $interval->format('%i');

                                    $newDate = new DateTime();
                                    $newDate2 = new DateTime();

                                    $newDate->modify("+" . intval($times[0]) . " hours");
                                    $newDate->modify("+" . intval($times[1]) . " minutes");
                                    $newDate->modify("+" . intval($times[2]) . " seconds");

                                    //SUMO POSICIONAMIENTO
                                    $newDate->modify("+2 minutes");

                                    $interval2 = $newDate2->diff($newDate);

                                    $tiempoDado = $interval->format('%d/%m/%Y %H:%i:%s');
                                    $tiempoActualDado = $interval2->format('%d/%m/%Y %H:%i:%s');

                                    $date1 = new DateTime($tiempoDado);
                                    $date2 = new DateTime($tiempoActualDado);

                                    if ($date1 > $date2) {
                                        $intervalDiff = $date2->diff($date1);
                                        $hoursd = $intervalDiff->format('%H');
                                        $minutesd = $intervalDiff->format('%i');
                                        $secondsd = $intervalDiff->format('%s');
                                        $daysd = $intervalDiff->format('%d');

                                        $hoursd = (intval($hoursd) + ($daysd * 24)) * 60;
                                        $secondsd = intval($secondsd) / 60;
                                        $demora = $demora + intval($minutesd) + $hoursd;

                                        $fechaHoraActual = new DateTime();
                                        $fechaSiguiente = new DateTime();
                                        $fechaSiguiente->add(new DateInterval('P1D'));

                                        $fechas = [
                                            'i1' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') . ' 15:10'),
                                            'f1' => DateTime::createFromFormat('d/m/Y H:i',  $fechaHoraActual->format('d/m/Y') . ' 15:40'),
                                            'i2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 00:50'),
                                            'f2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 05:59'),
                                            'i3' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') .  ' 00:50'),
                                        ];

                                        if (($dateInicioDado < $fechas['i1'] && $dateFinDado > $fechas['f1']) || ($dateInicioDado < $fechas['i1'] && ($fechaHoraActual > $fechas['i1']) && is_null($d->fin))) {
                                            //Esta dentro del parate de cambio de turno. Restar 30 min.
                                            $demora = $demora - 30;
                                            if ($demora < 0) {
                                                $demora = 0;
                                            }
                                            $demoraPorParate = $demoraPorParate + 30;
                                        }

                                        if (($dateInicioDado < $fechas['i3'] && $dateFinDado < $fechas['i2']) ||  ($dateInicioDado < $fechas['i3'] && $dateFinDado > $fechas['f2']) || ($dateInicioDado < $fechas['i3'] && ($fechaHoraActual > $fechas['i3']) && is_null($d->fin))) {
                                            // if (($dateInicioDado < $fechas['i3'] && $dateFinDado > $fechas['f2']) || ($dateInicioDado < $fechas['i3'] && ($fechaHoraActual > $fechas['i3']) && is_null($d->fin))) {
                                            //Esta dentro del parate de cierre. Restar 370 minutos, equivalente a 05:10 hs.
                                            $demora = $demora - 310;
                                            if ($demora < 0) {
                                                $demora = 0;
                                            }
                                            $demoraPorParate = $demoraPorParate + 310;
                                        }
                                    }
                                } else {
                                    $demora = $demora + 0;
                                }
                            }

                            //Sumo el posicionamiento
                            $dateEnd->modify("+2 minutes");
                        }
                    }

                    $interval = $dateEnd->diff($dateInit);
                    $hours = $interval->format('%H');
                    $days = $interval->format('%d');
                    $seconds = $interval->format('%s');
                    $minutes = $interval->format('%i');

                    if ($days > 0) {
                        $hours = $hours + ($days * 24);
                    }

                    $time = intval($seconds) * 1000;
                    $time = $time + (intval($minutes) * 1000 * 60);
                    $time = $time + (intval($hours) * 1000 * 60 * 60);

                    $dadosIniciados = LectraEstado::with(['dado'])->where('modelo', $dado->modelo)->where('operacion', $dado->operacion)->where('inicio', '<>', null)->where('lectra', $lectra)->get();

                    $kanban->dados = $dados;

                    if (strpos($dado->modelo, ' - ') > 0) {
                        $compartido = ModelosCompartidos::where('name', $dado->modelo)->first();
                        if ($compartido) {
                            $totalDados = ModeloKanbanPadre::where('compartido_id', $compartido->id)->get()->count();
                        } else {
                            $totalDados = 0;
                        }
                    } else {
                        $modelo = Modelos::where('nombre', $dado->modelo)->first();
                        if (!$modelo) {
                            $totalDados = [];
                        } else {
                            //OBTENGO EL TOTAL DE DADOS DEL MODELO
                            $totalDados = ModeloKanbanPadre::where('modelo_id', $modelo->id)->get()->count();
                        }
                    }

                    $inicio = LectraEstado::with(['dado'])->where('operacion', $dado->operacion)->where('inicio', '<>', null)->orderBy('inicio')->where('lectra', $lectra)->first();

                    // $kanban->demora = $time;
                    $kanban->finEstimado = $dateEnd->format("H:i");
                    $kanban->demora = $demora;

                    //Tengo que calcular cuanto estoy demorando por dado y en base a eso calcular si voy a tiempo o no
                    //TODO Calcular si esa demora esta en el cambio de turno o en el cierre, no contabilizar
                    if (count($dadosIniciados) == 0) {
                        $kanban->gap = 0;
                        $kanban->real = 0;
                    } else {
                        if (intval($demora) > 0) {
                            $dateEnd->modify("+" . intval($demora) . " minutes");
                            //TODO REVISAR ESTO, SI AGREGO LA DEMORA POR PARATE, EL REAL SE ME DISPARA
                            // if ($demoraPorParate > 0) {
                            //     $dateEnd->modify("+" . intval($demoraPorParate) . " minutes");
                            // }

                            $demora_hours = 0;
                            if (intval($demora) >= 60) {
                                $demora_hours = intval($demora / 60);
                                $demora_minutes = $demora - ($demora_hours * 60);
                            } else {
                                $demora_minutes = $demora;
                            }

                            $kanban->real = $dateEnd->format("H:i");
                            $kanban->gap = sprintf("%02d", $demora_hours)  . ":" . sprintf("%02d", $demora_minutes);
                        } else {
                            $kanban->real = $dateEnd->format("H:i");
                            $kanban->gap = "0";
                        }
                    }

                    // Log::alert($dado->dado);
                    $kanban->totalDados = $totalDados;
                    $kanban->dadosIniciados = $dadosIniciados;

                    $kanban->modelo = $dado->modelo ? $dado->modelo : $dado->dado->dado;
                    $kanban->inicio = $inicio ? $inicio->inicio : null;
                    $kanban->proximo = $proximo ? ($proximo->modelo ? $proximo->modelo : $proximo->dado_id) : "-";
                }

                if ($kanban) {
                    $kanban->lectra = $lectra;
                    $exists = false;

                    try {
                        if ($kanban->modelo != '') {
                            $exists = false;
                        } else {
                            $exists = true;
                        }
                    } catch (\Throwable $th) {
                        $exists = true;
                    }

                    if (!$exists) {
                        foreach ($response as $res) {
                            if ($res->lectra == $lectra) {
                                try {
                                    if (count($res->dados) > 0) {
                                        $exists = true;
                                    } else {
                                        $exists = false;
                                    }
                                } catch (\Throwable $th) {
                                    $exists = true;
                                }
                            }
                        }
                    }

                    if (!$exists) {
                        array_push($response, $kanban);
                    }
                } else {
                    array_push($response, ['lectra' => $lectra]);
                }
            }
        }


        $newResponse = [];
        foreach ($lectras as $lectra) {
            $exists = false;
            foreach ($response as $res) {
                // Log::alert($res->lectra);
                if ($res->lectra == $lectra) {
                    array_push($newResponse, $res);
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                array_push($newResponse, ['lectra' => $lectra]);
            }
        }

        // Log::alert(json_encode($newResponse, JSON_PRETTY_PRINT));

        // Log::alert(json_encode($response, JSON_PRETTY_PRINT));
        return $this->setResponse($newResponse);
    }

    public function getKanbansPendienteCorte() {
        //Son los modelos pendiente de corte, ya planificados
        $kanbans = Kanbans::select(['modelos.nombre', DB::raw('count(*) as cantidad')])
            ->leftJoin('modelos', 'modelos.id', '=', 'kanbans.modelo_id')
            ->whereHas('estado', function ($query) {
                $query->where('estado_id', Estados::PLANIFICADO);
            })
            ->whereHas('modelo', function ($q) {
                $q->where('activo', true);
            })
            ->groupBy('modelos.nombre')
            ->get();

        if ($kanbans) {
            return $this->setResponse($kanbans->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    private function existeModeloCompartidoPlanificado($modeloId) {
        $existeCompartido = PcPlanProduccion::whereHas('kanban.modelo', function ($q) use ($modeloId) {
            $q->where('id', $modeloId);
        })
            ->whereHas('kanban.estado', function ($query) {
                $query->where('estado_id', Estados::EN_PLANIFICACION);
            })->first();

        if ($existeCompartido) {
            return true;
        } else {
            return false;
        }
    }

    public function getPendientesPlanificacion() {

        //Son los modelos pendientes de planificacion

        $modelos = Modelos::whereHas('lineas', function ($q) {
            $q->where('linea_id', '<>', 11);
        })->get();


        $modelosM11 = Modelos::select(['nombre as modelo'])
            ->whereHas('lineas', function ($q) {
                $q->where('linea_id', 11);
            })->get();


        foreach ($modelosM11 as $mod) {
            // Log::alert($mod);
            $modelo = Modelos::where('nombre', $mod->modelo)->first();

            $dadosA = ModeloDado::with(['dado.material'])->where('modelo_id', $modelo->id)->where('esA', true)->orderBy('ordenA')->get();
            $dadosB = ModeloDado::with(['dado.material'])->where('modelo_id', $modelo->id)->where('esB', true)->orderBy('ordenB')->get();
            $dadosC = ModeloDado::with(['dado.material'])->where('modelo_id', $modelo->id)->orderBy('ordenCompleto')->get();

            // Log::alert(json_encode($dadosA, JSON_PRETTY_PRINT));

            $mod->setsPorCarro = $modelo->cantidad;
            $mod->dadosA = $dadosA;
            $mod->dadosB = $dadosB;
            $mod->dadosC = $dadosC;

            $mod->compartidoName = null;
            $mod->compartidoDadosA = [];
            $mod->compartidoDadosB = [];
            $mod->compartidoDadosC = [];
            $mod->operacion = null;
        }

        $tempKanbans = [];

        foreach ($modelos as $k) {

            $usaCompartido = false;
            // $mod = Modelos::where('nombre', $k->modelo)->first();
            // $k->idTemp = $i;

            //TIENE MODELO COMPARTIDO
            $compartido = ModelosCompartidos::where('modelo1_id', $k->id)->orWhere('modelo2_id', $k->id)->orWhere('modelo3_id', $k->id)->first();

            $usaCompartido = $compartido ? true : false;

            if ($usaCompartido) {
                $k->compartidoName = $compartido->name;
                $k->compartidoDadosA = ModeloDado::with(['dado.material'])->whereHas('dado', function ($q) use ($compartido) {
                    $q->where('compartido_id', $compartido->id);
                })->where('esA', true)->orderBy('ordenA')->get();
                $k->compartidoDadosB = ModeloDado::with(['dado.material'])->whereHas('dado', function ($q) use ($compartido) {
                    $q->where('compartido_id', $compartido->id);
                })->where('esB', true)->orderBy('ordenB')->get();
                $k->compartidoDadosC = ModeloDado::with(['dado.material'])->whereHas('dado', function ($q) use ($compartido) {
                    $q->where('compartido_id', $compartido->id);
                })->orderBy('ordenCompleto')->get();
            } else {
                $k->compartidoName = null;
                $k->compartidoDadosA = [];
                $k->compartidoDadosB = [];
                $k->compartidoDadosC = [];
            }

            $dadosA = ModeloDado::with(['dado.material'])->where('modelo_id', $k->id)->where('esA', true)->orderBy('ordenA')->get();
            $dadosB = ModeloDado::with(['dado.material'])->where('modelo_id', $k->id)->where('esB', true)->orderBy('ordenB')->get();
            $dadosC = ModeloDado::with(['dado.material'])->where('modelo_id', $k->id)->orderBy('ordenCompleto')->get();

            $k->setsPorCarro = $k->cantidad;
            $k->dadosA = $dadosA;
            $k->dadosB = $dadosB;
            $k->dadosC = $dadosC;

            // array_push($tempKanbans, $k);
            array_push($tempKanbans, [
                'cantidad'          => 1,
                'compartidoDadosA'  => $k->compartidoDadosA,
                'compartidoDadosB'  => $k->compartidoDadosB,
                'compartidoDadosC'  => $k->compartidoDadosC,
                'compartidoName'    => $k->compartidoName,
                'dadosA'            => $k->dadosA,
                'dadosB'            => $k->dadosB,
                'dadosC'            => $k->dadosC,
                'idTemp'            => rand(0, 100),
                'modelo'            => $k->nombre,
                'operacion'         => 1,
                'orden'             => 1,
                'setsPorCarro'      => $k->setsPorCarro,
            ]);
        }

        $reposiciones = DadosPieza::with([
            'material',
            'modelo',
            'pieza.modelo',
            'kanbanReemplazo.pieza',
            'kanbanReemplazo.kanban'
        ])
            ->whereNotNull('pieza_id')
            ->whereHas('kanbanReemplazo', function ($q) {
                $q->whereNull('fecha_ingreso')
                    ->whereNull('fecha_impresion')
                    ->whereNull('fecha_plan');
            })
            ->get()
            ->map(function ($repo) {
                $modelo = $repo->modelo?->nombre ?? $repo->pieza?->modelo?->nombre ?? 'REPOSICION';

                return [
                    'cantidad'          => 1,
                    'compartidoDadosA'  => [],
                    'compartidoDadosB'  => [],
                    'compartidoDadosC'  => [],
                    'compartidoName'    => null,
                    'dadosA'            => [],
                    'dadosB'            => [],
                    'dadosC'            => [[
                        'id'            => $repo->id,
                        'modelo_id'     => $repo->modelo_id,
                        'dado_id'       => $repo->id,
                        'tipo'          => null,
                        'esA'           => false,
                        'esB'           => false,
                        'ordenA'        => 0,
                        'ordenB'        => 0,
                        'ordenCompleto' => 1,
                        'dado'          => [$repo->toArray()],
                    ]],
                    'idTemp'            => $repo->kanbanReemplazo?->id ?? $repo->id,
                    'modelo'            => $modelo,
                    'operacion'         => $repo->kanbanReemplazo?->kanban_id,
                    'orden'             => 1,
                    'setsPorCarro'      => 1,
                    'kanbanReemplazo'   => $repo->kanbanReemplazo?->toArray(),
                    'pieza'             => $repo->pieza?->toArray(),
                ];
            })
            ->values();

        $response = [
            'pendientes'    => $tempKanbans, //$kanbans ? $kanbans->toArray() : [],
            'ornament'      => ModeloKanbanPadre::with('material')->where('modelo_id', null)->where('compartido_id', null)->where('otro', false)->get()->toArray(),
            'otros'         => ModeloKanbanPadre::with('material')->where('otro', true)->get()->toArray(),
            'm11Models'     => $modelosM11 ? $modelosM11->toArray() : [], //ModeloKanbanPadre::with('material')->where('modelo_id', null)->where('compartido_id', null)->get()->toArray()
            'reposiciones'  => $reposiciones ? $reposiciones->toArray() : [],
        ];

        return $this->setResponse($response);
    }

    public function existePlanDeCorte(Request $request) {
        $planCorte = PlanCorte::where('fecha', $request->fecha)
            ->where('turno', $request->turno)
            ->first();

        return $this->setResponse($planCorte ? $planCorte->toArray() : []);
    }

    public function intercambiaPlan(Request $request) {
        //Verifico si estoy intentando crear un plan de la misma fecha en la que estoy actualmente
        //Tomo los dados del dia anterior, que no esten terminados y los meto en el plan actual

        $fechaPlan = DateTime::createFromFormat('d/m/Y', $request->fecha);
        $fechaActual = new DateTime();

        if ($fechaPlan->format('d/m/Y') != $fechaActual->format('d/m/Y')) {
            return $this->setResponse([]);
        }

        // $ayer = $fechaActual->sub(new DateInterval('P1D'));
        // Log::alert("PASO");

        //TOMO EL ÚLTIMO PLAN PLANIFICADO
        $planAyer = PlanCorte::where('fecha', '<>',  $fechaPlan->format('d/m/Y'))->orderBy('id', 'ASC')->first();
        // $planAyer = PlanCorte::where('fecha', $ayer->format('d/m/Y'))->first();

        if (!$planAyer) {
            return $this->setResponse([]);
        }

        $planHoy = PlanCorte::where('fecha', $fechaPlan->format('d/m/Y'))->first();
        if (!$planHoy) {
            $operacion = Str::uuid();
            $planHoy = PlanCorte::create([
                'operacion' => $operacion,
                'fecha'     => $request->fecha,
                'turno'     => 'TM',
            ]);
        }

        if (!$planHoy) {
            return $this->setResponse([]);
        }

        LectraEstado::where('operacion', $planAyer->operacion)->where('fin', null)
            ->where('inicio', '<>', null)
            ->update([
                'operacion' => $planHoy->operacion
            ]);

        $data = LectraEstado::where('operacion', $planHoy->operacion)->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    private function buscaEnExistente($existentes, $lectra, $dado, $modelo, $operacion, $inicio, $fin, $id) {
        $existe = null;

        foreach ($existentes as $e) {
            if ($e['lectra'] == $lectra && $e['dado_id'] == $dado && $e['modelo'] == $modelo && $e['operacion'] == $operacion && $e['inicio'] == $inicio && $e['fin'] == $fin && $e['id'] == $id) {
                $existe = $e;
                // $e->procesado = true;
                continue;
            }
        }

        return $existe;
    }

    public function setPlanCorte(Request $request) {

        DB::beginTransaction();

        $esEdit = false;
        $modelosPlanificados = $request->modelos;
        $existentes = [];

        if ($request->planificacion != '') {
            $planCorte = PlanCorte::where('fecha', $request->plan['fecha'])->where('turno', $request->plan['turno'])->first();

            $operacion = $planCorte->operacion;
            $esEdit = true;

            $existentes = LectraEstado::where('operacion', $operacion)->where('fin', null)->get()->toArray();

            $piezasReposicionExistentes = LectraEstado::where('operacion', $operacion)
                ->where('fin', null)
                ->where('es_reposicion', true)
                ->whereNotNull('pieza_id')
                ->pluck('pieza_id')
                ->filter()
                ->unique()
                ->values();

            if ($piezasReposicionExistentes->count() > 0) {
                KanbansReemplazo::whereIn('pieza_id', $piezasReposicionExistentes)
                    ->whereNull('fecha_ingreso')
                    ->whereNull('fecha_impresion')
                    ->update([
                        'fecha_plan' => null
                    ]);
            }

            PlanCorte::where('operacion', $operacion)->delete();
            LectraEstado::where('operacion', $operacion)->where('fin', null)->delete();

            $planCorte = null;
        } else {
            $operacion = Str::uuid();

            //Verifico si no hay un plan del mismo dia pero otro turno
            $planCorte = PlanCorte::where('fecha', $request->plan['fecha'])->first();

            if ($planCorte) {
                $operacion = $planCorte->operacion;
            }
        }

        if (!$planCorte) {
            $creoPlanCorte = PlanCorte::create([
                'operacion' => $operacion,
                'fecha'     => $request->plan['fecha'],
                'turno'     => $request->plan['turno'],
            ]);

            if (!$creoPlanCorte) {
                DB::rollBack();

                Log::error('LectraController::setPlanCorte : Error al crear el plan de corte');
                return $this->setResponse([], 'Ocurrió un error. Comuníquese con el encargado de sistemas', true);
            }
        }

        try {

            foreach ($request->items as $r) {

                $lectra = $r['lectra'];

                if (count($r['datos']) == 0) {
                    //Si no mando ningún dado, elimino los dados de la lectra
                    LectraEstado::where('lectra', $lectra)->where('operacion', $operacion)->where('fin', null)->delete();
                }

                $fin = null;
                foreach ($r['datos'] as $d) {
                    try {
                        $fin = $d['fin'];
                        $inicio = $d['inicio'];
                    } catch (\Throwable $th) {
                        $fin = null;
                        $inicio = null;
                    }

                    if (is_null($fin)) {
                        $modelo = $d['modelo'] == 'ORNAMENT' ? null : $d['modelo'];

                        if ($esEdit) {
                            $existe = $this->buscaEnExistente($existentes, $lectra, $d['dado'], $modelo, $operacion, $inicio, $fin, $d['id']);
                            if ($existe) {
                                $existentes = array_filter($existentes, static function ($e)  use ($existe) {
                                    return $e['id'] !== $existe['id'];
                                });
                            }
                        } else {
                            $existe = null;
                        }

                        $lectraEstado = LectraEstado::create([
                            'lectra'                    => $lectra,
                            'dado_id'                   => $d['dado'],
                            'pieza_id'                  => array_key_exists('pieza_id', $d)
                                ? $d['pieza_id']
                                : ($d['pieza']['id'] ?? ($esEdit ? ($existe['pieza_id'] ?? null) : null)),
                            'fin'                       => $esEdit ? ($existe ? $existe['fin'] : null) : null,
                            'inicio'                    => $esEdit ? ($existe ? $existe['inicio'] : null) : null,
                            'modelo'                    => $modelo,
                            'operacion'                 => $operacion,
                            'esA'                       => boolval($d['esA']),
                            'esB'                       => boolval($d['esB']),
                            'esC'                       => boolval($d['esCompleto']),
                            'es_reposicion'             => array_key_exists('es_reposicion', $d)
                                ? boolval($d['es_reposicion'])
                                : ($esEdit ? ($existe ? boolval($existe['es_reposicion']) : false) : false),
                            'abastecido'                => $esEdit ? ($existe ? $existe['abastecido'] : null) : null,
                            'rrhh_ausentismo'           => $esEdit ? ($existe ? $existe['rrhh_ausentismo'] : null) : null,
                            'rrhh_rotacion'             => $esEdit ? ($existe ? $existe['rrhh_rotacion'] : null) : null,
                            'pr_piqueo'                 => $esEdit ? ($existe ? $existe['pr_piqueo'] : null) : null,
                            'pr_habilidad'              => $esEdit ? ($existe ? $existe['pr_habilidad'] : null) : null,
                            'pr_reposicion'             => $esEdit ? ($existe ? $existe['pr_reposicion'] : null) : null,
                            'pr_retendido_nylon'        => $esEdit ? ($existe ? $existe['pr_retendido_nylon'] : null) : null,
                            'pr_falta_tendido'          => $esEdit ? ($existe ? $existe['pr_falta_tendido'] : null) : null,
                            'kz_setup'                  => $esEdit ? ($existe ? $existe['kz_setup'] : null) : null,
                            'qc_defectos_proveedor'     => $esEdit ? ($existe ? $existe['qc_defectos_proveedor'] : null) : null,
                            'qc_problema_calidad'       => $esEdit ? ($existe ? $existe['qc_problema_calidad'] : null) : null,
                            'fin_estimado'              => $esEdit ? ($existe ? $existe['fin_estimado'] : null) : null,
                            'demora'                    => $esEdit ? ($existe ? $existe['demora'] : null) : null,
                            'id_reanudar'               => $esEdit ? ($existe ? $existe['id_reanudar'] : null) : null,
                            'fecha_abastecido'          => $esEdit ? ($existe ? $existe['fecha_abastecido'] : null) : null,
                            'pc_falta_carros'           => $esEdit ? ($existe ? $existe['pc_falta_carros'] : null) : null,
                            'pc_falta_material'         => $esEdit ? ($existe ? $existe['pc_falta_material'] : null) : null,
                            'mtto_perdida_destino'      => $esEdit ? ($existe ? $existe['mtto_perdida_destino'] : null) : null,
                            'mtto_cambio_cuchilla'      => $esEdit ? ($existe ? $existe['mtto_cambio_cuchilla'] : null) : null,
                            'mtto_falla_maquina'        => $esEdit ? ($existe ? $existe['mtto_falla_maquina'] : null) : null,
                            'es_plan_anterior'          => $esEdit ? ($existe ? $existe['es_plan_anterior'] : null) : null,
                        ]);

                        if ($lectraEstado && boolval($lectraEstado->es_reposicion) && !is_null($lectraEstado->pieza_id)) {
                            $kanbanReemplazoId = $d['kanbanReemplazo']['id'] ?? null;

                            $kanbanReemplazo = KanbansReemplazo::whereNull('fecha_ingreso')
                                ->whereNull('fecha_impresion')
                                ->when(
                                    !is_null($kanbanReemplazoId),
                                    fn($q) => $q->where('id', $kanbanReemplazoId),
                                    fn($q) => $q->where('pieza_id', $lectraEstado->pieza_id)
                                )
                                ->first();

                            if ($kanbanReemplazo) {
                                $kanbanReemplazo->fecha_plan = date('Y-m-d H:i:s');
                                $kanbanReemplazo->save();
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            Log::error("LectraController::setPlanCorte : " . $th->getMessage());
        }

        foreach ($modelosPlanificados as $m) {
            if (($m['esA'] && $m['esB']) && array_key_exists('modelo', $m)) {
                if (!is_null($m['modelo'])) {

                    $data = LogPlanCostura::where('modelo', $m['modelo'])
                        ->where('fecha', date('Y-m-d'))
                        ->where('cortes_ejecutados', 0)
                        ->first();

                    if ($data) {
                        LogPlanCostura::where('id', $data->id)
                            ->update([
                                'cortes_ejecutados' =>  1
                            ]);
                    }
                }
            }
        }

        DB::commit();
        return $this->setResponse([]);
    }

    private function parseDado($dado) {
        $dadoParseado = str_replace("-", "", $dado);
        $dadoParseado = str_replace("_", "", $dadoParseado);
        $dadoParseado = str_replace("?", "", $dadoParseado);
        $dadoParseado = str_replace("\"", "", $dadoParseado);
        $dadoParseado = str_replace("'", "", $dadoParseado);

        return $dadoParseado;
    }

    public function finalizarDado(Request $request) {
        $dado = $request->dado;
        $lectraEstadoId = $request->dadoId;
        $dadoParseado = $this->parseDado($dado);

        //Verifico si existe el dado
        if (!$lectraEstadoId) {
            $existe = ModeloKanbanPadre::with(['modelo'])->whereRaw("REPLACE(REPLACE(dado,'-',''),'_','') = ?", [$dadoParseado])->first();
        } else {
            $data = LectraEstado::where('id', $lectraEstadoId)->first();
            $existe = ModeloKanbanPadre::with(['modelo'])->where('dado', $data->dado_id)->first();
            if (!$existe && $data) {
                if ($data->dado_id == FallasLectra::DEMORA_PICKEO || $data->dado_id == FallasLectra::FALLA_SISTEMA || $data->dado_id == FallasLectra::FALTA_DE_TENDIDO || $data->dado_id == FallasLectra::MANTENIMIENTO) {
                    //SI ES UN ESTADO DE ERROR, LO FINALIZO
                    LectraEstado::where('id', $lectraEstadoId)
                        ->update([
                            'fin'       => date('Y-m-d H:i:s'),
                            'demora'    => 0
                        ]);
                    return $this->setResponse([], "Dado finalizado correctamente");
                }
            }
        }

        if (!$existe) {
            return $this->setResponse([], "El dado ingresado no existe!", true);
        }

        //VERIFICO LA DEMORA ENTRE LA FECHA ACTUAL (FIN) Y LA FECHA ESTABLECIDA AL INICIO QUE DEBERÍA SER EL FIN
        // if ($lectraEstadoId) {
        //     $dataLectra = LectraEstado::where('id', $lectraEstadoId)->first();
        // } else {
        //     $dataLectra = LectraEstado::where('dado_id', $existe->dado)->first();
        // }

        if (!$lectraEstadoId) {
            $lectraEstado = LectraEstado::where('fin', null)->where('inicio', '<>', null)->where('dado_id', $existe->dado)->first();
            if ($lectraEstado) {
                $lectraEstado->fin = date('Y-m-d H:i:s');
                $lectraEstado->save();
            }

            $dataDado = LectraEstado::where('dado_id', $existe->dado)->first();
        } else {
            $lectraEstado = LectraEstado::where('id', $lectraEstadoId)->first();
            if ($lectraEstado) {
                $lectraEstado->fin = date('Y-m-d H:i:s');
                $lectraEstado->save();
            }
            $dataDado = LectraEstado::where('id', $lectraEstadoId)->first();
        }

        //Verifico si se termino el modelo. Si es asi, paso los kanbans de ese modelo (y operación) a Stock en Buffer Corte
        //Tambien chequeo si es un modelo compartido, libero todos los moelos

        //TODO revisar porque no finaliza los modelos
        $vigente = LectraEstado::where('modelo', $dataDado->modelo)->where('fin', null)->first();
        if (!$vigente) {
            $modelos = explode("-", $dataDado->modelo);
            foreach ($modelos as $modelo) {
                $kanbans = Kanbans::whereHas('estado', function ($query) {
                    $query->where('estado_id', Estados::PLANIFICADO);
                })
                    ->whereHas('modelo', function ($q) use ($modelo) {
                        $q->where('nombre', $modelo);
                    })
                    ->where('operacion', $dataDado->operacion)
                    ->get();

                foreach ($kanbans as $kanban) {
                    Kanban::changeStatus($kanban, Estados::EN_BUFFER_CORTE);
                }
            }
        }

        return $this->setResponse([], "Dado finalizado correctamente");
    }

    public function verificarEstadoFrenoLectra(Request $request) {
        $lectra = $request->lectra;

        $frenoActivo = LectraEstado::where('lectra', $lectra)->where('fin', null)->where('inicio', '<>', null)
            ->where(function ($q) {
                $q->where('dado_id', 'FALTA DE TENDIDO');
                $q->orWhere('dado_id', 'DEMORA PICKEO');
                $q->orWhere('dado_id', 'MANTENIMIENTO');
                $q->orWhere('dado_id', 'FALLA SISTEMA');
            })
            ->first();

        // Log::alert($frenoActivo);
        if ($frenoActivo) {
            return $this->setResponse($frenoActivo->toArray());
        }

        return $this->setResponse([]);
    }

    public function iniciarDado(Request $request) {

        $creo = false;
        $dado = $request->dado;
        $lectraEstadoId = $request->dadoId;
        $lectra = $request->lectra;
        $dadoParseado = $this->parseDado($dado);

        // Log::alert($request);
        //Verifico si existe el dado

        if ($dado == FallasLectra::FALTA_DE_TENDIDO || $dado == FallasLectra::DEMORA_PICKEO || $dado == FallasLectra::FALLA_SISTEMA || $dado == FallasLectra::MANTENIMIENTO) {

            $idReanudar = null;
            $corteExistente = LectraEstado::where('lectra', $lectra)->where('inicio', '<>', null)->where('fin', null)->first();
            if ($corteExistente) {
                $idReanudar = $corteExistente->id;

                $lectraEstado = LectraEstado::where('id', $corteExistente->id)->first();
                if ($lectraEstado) {
                    $lectraEstado->fin = date('Y-m-d H:i:s');
                    $lectraEstado->save();
                }
                //Finalizo el corte actual
                // LectraEstado::where('id', $corteExistente->id)->update(['fin' => date('Y-m-d H:i:s')]);
            }

            LectraEstado::create([
                'lectra'        => $lectra,
                'fin'           => null,
                'inicio'        => date('Y-m-d H:i:s'),
                'modelo'        => null,
                'operacion'     => null,
                'dado_id'       => $dado,
                'pieza_id'      => null,
                'fin_estimado'  => null,
                'id_reanudar'   => $idReanudar,
                'es_reposicion' => false,
            ]);

            //CREO UNA COPIA DEL CORTE QUE FINALIZO
            if ($idReanudar > 0) {
                LectraEstado::create([
                    'lectra'        => $lectra,
                    'fin'           => null,
                    'inicio'        => null,
                    'modelo'        => $corteExistente->modelo,
                    'operacion'     => $corteExistente->operacion,
                    'dado_id'       => $corteExistente->dado_id,
                    'pieza_id'      => $corteExistente->pieza_id,
                    'fin_estimado'  => null,
                    'esA'           => $corteExistente->esA,
                    'esB'           => $corteExistente->esB,
                    'esC'           => $corteExistente->esC,
                    'es_reposicion' => $corteExistente->es_reposicion,
                    'abastecido'    => $corteExistente->abastecido,
                ]);
            }

            return $this->setResponse([], "Frenada iniciada correctamente");
        }

        if (!$lectraEstadoId) {
            $existe = false; //ModeloKanbanPadre::with(['modelo'])->whereRaw("REPLACE(REPLACE(dado,'-',''),'_','') = ?", [$dadoParseado])->first();
            if (!$existe) {
                /* Por problema en lectura de dados, se modifica la manera de leerlo. Ahora tomo el modelo y el codigo de material */
                $dadoTemp = str_replace("'", "-", $dado);
                $dadoTemp = str_replace("?", "_", $dadoTemp);
                $datosDado = explode("-", $dadoTemp);

                if (count($datosDado) == 0) {
                    return $this->setResponse([], 'Dado incorrecto', true);
                }
                $material = $datosDado[count($datosDado) - 3];

                //Separo el dado por _ para obtener los modelos
                $datosDadoModelos = explode("_", $dadoTemp);
                if (count($datosDadoModelos) == 0) {
                    $modelos = [$datosDado[0]];
                } else {
                    $modelos = explode("-", $datosDadoModelos[0]);
                }

                $existe = ModeloKanbanPadre::with(['modelo'])
                    ->whereHas('modelo', function ($q) use ($modelos) {
                        $q->whereIn('nombre', $modelos);
                    })
                    ->whereRaw("dado like '%-" . $material . "-%'")
                    ->first();
            }
        } else {
            $data = LectraEstado::where('id', $lectraEstadoId)->first();
            // Log::alert($data);
            if ($data) {
                $existe = ModeloKanbanPadre::with(['modelo'])->where('dado', $data->dado_id)->first();
            } else {
                $existe = false;
            }
        }

        if (!$existe) {
            if (substr(strtoupper($request->dado), 0, 2) == "I6") {
                $dado = substr($dadoParseado, 2);
                $existe = ModeloKanbanPadre::with(['modelo'])->whereRaw("REPLACE(REPLACE(dado,'-',''),'_','') = ?", [$dado])->first();
            } else {
                //Verifico si existe con el I6 delante
                $dado = "I6" . $dadoParseado;
                // $dado = "I6_" . $request->dado;
                $existe = ModeloKanbanPadre::with(['modelo'])->whereRaw("REPLACE(REPLACE(dado,'-',''),'_','') = ?", [$dado])->first();
            }
        }

        if (!$existe) {
            //SI EL DADO NO EXISTE, PUEDE SER UNA REPOSICIÓN O UN DADO INEXISTENTE EN SISTEMA.
            //DEBERÍA CREARLO CON UN TIEMPO FICTICIO PARA QUE NO FRENE EL PROCESO. APROX 5 MIN

            //VERIFICO SI EXISTE EL MODELO REPO, SI NO, LO CREO
            // $mod = Modelos::where('nombre', 'REPO')->first();
            // if (!$mod) {
            //     $mod = Modelos::create([
            //         'nombre'    => 'REPO',
            //         'codigo'    => 'REPO'
            //     ]);
            // }

            // $existe = ModeloKanbanPadre::create([
            //     'consumo'           => 1,
            //     'corte'             => 1,
            //     'dado'              => $dado,
            //     't_lectra1'         => '00:05:00',
            //     't_lectra2'         => '00:05:00',
            //     't_lectra3'         => '00:05:00',
            //     't_lectra4'         => '00:05:00',
            //     't_posicionamiento' => '00:01:00',
            //     'modelo_id'         => $mod->id,
            //     'material_id'       => null
            // ]);
            // $creo = true;

            // $existe = ModeloKanbanPadre::with(['modelo'])->where('id', $existe->id)->first();

            // Log::alert("NO EXISTE EL DADO");
            //POR AHORA SI NO EXISTE NO HAGO NADA
            return $this->setResponse([], "El dado ingresado no existe!", true);
        }

        //Verifico si esta planeado, 
        if ($lectraEstadoId) {
            $datos = LectraEstado::where('id', $lectraEstadoId)->first();
        } else {
            // Log::alert("PASO");
            if ($creo) {
                $datos = null;
            } else {
                // Log::alert($existe->modelo);
                if (!$existe->modelo) {
                    $datos = LectraEstado::where('lectra', $lectra)
                        ->where('fin', null)
                        ->whereRaw("REPLACE(REPLACE(dado_id,'-',''),'_','') = ?", [$dadoParseado])
                        ->first();
                } else {
                    $datos = LectraEstado::where('modelo', $existe->modelo->nombre)
                        ->where('lectra', $lectra)
                        ->where('fin', null)
                        ->where(function ($q) use ($dadoParseado, $material) {
                            $q->whereRaw("REPLACE(REPLACE(dado_id,'-',''),'_','') = ?", [$dadoParseado]);
                            $q->orWhereRaw("dado_id like '%-" . $material . "-%'");
                        })
                        ->first();
                }
            }
        }


        if ($datos) {

            if ($datos->inicio && !$datos->fin) {
                return $this->setResponse([], "El dado se encuentra en corte", true);
            }

            $lectraEstado = LectraEstado::where('fin', null)->where('inicio', '!=', null)->where('lectra', $datos->lectra)->first();
            if ($lectraEstado) {
                $lectraEstado->fin = date('Y-m-d H:i:s');
                $lectraEstado->save();
            }

            $fechaEstimadaFin = new DateTime();

            try {
                $tiempos = $this->getTiempoLectraSiNoExiste($datos->lectra, $existe);

                $inicioCarbon = Carbon::parse($datos->inicio);

                $fechaEstimadaFin = $this->sumarDuracionExcluyendoLapsos($inicioCarbon, (intval($tiempos[0]) * 60) + intval($tiempos[1]));

                // if (is_array($tiempos)) {
                //     if (count($tiempos) > 0) {
                //         $fechaEstimadaFin->modify('+' . $tiempos[0] . ' hours');
                //         $fechaEstimadaFin->modify('+' . $tiempos[1] . ' minutes');
                //         $fechaEstimadaFin->modify('+' . $tiempos[2] . ' seconds');
                //     }
                // }
                // Log::alert($tiempos);
            } catch (\Throwable $th) {
                Log::alert("ERROR AL INICIAR DADO, FIN ESTIMADO : " . $th->getMessage());
            }

            //Inicio el dado actual y actualizo en la lectra que esta solicitandolo
            LectraEstado::where('id', $datos->id)
                ->update([
                    'inicio'        => date('Y-m-d H:i:s'),
                    'fin_estimado'  => $fechaEstimadaFin->format('Y-m-d H:i:s'),
                    'lectra'        => $lectra
                ]);



            return $this->setResponse([], "Dado iniciado correctamente");
        }

        //Finalizo los dados abiertos de la lectra
        //Busco el dado abierto y actualizo la demora

        $lectraEstado = LectraEstado::where('fin', null)->where('inicio', '<>', null)->where('lectra', $datos->lectra)->first();
        if ($lectraEstado) {
            $lectraEstado->fin = date('Y-m-d H:i:s');
            $lectraEstado->save();
        }

        //Si no esta planeado, planear
        //Obtengo la operacion del plan actual
        $planCorte = PlanCorte::where('fecha', date('d/m/Y'))->first();
        if (!$planCorte) {
            //Si no hay plan del día, lo creo
            $operacion = Str::uuid();
            PlanCorte::create([
                'operacion' => $operacion,
                'fecha'     => date('d/m/Y'),
                'turno'     => 'TM',
            ]);
        } else {
            $operacion = $planCorte->operacion;
        }

        $fechaEstimadaFin = new DateTime();
        //Busco la demora del dado y lo agrego a la fecha actual para determinar el fin esperado
        try {
            $tiempos = $this->getTiempoLectraSiNoExiste($datos->lectra, $existe);
            // $fechaEstimadaFin = $this->sumarTiempo($fechaEstimadaFin->format('Y-m-d H:i:s'), $tiempos[0], $tiempos[1], $tiempos[2]);

            $inicioCarbon = Carbon::parse($datos->inicio);
            $fechaEstimadaFin = $this->sumarDuracionExcluyendoLapsos($inicioCarbon, (intval($tiempos[0]) * 60) + intval($tiempos[1]));
        } catch (\Throwable $th) {
            Log::alert("ERROR AL INICIAR DADO, FIN ESTIMADO : " . $th->getMessage());
        }

        LectraEstado::create([
            'lectra'        => $lectra,
            'fin'           => null,
            'inicio'        => date('Y-m-d H:i:s'),
            'modelo'        => $creo ? 'REPO' : $existe->modelo->nombre,
            'operacion'     => $operacion,
            'dado_id'       => $existe->dado,
            'pieza_id'      => null,
            'fin_estimado'  => $fechaEstimadaFin->format('Y-m-d H:i:s'),
            'es_reposicion' => false,
        ]);
        return $this->setResponse([], "Dado iniciado correctamente");
    }

    private function sumarDuracionExcluyendoLapsos(Carbon $inicio, int $duracionMinutos): Carbon {
        $actual = $inicio->copy();
        $minutosSumados = 0;

        // Definimos los intervalos a excluir
        $lapsos = [
            ['00:50', '06:00'],
            ['15:10', '15:40'],
        ];

        while ($minutosSumados < $duracionMinutos) {
            $hora = $actual->format('H:i');
            $dentroDeLapso = false;

            foreach ($lapsos as [$desde, $hasta]) {
                if ($hora >= $desde && $hora < $hasta) {
                    $dentroDeLapso = true;
                    break;
                }
            }

            if (!$dentroDeLapso) {
                $minutosSumados++;
            }

            $actual->addMinute();
        }

        return $actual;
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

    public function datosAbastecimiento() {
        $response = [];

        $lectras = [1, 2, 3, 4];
        // $dados = LectraEstado::with('dado.material')->where('fin', null)->where('inicio', null)->orderBy('id')->get();

        foreach ($lectras as $lectra) {

            $demora = 0;
            $demoraPorParate = 0;
            $dados = LectraEstado::with('dado.material')->where('fin', null)->where('inicio', null)->where('lectra', $lectra)->orderBy('id')->get();

            //SI HAY UN DADO ABIERTO, TOMO EL NUMERO DE OPERACION Y OBTENGO TODOS LOS DADOS DE LA MISMA
            $dadosIniciados = LectraEstado::with(['dado'])->where('inicio', '<>', null)->where('lectra', $lectra)->orderBy('inicio')->get();

            if (count($dadosIniciados)) {
                //Si ya tengo algun dado iniciado, calculo a partir del inicio, el tiempo que va a demorar todo
                $dateEnd = new DateTime($dadosIniciados[0]->inicio);
                $primero = true;

                //LE SUMO TODOS LOS DADOS INICIADOS
                foreach ($dadosIniciados as $iniciado) {
                    if ($primero) {
                        $primero = false;
                    } else {
                        if ($iniciado->dado->{"t_lectra" . $lectra}) {
                            //Obtengo los tiempos para la lectra
                            $times = explode(":", $iniciado->dado->{"t_lectra" . $lectra});

                            for ($i = 0; $i < intval($iniciado->dado->corte); $i++) {
                                //Sumo las horas,minutos y segundos por cada repeticion
                                $dateEnd->modify("+" . intval($times[0]) . " hours");
                                $dateEnd->modify("+" . intval($times[1]) . " minutes");
                                $dateEnd->modify("+" . intval($times[2]) . " seconds");
                            }
                        }
                    }
                }
            } else {
                //SI todavia no inicie ningun dado, calculo desde la hora actual el tiempo estimado de demora
                $dateEnd = new DateTime();
            }

            foreach ($dados as $d) {
                // $posicionamiento = null;

                if ($d->dado) {
                    if ($d->dado->{"t_lectra" . $lectra}) {
                        //Obtengo los tiempos para la lectra
                        $times = explode(":", $d->dado->{"t_lectra" . $lectra});

                        for ($i = 0; $i < intval($d->dado->corte); $i++) {
                            //Sumo las horas,minutos y segundos por cada repeticion
                            $dateEnd->modify("+" . intval($times[0]) . " hours");
                            $dateEnd->modify("+" . intval($times[1]) . " minutes");
                            $dateEnd->modify("+" . intval($times[2]) . " seconds");
                        }

                        //Por cada dado verifico la demora
                        if ($d->inicio) {
                            $dateInicioDado = new DateTime($d->inicio);
                            if ($d->fin) {
                                //El dado ya termino
                                $dateFinDado = new DateTime($d->fin);
                            } else {
                                //El dado se esta cortando
                                $dateFinDado = new DateTime();
                            }

                            $interval = $dateFinDado->diff($dateInicioDado);

                            $newDate = new DateTime();
                            $newDate2 = new DateTime();

                            $newDate->modify("+" . intval($times[0]) . " hours");
                            $newDate->modify("+" . intval($times[1]) . " minutes");
                            $newDate->modify("+" . intval($times[2]) . " seconds");

                            $interval2 = $newDate2->diff($newDate);

                            $tiempoDado = $interval->format('%d/%m/%Y %H:%i:%s');
                            $tiempoActualDado = $interval2->format('%d/%m/%Y %H:%i:%s');

                            $date1 = new DateTime($tiempoDado);
                            $date2 = new DateTime($tiempoActualDado);

                            if ($date1 > $date2) {
                                $intervalDiff = $date2->diff($date1);
                                $hoursd = $intervalDiff->format('%H');
                                $minutesd = $intervalDiff->format('%i');
                                $secondsd = $intervalDiff->format('%s');
                                $daysd = $intervalDiff->format('%d');

                                $hoursd = (intval($hoursd) + ($daysd * 24)) * 60;
                                $secondsd = intval($secondsd) / 60;
                                $demora = $demora + intval($minutesd) + $hoursd;

                                $fechaHoraActual = new DateTime();
                                $fechaSiguiente = new DateTime();
                                $fechaSiguiente->add(new DateInterval('P1D'));

                                $fechas = [
                                    'i1' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') . ' 15:10'),
                                    'f1' => DateTime::createFromFormat('d/m/Y H:i',  $fechaHoraActual->format('d/m/Y') . ' 15:40'),
                                    'i2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 00:50'),
                                    'f2' => DateTime::createFromFormat('d/m/Y H:i', $fechaSiguiente->format('d/m/Y') .  ' 05:59'),
                                    'i3' => DateTime::createFromFormat('d/m/Y H:i', $fechaHoraActual->format('d/m/Y') .  ' 00:50'),
                                ];

                                if (($dateInicioDado < $fechas['i1'] && $dateFinDado > $fechas['f1']) || ($dateInicioDado < $fechas['i1'] && ($fechaHoraActual > $fechas['i1']) && is_null($d->fin))) {
                                    //Esta dentro del parate de cambio de turno. Restar 30 min.
                                    $demora = $demora - 30;
                                    $demoraPorParate = $demoraPorParate + 30;
                                }

                                if (($dateInicioDado < $fechas['i3'] && $dateFinDado > $fechas['f2']) || ($dateInicioDado < $fechas['i3'] && ($fechaHoraActual > $fechas['i3']) && is_null($d->fin))) {
                                    //Esta dentro del parate de cierre. Restar 370 minutos, equivalente a 05:10 hs.
                                    $demora = $demora - 310;
                                    $demoraPorParate = $demoraPorParate + 310;
                                }
                            }
                        } else {
                            $demora = $demora + 0;
                        }

                        $dateEnd->modify("+2 minutes");
                        // if ($d->dado->t_posicionamiento) {
                        //     $repes = explode(":", $d->dado->t_posicionamiento);
                        //     $dateEnd->modify("-" . intval($repes[0]) . " hours");
                        //     $dateEnd->modify("-" . intval($repes[1]) . " minutes");
                        //     $dateEnd->modify("-" . intval($repes[2]) . " seconds");
                        // }

                        if (intval($demora) > 0) {
                            $dateEnd->modify("+" . intval($demora) . " minutes");
                            if ($demoraPorParate > 0) {
                                $dateEnd->modify("+" . intval($demoraPorParate) . " minutes");
                            }

                            $demora_hours = 0;
                            if (intval($demora) >= 60) {
                                $demora_hours = intval($demora / 60);
                                $demora_minutes = $demora - ($demora_hours * 60);
                            } else {
                                $demora_minutes = $demora;
                            }

                            $d->real = $dateEnd->format("H:i");
                            // $kanban->gap = sprintf("%02d", $demora_hours)  . ":" . sprintf("%02d", $demora_minutes);
                        } else {
                            $d->real = $dateEnd->format("H:i");
                            // $kanban->gap = "0";
                        }
                    }

                    //Sumo el posicionamiento
                    // if ($d->dado->t_posicionamiento && !$posicionamiento) {
                    //     $posicionamiento = $d->dado->t_posicionamiento;
                    // }

                    // $dado->inicio_estimado = 
                }
            }

            $response = array_merge($response, $dados->toArray());


            // Log::alert($dados->toArray());
        }

        // Log::alert($response);
        if ($response) {
            return $this->setResponse($response);
        } else {
            return $this->setResponse([]);
        }
    }

    public function abastecerDado($dadoId, $porModelo) {

        $existe = LectraEstado::where('id', $dadoId)->first();

        try {
            if ($porModelo == 1) {
                if ($existe) {
                    //SOLO ABASTECER, NO DESABASTECE
                    if (!is_null($existe->modelo)) {
                        LectraEstado::where('modelo', $existe->modelo)
                            ->where('operacion', $existe->operacion)
                            ->where('lectra', $existe->lectra)
                            ->where(function ($q) {
                                $q->where('abastecido', false);
                                $q->orWhere('abastecido', null);
                            })
                            ->update([
                                'abastecido'        => true,
                                'fecha_abastecido'  => date('Y-m-d H:i:s')
                            ]);

                        //GENERO EL LOG DE DADOS ABASTECIDO
                        LogAbastecimiento::create([
                            'modelo'    => $existe->modelo,
                            'dado'      => '',
                            'accion'    => 'MODELO ABASTECIDO',
                            'lectra'    => intval($existe->lectra)
                        ]);
                    }
                }
            } else {
                if ($existe) {
                    $abastecido = $existe->abastecido == "1" ? false : true;
                    LectraEstado::where('id', $dadoId)->update(['abastecido' => $abastecido, 'fecha_abastecido' => ($abastecido ? date('Y-m-d H:i:s') : null)]);

                    LogAbastecimiento::create([
                        'modelo'    => $existe->modelo,
                        'dado'      => $existe->dado_id,
                        'accion'    => ($abastecido ? "DADO ABASTECIDO" : "DADO DESABASTECIDO"),
                        'lectra'    => intval($existe->lectra)
                    ]);
                } else {
                    // LogAbastecimiento::create([
                    //     'modelo'    => $existe->modelo,
                    //     'dado'      => $existe->dado_id,
                    //     'accion'    =>  "DADO ABASTECIDO",
                    // ]);

                    LectraEstado::where('id', $dadoId)->update(['abastecido' => true, 'fecha_abastecido' => date('Y-m-d H:i:s')]);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        return $this->setResponse([]);
    }

    /**
     * Valida un dado contra una etiqueta QR de cono
     */
    public function validaDadoEtiquetaCono(Request $request) {

        $dadoEscaneado = $request->dado;
        $kanbanEscaneado = $request->kanban;

        $kanban = TKanbanMaterial::where('ID', $kanbanEscaneado)->first();
        // Log::alert($kanban);

        if (!$kanban) {
            return $this->setResponse([], 'Kanban incorrecto o inexistente', true);
        }

        $dadoTemp = str_replace("'", "-", $dadoEscaneado);
        $dadoTemp = str_replace("?", "_", $dadoTemp);
        $datosDado = explode("-", $dadoTemp);

        if (count($datosDado) == 0) {
            return $this->setResponse([], 'Dado incorrecto', true);
        }

        try {
            $material = $datosDado[count($datosDado) - 3];
            $coincidencia = strtoupper(trim($material)) == strtoupper(trim($kanban->CODIGO_INT));

            if ($coincidencia) {
                return $this->setResponse([], 'Material OK', false);
            } else {
                return $this->setResponse(['material_dado' => $material, 'material_kanban' => $kanban->CODIGO_INT], 'Material incorrecto', true);
            }
        } catch (\Throwable $th) {
            return $this->setResponse([], 'Material o dado incorrecto', true);

            //throw $th;
        }
    }
}
