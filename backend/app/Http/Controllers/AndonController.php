<?php

namespace App\Http\Controllers;

use App\Models\AndonLineas;
use App\Models\AusentismoDiario;
use App\Models\EpEtiqueta;
use App\Models\FallasInformadas;
use App\Models\PlanLinea;
use App\Models\TmpPlanLinea;
use DateInterval;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AndonController extends Controller {

    private function recorrerAndon($fecha) {
        $lineas = ['1', '2', '3', '4'];
        $difAcumulada = 0;
        $turno = null;
        $andon = 0;
        $turnoAnt = null;
        $cantidadAcumulada = 0;

        foreach ($lineas as $linea) {
            //Obtengo el plan de la linea
            $plans = PlanLinea::where('linea_id', $linea)->orderBy('orden', 'ASC')->get();

            //Recorro el plan de la linea
            foreach ($plans as $plan) {
                $turno = $plan->turno;

                if ($turno != $turnoAnt && !is_null($turnoAnt)) {
                    $difAcumulada = 0;
                    $cantidadAcumulada = 0;
                }

                $fechaDesde = DateTime::createFromFormat('Y-m-d H:i:s', $fecha->format('Y-m-d') . ' ' . substr($plan->hora_desde, 0, 8));
                $fechaHasta = DateTime::createFromFormat('Y-m-d H:i:s', $fecha->format('Y-m-d') . ' ' . substr($plan->hora_hasta, 0, 8));

                if ($plan->hora_desde == '00:00:00.0000000') {
                    $fechaDesde->add(new DateInterval('P1D'));
                    $fechaHasta->add(new DateInterval('P1D'));
                }

                if ($linea == 1) {
                    //Obtengo las fundas escaneadas en el rango horario del plan
                    $cantidad = EpEtiqueta::select('id')->where('linea_real', $linea)
                        ->whereBetween('hora_validacion_carab', [$fechaDesde->format('Y-m-d H:i:s'), $fechaHasta->format('Y-m-d H:i:s')])
                        ->count();
                } else {
                    //CUANDO NO ES LA M1 NO VALIDAN CARA B
                    $cantidad = 0;
                }

                if ($cantidad == 0) {
                    $cantidad = EpEtiqueta::select('id')->where('linea_real', $linea)
                        ->whereBetween('hora_validacion', [$fechaDesde->format('Y-m-d H:i:s'), $fechaHasta->format('Y-m-d H:i:s')])
                        ->count();

                    $cantidadComponentes = EpEtiqueta::selectRaw('distinct(lado + tipo)')
                        ->where('linea_real', $linea)
                        ->whereBetween('hora_validacion', [$fechaDesde->format('Y-m-d H:i:s'), $fechaHasta->format('Y-m-d H:i:s')])
                        ->get();
                } else {
                    $cantidadComponentes = EpEtiqueta::selectRaw('distinct(lado + tipo)')
                        ->where('linea_real', $linea)
                        ->whereBetween('hora_validacion_carab', [$fechaDesde->format('Y-m-d H:i:s'), $fechaHasta->format('Y-m-d H:i:s')])
                        ->get();
                }

                if ($cantidadComponentes->count() > 0) {
                    $cantidad = ceil($cantidad / $cantidadComponentes->count());
                } else {
                    $cantidad = $cantidad / 2;
                }

                $cantidadAcumulada = $cantidadAcumulada + $cantidad;

                $diferencia = intval($cantidad) - intval($plan->plan);
                $difAcumulada = $difAcumulada + $diferencia;

                if ($cantidad == 0) {
                    $andon = 0;
                } else {

                    if (intval($plan->plan_acumulado) > 0) {
                        $andon =  (($cantidadAcumulada) / intval($plan->plan_acumulado));
                    } else {
                        $andon = 0;
                    }
                }

                TmpPlanLinea::create([
                    'linea_id'              => $linea,
                    'cantidad'              => $cantidad,
                    'fecha'                 => $fecha,
                    'orden'                 => $plan->orden,
                    'diferencia'            => $diferencia,
                    'diferencia_acumulada'  => $difAcumulada,
                    'andon_hora'            => $andon
                    // 'diferencia_acumulada'  => ($fechaDesde < $fecha) ? $difAcumulada : 0
                ]);


                $turnoAnt = $plan->turno;
            }
        }
    }

    public function actualizarAndon() {

        //Reorro las lineas y actualizo el hora a hora
        $fecha = new DateTime();
        $fecha->sub(new DateInterval('P1D'));

        //PRIMERO LA FECHA ANTERIOR
        TmpPlanLinea::where('fecha', $fecha->format('Y-m-d'))->delete();

        $this->recorrerAndon($fecha);

        //AHORA LA FECHA ACTUAL
        $nFecha = new DateTime();
        TmpPlanLinea::where('fecha', $nFecha->format('Y-m-d'))->delete();

        $this->recorrerAndon($nFecha);
    }

    public function obtenerAndonActualLinea() {

        $fecha = date('Y-m-d');
        $data = AndonLineas::where('fecha', $fecha)
            ->orderBy('linea_id')
            ->orderBy('orden')
            ->get();

        return $this->setResponse($data ? $data->toArray() : []);
    }

    public function armarAndonDefectosLineaHoraAHora($linea) {

        // $dataTurno = obtenerTurnoActual();

        $fecha = new DateTime();
        $horaActual = intval($fecha->format('H'));
        $minutoActual = intval($fecha->format('i'));

        if (($horaActual > 15 && $minutoActual > 10) || ($horaActual >= 0 && $horaActual < 2)) {
            $dataTurno = [
                'TURNO' => 'TT',
                'SIGLA' => 'T'
            ];
        } else {
            $dataTurno = [
                'TURNO' => 'TM',
                'SIGLA' => 'M'
            ];
        }

        $planLinea = PlanLinea::where('linea_id', intval($linea))->where('turno', $dataTurno['SIGLA'])->orderBy('orden', 'ASC')->get();

        if (!$planLinea) {
            return $this->setResponse([], 'No hay plan para la linea ' . $linea, true);
        }

        $inicioTurno = new DateTime();
        $finTurno = new DateTime();

        foreach ($planLinea as $hora) {

            $tiempoDesde = explode(":", $hora->hora_desde);
            $tiempoHasta = explode(":", $hora->hora_hasta);

            if ($dataTurno['TURNO'] == 'TM') {
                $inicioTurno->setTime($tiempoDesde[0], $tiempoDesde[1], explode('.', $tiempoDesde[2])[0]);
                $finTurno->setTime($tiempoHasta[0], $tiempoHasta[1], explode('.', $tiempoHasta[2])[0]);
            } else {
                if (intval($hora->hora_desde) == 0) {
                    $inicioTurno = new DateTime();
                    $finTurno = new DateTime();

                    $inicioTurno->add(new DateInterval('P1D'));
                    $finTurno->add(new DateInterval('P1D'));

                    $inicioTurno->setTime($tiempoDesde[0], $tiempoDesde[1], $tiempoDesde[2]);
                    $finTurno->setTime($tiempoHasta[0], $tiempoHasta[1], $tiempoHasta[2]);
                } else {
                    $inicioTurno->setTime($tiempoDesde[0], $tiempoDesde[1], explode('.', $tiempoDesde[2])[0]);
                    $finTurno->setTime($tiempoHasta[0], $tiempoHasta[1], explode('.', $tiempoHasta[2])[0]);
                }
            }

            $defectos = FallasInformadas::selectRaw('COUNT(*) as cantidad, tipo_falla')
                ->where('linea', intval($linea))
                ->whereBetween('created_at', [$inicioTurno->format('Y-m-d H:i:s'), $finTurno->format('Y-m-d H:i:s')])
                ->groupBy('tipo_falla')
                ->get();

            $hora->defectos = $defectos;
        }

        return $this->setResponse($planLinea->toArray());
    }
}
