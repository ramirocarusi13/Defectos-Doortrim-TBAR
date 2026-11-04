<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de consumo de componentes declarados por produccion al retrabajar
 * defectos EOL (eol_defecto_componentes). Mismo criterio defensivo que el resto
 * de los reportes de produccion: excluye siempre el modo practica/capacitacion
 * (join a eol_defectos por flujo).
 */
class ReporteComponentesController extends Controller
{
    private const TABLA           = 'eol_defecto_componentes';
    private const TABLA_DEFECTOS  = 'eol_defectos';

    /**
     * GET /api/reporte-componentes?desde=YYYY-MM-DD&hasta=YYYY-MM-DD&turno=
     * Defaults: desde/hasta sin especificar -> mes calendario actual completo.
     * turno opcional (comparacion case-insensitive contra el turno guardado en la
     * fila del componente, resuelto del usuario al momento de reparar).
     */
    public function index(Request $request): JsonResponse
    {
        $periodo = $this->resolverPeriodo($request);
        if ($periodo instanceof JsonResponse) {
            return $periodo;
        }
        [$desde, $hasta, $turno] = $periodo;

        $base = DB::table(self::TABLA . ' as c')
            ->join(self::TABLA_DEFECTOS . ' as d', 'd.id', '=', 'c.eol_defecto_id')
            ->whereBetween('c.created_at', [$desde, $hasta])
            // Mismo criterio defensivo que el resto de los reportes de produccion.
            ->where(fn ($q) => $q->whereNull('d.flujo')->orWhere('d.flujo', '<>', 'PRACTICA'));

        if ($turno) {
            $base->whereRaw("UPPER(LTRIM(RTRIM(c.turno))) = ?", [$turno]);
        }

        // Agregado por componente (una sola query agrupada, sin iterar filas en PHP).
        $filasComponente = (clone $base)
            ->select([
                'c.componente_codigo as codigo',
                DB::raw('MAX(c.componente_nombre) as nombre'),
                DB::raw("SUM(CASE WHEN c.accion = 'DESARMADO' THEN c.cantidad ELSE 0 END) as desarmados"),
                DB::raw("SUM(CASE WHEN c.accion = 'CAMBIADO' THEN c.cantidad ELSE 0 END) as cambiados"),
                DB::raw("SUM(CASE WHEN c.accion = 'SCRAP' THEN c.cantidad ELSE 0 END) as scrap"),
            ])
            ->groupBy('c.componente_codigo')
            ->get();

        $porComponente = $filasComponente
            ->map(fn ($r) => [
                'codigo'     => $r->codigo,
                'nombre'     => $r->nombre,
                'desarmados' => (int) $r->desarmados,
                'cambiados'  => (int) $r->cambiados,
                'scrap'      => (int) $r->scrap,
                'total'      => (int) $r->desarmados + (int) $r->cambiados + (int) $r->scrap,
            ])
            ->sortByDesc('total')
            ->values();

        $totalDesarmados = (int) $porComponente->sum('desarmados');
        $totalCambiados  = (int) $porComponente->sum('cambiados');
        $totalScrap      = (int) $porComponente->sum('scrap');

        // Agregado por dia (del mes/rango), otra unica query agrupada.
        $filasDia = (clone $base)
            ->select([
                DB::raw('DAY(c.created_at) as dia'),
                DB::raw("SUM(CASE WHEN c.accion = 'DESARMADO' THEN c.cantidad ELSE 0 END) as desarmados"),
                DB::raw("SUM(CASE WHEN c.accion = 'CAMBIADO' THEN c.cantidad ELSE 0 END) as cambiados"),
                DB::raw("SUM(CASE WHEN c.accion = 'SCRAP' THEN c.cantidad ELSE 0 END) as scrap"),
            ])
            ->groupBy(DB::raw('DAY(c.created_at)'))
            ->get();

        $porDia = [];
        foreach ($filasDia as $r) {
            $porDia[(string) (int) $r->dia] = [
                'desarmados' => (int) $r->desarmados,
                'cambiados'  => (int) $r->cambiados,
                'scrap'      => (int) $r->scrap,
            ];
        }

        return response()->json([
            'data' => [
                'totalDesarmados' => $totalDesarmados,
                'totalCambiados'  => $totalCambiados,
                'totalScrap'      => $totalScrap,
                'porComponente'   => $porComponente->all(),
                'porDia'          => $porDia,
            ],
            'error' => false,
        ]);
    }

    /**
     * Lee y valida desde/hasta/turno. Sin desde/hasta -> mes calendario actual
     * completo. Devuelve [Carbon $desde, Carbon $hasta, ?string $turno] o un
     * JsonResponse de error 422 si alguna fecha viene mal formada.
     *
     * @return array{0: Carbon, 1: Carbon, 2: ?string}|JsonResponse
     */
    private function resolverPeriodo(Request $request)
    {
        $desdeInput = $request->input('desde');
        $hastaInput = $request->input('hasta');
        $turnoInput = $request->input('turno');

        $desde = null;
        $hasta = null;

        if ($desdeInput) {
            try {
                $desde = Carbon::createFromFormat('Y-m-d', $desdeInput)->startOfDay();
            } catch (\Throwable $e) {
                return response()->json(['data' => [], 'error' => true, 'message' => 'Formato de desde invalido (use YYYY-MM-DD)'], 422);
            }
        }

        if ($hastaInput) {
            try {
                $hasta = Carbon::createFromFormat('Y-m-d', $hastaInput)->endOfDay();
            } catch (\Throwable $e) {
                return response()->json(['data' => [], 'error' => true, 'message' => 'Formato de hasta invalido (use YYYY-MM-DD)'], 422);
            }
        }

        // Defaults: mes calendario actual completo (si falta alguno de los dos extremos).
        $ahora = Carbon::now();
        $desde = $desde ?: $ahora->copy()->startOfMonth()->startOfDay();
        $hasta = $hasta ?: $ahora->copy()->endOfMonth()->endOfDay();

        $turno = $turnoInput ? strtoupper(trim($turnoInput)) : null;

        return [$desde, $hasta, $turno];
    }
}
