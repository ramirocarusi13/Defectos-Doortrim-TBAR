<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reporte diario de QC: replica el reporte que el cliente arma a mano en Excel.
 * Turnos: A = AMARILLO, B = BLANCO, N = NARANJA.
 *
 * No hay datos de produccion (solo cantidades de defectos marcados, sin PPM).
 * La fecha de referencia es SIEMPRE 'marcado_at' (cuando calidad marco el defecto
 * en fin de linea), igual que reporteFmds/reporteDiario en EolDefectoController:
 * un defecto marcado cuenta en el reporte aunque despues se retrabaje o valide.
 */
class ReporteQcController extends Controller
{
    private const TABLA = 'eol_defectos';

    /** Normaliza linea: trim, y null/'' -> 'SIN LINEA'. */
    private const LINEA_EXPR = "COALESCE(NULLIF(LTRIM(RTRIM(linea)), ''), 'SIN LINEA')";

    /** Normaliza turno: trim + upper, y null/'' -> 'S/T'. Valores fuera de A/B/N se conservan tal cual. */
    private const TURNO_EXPR = "COALESCE(NULLIF(UPPER(LTRIM(RTRIM(turno))), ''), 'S/T')";

    /** Normaliza puesto: trim, y null/'' -> 'SIN PUESTO' (mismo criterio que LINEA_EXPR). */
    private const PUESTO_EXPR = "COALESCE(NULLIF(LTRIM(RTRIM(puesto_nombre)), ''), 'SIN PUESTO')";

    /** Normaliza operario: trim, y null/'' -> 'SIN OPERARIO' (mismo criterio que LINEA_EXPR). */
    private const OPERARIO_EXPR = "COALESCE(NULLIF(LTRIM(RTRIM(marcado_por_nombre)), ''), 'SIN OPERARIO')";

    /**
     * Caracteristica mas grave del grupo (A > B > C). CASE explicito en vez de MAX
     * alfabetico porque 'A' < 'C' alfabeticamente coincide con la gravedad, pero lo
     * dejamos explicito para no depender de eso y cubrir NULL/valores fuera de A/B/C.
     */
    private const CARACTERISTICA_EXPR = "CASE
        WHEN MAX(CASE WHEN caracteristica = 'A' THEN 1 ELSE 0 END) = 1 THEN 'A'
        WHEN MAX(CASE WHEN caracteristica = 'B' THEN 1 ELSE 0 END) = 1 THEN 'B'
        WHEN MAX(CASE WHEN caracteristica = 'C' THEN 1 ELSE 0 END) = 1 THEN 'C'
        ELSE NULL
    END";

    private const TURNOS_BASE = ['A', 'B', 'N'];

    /**
     * Filtro de flujo comun a todas las agregaciones: si se pide un flujo puntual
     * (incluido 'PRACTICA', para poder auditar la capacitacion a mano) se usa tal
     * cual; sin flujo explicito, el reporte de produccion excluye SIEMPRE el modo
     * practica/capacitacion.
     */
    private function aplicarFlujo($q, ?string $flujo): void
    {
        if ($flujo) {
            $q->where('flujo', strtoupper(trim($flujo)));
        } else {
            $q->where(fn ($qq) => $qq->whereNull('flujo')->orWhere('flujo', '<>', 'PRACTICA'));
        }
    }

    /**
     * GET /api/reporte-qc/mensual?mes=YYYY-MM&flujo=
     *
     * Matriz linea x turno x dia del mes, con total del mes y total del mes anterior.
     */
    public function mensual(Request $request): JsonResponse
    {
        $mesInput = $request->input('mes');
        $flujo    = $request->input('flujo');

        try {
            $mes = $mesInput
                ? Carbon::createFromFormat('Y-m', $mesInput)->startOfMonth()
                : Carbon::now()->startOfMonth();
        } catch (\Throwable $e) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Formato de mes invalido (use YYYY-MM)'], 422);
        }

        $mesAnterior = $mes->copy()->subMonthNoOverflow();
        $diasEnMes   = $mes->daysInMonth;

        // Dos queries agregadas por linea (una por mes), sin filas crudas ni N+1.
        $actualLinea   = $this->agregarPorDia($mes, $flujo, self::LINEA_EXPR, 'linea');
        $anteriorLinea = $this->agregarPorTurno($mesAnterior, $flujo, self::LINEA_EXPR, 'linea');

        [$lineas, $totalPorDia, $totalGeneral] = $this->armarBloques(
            $actualLinea,
            $anteriorLinea,
            $diasEnMes,
            'linea'
        );

        // Mismo desglose pero por puesto de control (puesto_nombre) en vez de linea.
        $actualPuesto   = $this->agregarPorDia($mes, $flujo, self::PUESTO_EXPR, 'puesto');
        $anteriorPuesto = $this->agregarPorTurno($mesAnterior, $flujo, self::PUESTO_EXPR, 'puesto');

        [$puestos] = $this->armarBloques(
            $actualPuesto,
            $anteriorPuesto,
            $diasEnMes,
            'puesto'
        );

        $totalPorDiaOut = [];
        foreach ($totalPorDia as $d => $v) {
            $totalPorDiaOut[(string) $d] = $v;
        }

        // Ranking por operario y top de fallas del mes (agregados, sin filas crudas).
        $porOperario = $this->agregarPorOperario($mes, $flujo);
        $topDefectos = $this->agregarTopDefectos($mes, $flujo);

        return response()->json([
            'data' => [
                'mes'          => $mes->format('Y-m'),
                'diasEnMes'    => $diasEnMes,
                'lineas'       => $lineas,
                'puestos'      => $puestos,
                'totalPorDia'  => $totalPorDiaOut,
                'totalGeneral' => $totalGeneral,
                'porOperario'  => $porOperario,
                'topDefectos'  => $topDefectos,
            ],
            'error' => false,
        ]);
    }

    /**
     * Arma la matriz [grupo][turno][dia] = cantidad + totalMesAnterior a partir de las
     * dos colecciones agregadas (actual por dia, anterior por turno). Usada tanto para
     * 'lineas' como para 'puestos' (mismo shape, distinta columna de agrupacion).
     *
     * @return array{0: array, 1: array<int,int>, 2: int} [bloques, totalPorDia, totalGeneral]
     */
    private function armarBloques($actual, $anterior, int $diasEnMes, string $key): array
    {
        // [grupo][turno][dia] = cantidad
        $porGrupoTurnoDia = [];
        foreach ($actual as $r) {
            $porGrupoTurnoDia[$r->grupo][$r->turno][(int) $r->dia] = (int) $r->cantidad;
        }

        // [grupo][turno] = cantidad (mes anterior completo)
        $totalAnteriorPorGrupoTurno = [];
        foreach ($anterior as $r) {
            $totalAnteriorPorGrupoTurno[$r->grupo][$r->turno] = (int) $r->cantidad;
        }

        $gruposSet = array_unique(array_merge(
            array_keys($porGrupoTurnoDia),
            array_keys($totalAnteriorPorGrupoTurno)
        ));
        sort($gruposSet);

        $bloques      = [];
        $totalPorDia  = array_fill(1, $diasEnMes, 0);
        $totalGeneral = 0;

        foreach ($gruposSet as $grupo) {
            $turnoKeys = $this->turnosOrdenados(
                array_keys($porGrupoTurnoDia[$grupo] ?? []),
                array_keys($totalAnteriorPorGrupoTurno[$grupo] ?? [])
            );

            $turnosData      = [];
            $totalGrupo         = 0;
            $totalGrupoAnterior = 0;

            foreach ($turnoKeys as $turno) {
                $porDia    = [];
                $totalTurno = 0;

                for ($d = 1; $d <= $diasEnMes; $d++) {
                    $cant = (int) ($porGrupoTurnoDia[$grupo][$turno][$d] ?? 0);
                    $porDia[(string) $d] = $cant;
                    $totalTurno += $cant;
                    $totalPorDia[$d] += $cant;
                }

                $totalAnt = (int) ($totalAnteriorPorGrupoTurno[$grupo][$turno] ?? 0);

                $turnosData[$turno] = [
                    'porDia'           => $porDia,
                    'total'            => $totalTurno,
                    'totalMesAnterior' => $totalAnt,
                ];

                $totalGrupo         += $totalTurno;
                $totalGrupoAnterior += $totalAnt;
            }

            $bloques[] = [
                $key                 => $grupo,
                'turnos'             => $turnosData,
                'total'              => $totalGrupo,
                'totalMesAnterior'   => $totalGrupoAnterior,
            ];

            $totalGeneral += $totalGrupo;
        }

        return [$bloques, $totalPorDia, $totalGeneral];
    }

    /**
     * GET /api/reporte-qc/top?fecha=YYYY-MM-DD&flujo=
     *
     * Top 3 fallas por linea x turno de una fecha puntual.
     */
    public function top(Request $request): JsonResponse
    {
        $fechaInput = $request->input('fecha');
        $flujo      = $request->input('flujo');

        if (!$fechaInput) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Falta el parametro fecha (YYYY-MM-DD)'], 422);
        }

        try {
            $fecha = Carbon::createFromFormat('Y-m-d', $fechaInput)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Formato de fecha invalido (use YYYY-MM-DD)'], 422);
        }

        $q = DB::table(self::TABLA)
            ->select([
                DB::raw(self::LINEA_EXPR . ' as linea'),
                DB::raw(self::TURNO_EXPR . ' as turno'),
                DB::raw("COALESCE(NULLIF(falla_codigo, ''), 'S/C') as falla_codigo"),
                DB::raw('MAX(falla_nombre) as falla_nombre'),
                DB::raw('MAX(CAST(es_critico AS INT)) as es_critico'),
                DB::raw(self::CARACTERISTICA_EXPR . ' as caracteristica'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->whereNotNull('marcado_at')
            ->whereDate('marcado_at', $fecha->format('Y-m-d'));

        $this->aplicarFlujo($q, $flujo);

        $rows = $q->groupBy([
            DB::raw(self::LINEA_EXPR),
            DB::raw(self::TURNO_EXPR),
            DB::raw("COALESCE(NULLIF(falla_codigo, ''), 'S/C')"),
        ])->get();

        // Agrupo (linea x turno) en PHP: un dia tiene pocos grupos, no vale la pena
        // una segunda query con ROW_NUMBER() para el top.
        $porTurnoLinea = [];
        foreach ($rows as $r) {
            $porTurnoLinea[$r->turno][$r->linea][] = $r;
        }

        $turnoKeys = $this->turnosOrdenados(array_keys($porTurnoLinea), []);

        $turnosOut = [];
        foreach ($turnoKeys as $turno) {
            $lineasArr = [];
            foreach (($porTurnoLinea[$turno] ?? []) as $linea => $filas) {
                usort($filas, fn ($a, $b) => $b->cantidad <=> $a->cantidad);
                $top = array_slice($filas, 0, 3);

                $lineasArr[] = [
                    'linea' => $linea,
                    'top'   => array_map(fn ($f) => [
                        'codigo'         => $f->falla_codigo,
                        'nombre'         => $f->falla_nombre,
                        'cantidad'       => (int) $f->cantidad,
                        'esCritico'      => (bool) $f->es_critico,
                        'caracteristica' => $f->caracteristica,
                    ], $top),
                ];
            }
            usort($lineasArr, fn ($a, $b) => strcmp($a['linea'], $b['linea']));
            $turnosOut[$turno] = $lineasArr;
        }

        return response()->json([
            'data' => [
                'fecha'  => $fecha->format('Y-m-d'),
                'turnos' => $turnosOut,
            ],
            'error' => false,
        ]);
    }

    /**
     * Agrega el mes completo por linea x turno x dia (para la matriz mensual).
     * Una sola query agregada por groupBy linea/turno/dia (DAY() en SQL Server).
     */
    private function agregarPorDia(Carbon $mes, ?string $flujo, string $groupExpr = self::LINEA_EXPR, string $alias = 'grupo')
    {
        $q = DB::table(self::TABLA)
            ->select([
                DB::raw($groupExpr . " as $alias"),
                DB::raw(self::TURNO_EXPR . ' as turno'),
                DB::raw('DAY(marcado_at) as dia'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->whereNotNull('marcado_at')
            ->whereYear('marcado_at', $mes->year)
            ->whereMonth('marcado_at', $mes->month);

        $this->aplicarFlujo($q, $flujo);

        $rows = $q->groupBy([
            DB::raw($groupExpr),
            DB::raw(self::TURNO_EXPR),
            DB::raw('DAY(marcado_at)'),
        ])->get();

        // armarBloques() espera siempre la propiedad 'grupo'.
        return $alias === 'grupo' ? $rows : $rows->map(function ($r) use ($alias) {
            $r->grupo = $r->$alias;
            return $r;
        });
    }

    /**
     * Agrega el mes completo por grupo (linea o puesto) x turno (total del mes, sin
     * desglose por dia). Se usa para el "totalMesAnterior".
     */
    private function agregarPorTurno(Carbon $mes, ?string $flujo, string $groupExpr = self::LINEA_EXPR, string $alias = 'grupo')
    {
        $q = DB::table(self::TABLA)
            ->select([
                DB::raw($groupExpr . " as $alias"),
                DB::raw(self::TURNO_EXPR . ' as turno'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->whereNotNull('marcado_at')
            ->whereYear('marcado_at', $mes->year)
            ->whereMonth('marcado_at', $mes->month);

        $this->aplicarFlujo($q, $flujo);

        $rows = $q->groupBy([
            DB::raw($groupExpr),
            DB::raw(self::TURNO_EXPR),
        ])->get();

        return $alias === 'grupo' ? $rows : $rows->map(function ($r) use ($alias) {
            $r->grupo = $r->$alias;
            return $r;
        });
    }

    /**
     * Ranking del mes por operario que marco el defecto (marcado_por_nombre),
     * cantidad desc, tope 20. Una sola query agregada.
     *
     * @return array<int, array{nombre: string, cantidad: int}>
     */
    private function agregarPorOperario(Carbon $mes, ?string $flujo): array
    {
        $q = DB::table(self::TABLA)
            ->select([
                DB::raw(self::OPERARIO_EXPR . ' as nombre'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->whereNotNull('marcado_at')
            ->whereYear('marcado_at', $mes->year)
            ->whereMonth('marcado_at', $mes->month);

        $this->aplicarFlujo($q, $flujo);

        $rows = $q->groupBy(DB::raw(self::OPERARIO_EXPR))
            ->orderByDesc('cantidad')
            ->limit(20)
            ->get();

        return $rows->map(fn ($r) => [
            'nombre'   => $r->nombre,
            'cantidad' => (int) $r->cantidad,
        ])->all();
    }

    /**
     * Top de fallas del mes completo (sin desglosar por linea/turno/dia), con la
     * caracteristica mas grave del grupo (mismo CASE que top()). Cantidad desc, tope 15.
     *
     * @return array<int, array{codigo: string, nombre: ?string, cantidad: int, caracteristica: ?string}>
     */
    private function agregarTopDefectos(Carbon $mes, ?string $flujo): array
    {
        $q = DB::table(self::TABLA)
            ->select([
                DB::raw("COALESCE(NULLIF(falla_codigo, ''), 'S/C') as codigo"),
                DB::raw('MAX(falla_nombre) as nombre'),
                DB::raw(self::CARACTERISTICA_EXPR . ' as caracteristica'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->whereNotNull('marcado_at')
            ->whereYear('marcado_at', $mes->year)
            ->whereMonth('marcado_at', $mes->month);

        $this->aplicarFlujo($q, $flujo);

        $rows = $q->groupBy(DB::raw("COALESCE(NULLIF(falla_codigo, ''), 'S/C')"))
            ->orderByDesc('cantidad')
            ->limit(15)
            ->get();

        return $rows->map(fn ($r) => [
            'codigo'         => $r->codigo,
            'nombre'         => $r->nombre,
            'cantidad'       => (int) $r->cantidad,
            'caracteristica' => $r->caracteristica,
        ])->all();
    }

    /**
     * A, B, N siempre presentes (aunque esten en 0) para que el front dibuje la
     * matriz completa; turnos "raros" (fuera de A/B/N, ya normalizados trim+upper)
     * se agregan despues, ordenados alfabeticamente.
     */
    private function turnosOrdenados(array ...$listasDeTurnos): array
    {
        $todos = array_unique(array_merge(...$listasDeTurnos));
        $extra = array_values(array_diff($todos, self::TURNOS_BASE));
        sort($extra);

        return array_merge(self::TURNOS_BASE, $extra);
    }
}
