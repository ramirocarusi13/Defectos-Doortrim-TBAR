<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tablero en tiempo real del ORIGEN de los defectos: en que puesto de trabajo,
 * turno y operario se genero cada defecto, segun lo declara el retrabajador al
 * cerrar el retrabajo (eol_defectos.operacion_* / operario_* / turno_generacion /
 * sector_generacion, sellados en EolDefectoController::reparar).
 *
 * El dia es la JORNADA de planta: 06:00 de la fecha -> 05:59:59 del dia siguiente
 * (el turno tarde termina de madrugada). Se filtra por asignado_at (momento en que
 * se declaro el origen). Excluye siempre el modo practica/capacitacion.
 */
class ReporteOrigenController extends Controller
{
    private const HORA_INICIO_JORNADA = 6;

    /**
     * GET /api/eol-defectos/reporte-origen?fecha=YYYY-MM-DD&turno=A|B|N
     */
    public function index(Request $request): JsonResponse
    {
        $ahora = Carbon::now();

        $fechaInput = $request->input('fecha');
        if ($fechaInput) {
            try {
                $fecha = Carbon::createFromFormat('Y-m-d', $fechaInput)->startOfDay();
            } catch (\Throwable $e) {
                return response()->json(['data' => [], 'error' => true, 'message' => 'Formato de fecha invalido (use YYYY-MM-DD)'], 422);
            }
        } else {
            // Antes de las 06:00 seguimos en la jornada de ayer.
            $fecha = $ahora->copy()->subHours(self::HORA_INICIO_JORNADA)->startOfDay();
        }

        $desde = $fecha->copy()->setTime(self::HORA_INICIO_JORNADA, 0, 0);
        $hasta = $desde->copy()->addDay()->subSecond();

        $turno = strtoupper(trim((string) $request->input('turno', '')));
        $turno = ['AMARILLO' => 'A', 'BLANCO' => 'B', 'NARANJA' => 'N'][$turno] ?? $turno;

        $base = DB::table('eol_defectos')
            ->whereNotNull('asignado_at')
            ->whereBetween('asignado_at', [$desde, $hasta])
            ->where(fn ($q) => $q->whereNull('flujo')->orWhere('flujo', '<>', 'PRACTICA'));

        if ($turno !== '') {
            $base->where('turno_generacion', $turno);
        }

        $total       = (clone $base)->count();
        $sinOperario = (clone $base)->where(fn ($q) => $q->whereNull('operario_nombre')->orWhere('operario_nombre', ''))->count();

        $porSector = (clone $base)
            ->select([DB::raw("COALESCE(NULLIF(sector_generacion, ''), 'S/SECTOR') as sector"), DB::raw('COUNT(*) as total')])
            ->groupBy(DB::raw("COALESCE(NULLIF(sector_generacion, ''), 'S/SECTOR')"))
            ->get()
            ->map(fn ($r) => ['sector' => $r->sector, 'total' => (int) $r->total])
            ->sortByDesc('total')->values();

        $porPuesto = (clone $base)
            ->select([
                'operacion_id',
                DB::raw('MAX(operacion_nombre) as nombre'),
                DB::raw('MAX(sector_generacion) as sector'),
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('operacion_id')
            ->get()
            ->map(fn ($r) => [
                'id'     => $r->operacion_id,
                'nombre' => $r->nombre,
                'sector' => $r->sector,
                'total'  => (int) $r->total,
            ])
            ->sortByDesc('total')->values();

        // Ranking de operarios: identificados por legajo (o nombre si no hay legajo).
        $filasOperario = (clone $base)
            ->whereNotNull('operario_nombre')->where('operario_nombre', '<>', '')
            ->select([
                DB::raw("COALESCE(NULLIF(operario_id, ''), operario_nombre) as clave"),
                DB::raw('MAX(operario_id) as legajo'),
                DB::raw('MAX(operario_nombre) as nombre'),
                DB::raw('MAX(turno_generacion) as turno'),
                'operacion_nombre',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy(DB::raw("COALESCE(NULLIF(operario_id, ''), operario_nombre)"), 'operacion_nombre')
            ->get();

        $porOperario = $filasOperario
            ->groupBy('clave')
            ->map(function ($filas) {
                $primero = $filas->first();
                return [
                    'legajo'  => $primero->legajo,
                    'nombre'  => $primero->nombre,
                    'turno'   => $primero->turno,
                    'total'   => (int) $filas->sum('total'),
                    'puestos' => $filas->sortByDesc('total')
                        ->map(fn ($f) => ['nombre' => $f->operacion_nombre, 'total' => (int) $f->total])
                        ->values()->all(),
                ];
            })
            ->sortByDesc('total')->values();

        $porTurno = (clone $base)
            ->select([DB::raw("COALESCE(NULLIF(turno_generacion, ''), 'S/T') as turno"), DB::raw('COUNT(*) as total')])
            ->groupBy(DB::raw("COALESCE(NULLIF(turno_generacion, ''), 'S/T')"))
            ->get()
            ->map(fn ($r) => ['turno' => $r->turno, 'total' => (int) $r->total])
            ->values();

        // Por hora de la jornada (06 -> 05), para ver el ritmo en vivo.
        $filasHora = (clone $base)
            ->select([DB::raw('DATEPART(HOUR, asignado_at) as hora'), DB::raw('COUNT(*) as total')])
            ->groupBy(DB::raw('DATEPART(HOUR, asignado_at)'))
            ->get()
            ->keyBy(fn ($r) => (int) $r->hora);

        $porHora = [];
        for ($i = 0; $i < 24; $i++) {
            $h = (self::HORA_INICIO_JORNADA + $i) % 24;
            $porHora[] = ['hora' => $h, 'total' => (int) ($filasHora[$h]->total ?? 0)];
        }

        $ultimos = (clone $base)
            ->orderByDesc('asignado_at')
            ->limit(25)
            ->get([
                'id', 'qr', 'modelo', 'tipo', 'lado', 'falla_codigo', 'falla_nombre', 'estado',
                'operacion_nombre', 'sector_generacion', 'turno_generacion',
                'operario_id', 'operario_nombre', 'reparado_por_nombre', 'asignado_at', 'puesto_nombre',
            ])
            ->map(fn ($d) => [
                'id'          => $d->id,
                'hora'        => $d->asignado_at ? Carbon::parse($d->asignado_at)->format('H:i') : null,
                'qr'          => $d->qr,
                'modelo'      => $d->modelo,
                'tipo'        => $d->tipo,
                'lado'        => $d->lado,
                'falla'       => trim(($d->falla_codigo ? $d->falla_codigo . ' - ' : '') . ($d->falla_nombre ?? '')),
                'estado'      => $d->estado,
                'puesto'      => $d->operacion_nombre,
                'sector'      => $d->sector_generacion,
                'turno'       => $d->turno_generacion,
                'legajo'      => $d->operario_id,
                'operario'    => $d->operario_nombre,
                'retrabajo'   => $d->reparado_por_nombre,
                'puestoControl' => $d->puesto_nombre,
            ])
            ->values();

        // Defectos de la jornada que todavia no tienen origen (pendientes de retrabajo).
        $sinOrigen = DB::table('eol_defectos')
            ->whereBetween('marcado_at', [$desde, $hasta])
            ->whereNull('asignado_at')
            ->whereIn('estado', ['PENDIENTE'])
            ->where(fn ($q) => $q->whereNull('flujo')->orWhere('flujo', 'EOL'))
            ->count();

        return response()->json([
            'data' => [
                'fecha'       => $fecha->format('Y-m-d'),
                'desde'       => $desde->format('Y-m-d H:i'),
                'hasta'       => $hasta->format('Y-m-d H:i'),
                'turno'       => $turno !== '' ? $turno : null,
                'actualizado' => $ahora->format('H:i:s'),
                'total'       => $total,
                'sinOperario' => $sinOperario,
                'pendientesSinOrigen' => $sinOrigen,
                'porSector'   => $porSector->all(),
                'porPuesto'   => $porPuesto->all(),
                'porOperario' => $porOperario->all(),
                'porTurno'    => $porTurno->all(),
                'porHora'     => $porHora,
                'ultimos'     => $ultimos->all(),
            ],
            'error' => false,
        ]);
    }
}
