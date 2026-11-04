<?php

use App\Models\FallasInformadas;
use App\Models\Turnos;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

if (!function_exists('formatear_fecha')) {
    function formatear_fecha($fecha) {
        return \Carbon\Carbon::parse($fecha)->format('d/m/Y');
    }
}

if (!function_exists('obtenerTurnoActual')) {

    function obtenerTurnoActual() {

        $fecha = new DateTime();
        // $horaActual = $fecha->format('H');

        // if ($horaActual >= 0 && $horaActual < 6) {
        //     $fecha->sub(new DateInterval("P1D"));
        //     $fecha->setTime(23, 0, 0);
        // }

        // $today = new DateTime();
        $horaActual = intval($fecha->format('H'));
        $minutoActual = intval($fecha->format('i'));

        $fechaInicioTurno = null;
        $fechaFinTurno = null;
        $turno = null;

        if (($horaActual >= 0 && $horaActual < 6) || ($horaActual >= 15 && $minutoActual >= 40)) {
            //TURNO TARDE
            $fechaInicioTurno = new DateTime();
            $fechaInicioTurno->sub(new DateInterval('P1D'));
            $fechaInicioTurno->setTime(15, 40, 0);

            $fechaFinTurno = new DateTime();
            $fechaFinTurno->setTime(5, 59, 0);

            $turno = 'TT';
            $sigla = 'T';
        } else {
            $turno = 'TM';

            $fechaInicioTurno = new DateTime();
            $fechaInicioTurno->setTime(6, 0, 0);

            $fechaFinTurno = new DateTime();
            $fechaFinTurno->setTime(15, 10, 0);
            $sigla = 'M';
        }

        return [
            'TURNO'     => $turno,
            'INICIO'    => $fechaInicioTurno,
            'FIN'       => $fechaFinTurno,
            'SIGLA'     => $sigla
        ];
    }
}

if (!function_exists('getDataTurnoActual')) {

    function getDataTurnoActual() {

        $fecha = new DateTime();

        $horaActual = intval($fecha->format('H'));
        $minutoActual = intval($fecha->format('i'));

        $esTurnoTarde = ($horaActual > 15 || ($horaActual === 15 && $minutoActual >= 40))
            || ($horaActual === 0 && $minutoActual <= 50);

        $fechaBusqueda = clone $fecha;
        if ($esTurnoTarde && $horaActual === 0 && $minutoActual <= 50) {
            $fechaBusqueda->sub(new DateInterval('P1D'));
        }

        if ($esTurnoTarde) {
            $data = Turnos::where('fecha', $fechaBusqueda->format('Y-m-d'))->where('turno', 'T')->first();
        } else {
            $data = Turnos::where('fecha', $fechaBusqueda->format('Y-m-d'))->where('turno', 'M')->first();
        }

        return $data;
    }
}

if (!function_exists('getTurnoActual')) {

    function getTurnoActual() {

        $fecha = new DateTime();
        // $fecha = DateTime::createFromFormat("Y-m-d H:i:s", "2025-10-03 03:10:00");

        $horaActual = intval($fecha->format('H'));
        $minutoActual = intval($fecha->format('i'));

        // if ($horaActual >= 0 && $horaActual < 6) {
        //     $fecha->sub(new DateInterval("P1D"));
        //     $fecha->setTime(23, 0, 0);
        // }

        // $turno = null;
        // $fechaCambioTurno = DateTime::createFromFormat('Y-m-d H:i:s', $fecha->format('Y-m-d') . ' 15:40:00');
        // $fechaCambioTurno = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 15:40:00');


        $esTurnoTarde = ($horaActual > 15 || ($horaActual === 15 && $minutoActual >= 40))
            || ($horaActual === 0 && $minutoActual <= 50);

        $fechaBusqueda = clone $fecha;
        if ($esTurnoTarde && $horaActual === 0 && $minutoActual <= 50) {
            $fechaBusqueda->sub(new DateInterval('P1D'));
        }

        if ($esTurnoTarde) {
            $data = Turnos::where('fecha', $fechaBusqueda->format('Y-m-d'))->where('turno', 'T')->first();
        } else {
            $data = Turnos::where('fecha', $fechaBusqueda->format('Y-m-d'))->where('turno', 'M')->first();
        }


        // $turnoActual = FallasInformadas::select('turno')
        //     ->whereBetween('created_at', [$fechaCambioTurno->format('Y-m-d') . ' 06:00:00', $fechaCambioTurno->format('Y-m-d H:i:s')])
        //     ->first();

        // if ($fecha > $fechaCambioTurno) {
        //     //YA ES LA TARDE, DEBERIA HABER DATOS
        //     if ($turnoActual) {
        //         $turno = $turnoActual?->turno;
        //         if ($turno == 'A') {
        //             $turno = 'B';
        //         } else {
        //             $turno = 'A';
        //         }
        //     }
        // } else {
        //     if ($turnoActual) {
        //         $turno = $turnoActual?->turno;
        //     }
        // }

        // if (is_null($turno)) {
        //     //Busco el último turno informado
        //     $turnoActual = FallasInformadas::select('turno')
        //         ->whereRaw('not turno is null')->orderBy('created_at', 'DESC')->first();

        //     if ($turnoActual) {
        //         $turno = $turnoActual?->turno;

        //         if ($turno == 'A') {
        //             $turno = 'B';
        //         } else {
        //             $turno = 'A';
        //         }
        //     }
        // }
        // Log::alert('Turno actual: ' . $data->turno_nombre);

        if ($data) {
            return $data->turno_nombre;
        } else {
            return "A";
        }
    }
}

if (!function_exists('getFranjaHoraria')) {
    function getFranjaHoraria(?Carbon $now = null, ?string $tz = null): string {
        $tz  = $tz ?? config('app.timezone', 'America/Argentina/Buenos_Aires');
        $now = $now ? $now->copy()->timezone($tz) : Carbon::now($tz);

        $isBetween = function (Carbon $time, string $start, string $end) use ($tz): bool {
            $s = Carbon::parse($start, $tz)->setDateFrom($time); // hoy a $start
            $e = Carbon::parse($end,   $tz)->setDateFrom($time); // hoy a $end

            // Si el fin es "menor" o igual al inicio, el rango cruza medianoche
            if ($e->lessThanOrEqualTo($s)) {
                return $time->greaterThanOrEqualTo($s) || $time->lessThanOrEqualTo($e);
            }
            return $time->greaterThanOrEqualTo($s) && $time->lessThan($e);
        };

        if ($isBetween($now, '06:00', '15:39')) {
            return 'M';
        }
        if ($isBetween($now, '15:40', '05:59')) { // cruza medianoche
            return 'T';
        }
        return ''; // (ej. 00:50–00:06)
    }
}
