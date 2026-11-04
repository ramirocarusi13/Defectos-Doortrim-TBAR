<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard operativo de defectos EOL (agregados, sin filas crudas) para una
 * pantalla tipo "andon" de calidad. Misma fecha de referencia y mismos
 * criterios de normalizacion que ReporteQcController: la fecha SIEMPRE es
 * 'marcado_at' y turno A=AMARILLO, B=BLANCO, N=NARANJA.
 *
 * Filtros comunes a los 3 primeros endpoints:
 *  - fecha=YYYY-MM-DD -> ese dia puntual. Sin fecha -> mes calendario actual completo.
 *  - turno=A|B|N       -> ese turno. Sin turno -> todos.
 */
class DashboardQcController extends Controller
{
    private const TABLA = 'eol_defectos';

    /** Normaliza turno: trim + upper, y null/'' -> 'S/T' (igual que ReporteQcController). */
    private const TURNO_EXPR = "COALESCE(NULLIF(UPPER(LTRIM(RTRIM(turno))), ''), 'S/T')";

    /** Normaliza puesto: trim, y null/'' -> 'SIN PUESTO'. */
    private const PUESTO_EXPR = "COALESCE(NULLIF(LTRIM(RTRIM(puesto_nombre)), ''), 'SIN PUESTO')";

    /** Normaliza operario: trim, y null/'' -> 'SIN OPERARIO'. */
    private const OPERARIO_EXPR = "COALESCE(NULLIF(LTRIM(RTRIM(marcado_por_nombre)), ''), 'SIN OPERARIO')";

    /** Normaliza modelo: trim + upper, y null/'' -> 'SIN MODELO'. */
    private const MODELO_EXPR = "COALESCE(NULLIF(UPPER(LTRIM(RTRIM(modelo))), ''), 'SIN MODELO')";

    /** Normaliza codigo de defecto: trim, y null/'' -> 'S/C' (mismo criterio que topDefectos). */
    private const DEFECTO_EXPR = "COALESCE(NULLIF(LTRIM(RTRIM(falla_codigo)), ''), 'S/C')";

    /** Normaliza estado: trim + upper (siempre viene seteado, default 'PENDIENTE'). */
    private const ESTADO_EXPR = "UPPER(LTRIM(RTRIM(estado)))";

    /**
     * Filtros cruzados (estilo Power BI) que acepta GET /api/dashboard-qc, combinables
     * entre si (AND) y con fecha/turno. La clave es el nombre del query param; el valor
     * es la expresion SQL normalizada con la que se compara (case-insensitive via UPPER).
     */
    private const DIMENSIONES = [
        'operario' => self::OPERARIO_EXPR,
        'defecto'  => self::DEFECTO_EXPR,
        'puesto'   => self::PUESTO_EXPR,
        'modelo'   => self::MODELO_EXPR,
    ];

    /** tipo y lado, normalizados a NULL cuando estan vacios (para descartarlos de la combinacion). */
    private const TIPO_EXPR = "NULLIF(UPPER(LTRIM(RTRIM(tipo))), '')";
    private const LADO_EXPR = "NULLIF(UPPER(LTRIM(RTRIM(lado))), '')";

    /**
     * Caracteristica mas grave del grupo (A > B > C). Identico criterio que
     * ReporteQcController::CARACTERISTICA_EXPR.
     */
    private const CARACTERISTICA_EXPR = "CASE
        WHEN MAX(CASE WHEN caracteristica = 'A' THEN 1 ELSE 0 END) = 1 THEN 'A'
        WHEN MAX(CASE WHEN caracteristica = 'B' THEN 1 ELSE 0 END) = 1 THEN 'B'
        WHEN MAX(CASE WHEN caracteristica = 'C' THEN 1 ELSE 0 END) = 1 THEN 'C'
        ELSE NULL
    END";

    /**
     * GET /api/dashboard-qc?fecha=YYYY-MM-DD&turno=A|B|N&operario=&defecto=&puesto=&modelo=
     *
     * Pantalla principal: KPIs por estado actual + rankings agregados del periodo.
     *
     * Filtrado cruzado estilo Power BI: operario/defecto/puesto/modelo son combinables
     * entre si (AND) y con fecha/turno, PERO cada lista ignora el filtro de su PROPIA
     * dimension (ej.: con operario=GOMEZ, topOperarios/operarios siguen mostrando a
     * TODOS los operarios del resto del periodo filtrado, para que el front pueda
     * resaltar la seleccion sin que el resto de las barras desaparezca; en cambio
     * topDefectos/porModelo/porPuesto/kpis si quedan acotados a GOMEZ).
     */
    public function index(Request $request): JsonResponse
    {
        $resuelto = $this->resolverFiltros($request);
        if ($resuelto instanceof JsonResponse) {
            return $resuelto;
        }
        [$fecha, $turno, $dims] = $resuelto;

        return response()->json([
            'data' => [
                'actualizado'   => Carbon::now()->format('H:i:s'),
                'kpis'          => $this->kpis($fecha, $turno, $dims),
                'topDefectos'   => $this->topDefectos($fecha, $turno, $dims, 10),
                'topOperarios'  => $this->topOperarios($fecha, $turno, $dims, 10),
                'porModelo'     => $this->porModelo($fecha, $turno, $dims, 8),
                'porTipoLado'   => $this->porTipoLado($fecha, $turno, $dims),
                'porPuesto'     => $this->porPuesto($fecha, $turno, $dims),
                'operarios'     => $this->operarios($fecha, $turno, $dims),
            ],
            'error' => false,
        ]);
    }

    /**
     * GET /api/dashboard-qc/operario?nombre=&fecha=&turno=
     *
     * Drill-down de un operario puntual (nombre normalizado exacto, igual criterio
     * que OPERARIO_EXPR). mesActual/mesPasado ignoran fecha/turno a proposito: son
     * el total calendario del operario, para comparar contra el periodo filtrado.
     */
    public function operario(Request $request): JsonResponse
    {
        $nombreInput = trim((string) $request->input('nombre', ''));
        if ($nombreInput === '') {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Falta el parametro nombre'], 422);
        }

        $periodo = $this->resolverPeriodo($request);
        if ($periodo instanceof JsonResponse) {
            return $periodo;
        }
        [$fecha, $turno] = $periodo;

        $totalPeriodo = (int) $this->baseQuery($fecha, $turno)->count();

        $cantidadPeriodo = (int) $this->baseQuery($fecha, $turno)
            ->whereRaw(self::OPERARIO_EXPR . ' = ?', [$nombreInput])
            ->count();

        $porcentaje = $totalPeriodo > 0
            ? round(($cantidadPeriodo / $totalPeriodo) * 100, 1)
            : 0.0;

        $porDefectoQuery = DB::table(self::TABLA)
            ->select([
                DB::raw("COALESCE(NULLIF(falla_codigo, ''), 'S/C') as codigo"),
                DB::raw('MAX(falla_nombre) as nombre'),
                DB::raw(self::CARACTERISTICA_EXPR . ' as caracteristica'),
                DB::raw('COUNT(*) as cantidad'),
            ]);
        $this->aplicarFiltrosPeriodo($porDefectoQuery, $fecha, $turno);

        $porDefecto = $porDefectoQuery
            ->whereRaw(self::OPERARIO_EXPR . ' = ?', [$nombreInput])
            ->groupBy(DB::raw("COALESCE(NULLIF(falla_codigo, ''), 'S/C')"))
            ->orderByDesc('cantidad')
            ->get()
            ->map(fn ($r) => [
                'codigo'         => $r->codigo,
                'nombre'         => $r->nombre,
                'cantidad'       => (int) $r->cantidad,
                'caracteristica' => $r->caracteristica,
            ])->all();

        $ahora        = Carbon::now();
        $mesAnterior  = $ahora->copy()->subMonthNoOverflow();

        $mesActualQuery = DB::table(self::TABLA)
            ->whereNotNull('marcado_at')
            ->whereYear('marcado_at', $ahora->year)
            ->whereMonth('marcado_at', $ahora->month)
            ->whereRaw(self::OPERARIO_EXPR . ' = ?', [$nombreInput]);
        self::excluirPractica($mesActualQuery);
        $mesActual = (int) $mesActualQuery->count();

        $mesPasadoQuery = DB::table(self::TABLA)
            ->whereNotNull('marcado_at')
            ->whereYear('marcado_at', $mesAnterior->year)
            ->whereMonth('marcado_at', $mesAnterior->month)
            ->whereRaw(self::OPERARIO_EXPR . ' = ?', [$nombreInput]);
        self::excluirPractica($mesPasadoQuery);
        $mesPasado = (int) $mesPasadoQuery->count();

        return response()->json([
            'data' => [
                'nombre'              => $nombreInput,
                'cantidadPeriodo'     => $cantidadPeriodo,
                'porcentajeDelPeriodo' => $porcentaje,
                'porDefecto'          => $porDefecto,
                'mesActual'           => $mesActual,
                'mesPasado'           => $mesPasado,
            ],
            'error' => false,
        ]);
    }

    /**
     * GET /api/dashboard-qc/evolucion            -> ultimos 12 meses calendario (con ceros).
     * GET /api/dashboard-qc/evolucion?mes=YYYY-MM -> desglose por dia de ese mes (con ceros).
     */
    public function evolucion(Request $request): JsonResponse
    {
        $mesInput = $request->input('mes');

        if ($mesInput) {
            try {
                $mes = Carbon::createFromFormat('Y-m', $mesInput)->startOfMonth();
            } catch (\Throwable $e) {
                return response()->json(['data' => [], 'error' => true, 'message' => 'Formato de mes invalido (use YYYY-MM)'], 422);
            }

            $evolucionMesQuery = DB::table(self::TABLA)
                ->select([
                    DB::raw('DAY(marcado_at) as dia'),
                    DB::raw('COUNT(*) as cantidad'),
                ])
                ->whereNotNull('marcado_at')
                ->whereYear('marcado_at', $mes->year)
                ->whereMonth('marcado_at', $mes->month);
            self::excluirPractica($evolucionMesQuery);
            $rows = $evolucionMesQuery
                ->groupBy(DB::raw('DAY(marcado_at)'))
                ->get();

            $porDia = array_fill(1, $mes->daysInMonth, 0);
            foreach ($rows as $r) {
                $porDia[(int) $r->dia] = (int) $r->cantidad;
            }

            $porDiaOut = [];
            foreach ($porDia as $d => $cant) {
                $porDiaOut[(string) $d] = $cant;
            }

            return response()->json([
                'data' => [
                    'mes'    => $mes->format('Y-m'),
                    'porDia' => $porDiaOut,
                ],
                'error' => false,
            ]);
        }

        // Ultimos 12 meses calendario (incluyendo el actual), con ceros donde no hubo datos.
        $hasta  = Carbon::now()->startOfMonth();
        $desde  = $hasta->copy()->subMonthsNoOverflow(11);

        $evolucion12MesesQuery = DB::table(self::TABLA)
            ->select([
                DB::raw('YEAR(marcado_at) as anio'),
                DB::raw('MONTH(marcado_at) as mes'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->whereNotNull('marcado_at')
            ->where('marcado_at', '>=', $desde->copy()->startOfDay())
            ->where('marcado_at', '<', $hasta->copy()->addMonthNoOverflow()->startOfDay());
        self::excluirPractica($evolucion12MesesQuery);
        $rows = $evolucion12MesesQuery
            ->groupBy(DB::raw('YEAR(marcado_at)'), DB::raw('MONTH(marcado_at)'))
            ->get();

        $porClave = [];
        foreach ($rows as $r) {
            $clave = sprintf('%04d-%02d', $r->anio, $r->mes);
            $porClave[$clave] = (int) $r->cantidad;
        }

        $meses = [];
        $cursor = $desde->copy();
        for ($i = 0; $i < 12; $i++) {
            $clave = $cursor->format('Y-m');
            $meses[] = [
                'mes'      => $clave,
                'cantidad' => $porClave[$clave] ?? 0,
            ];
            $cursor->addMonthNoOverflow();
        }

        return response()->json([
            'data' => ['meses' => $meses],
            'error' => false,
        ]);
    }

    /**
     * Lee y valida fecha/turno del request. Devuelve [Carbon|null $fecha, string|null $turno]
     * o un JsonResponse de error 422 si la fecha viene mal formada.
     *
     * @return array{0: ?Carbon, 1: ?string}|JsonResponse
     */
    private function resolverPeriodo(Request $request)
    {
        $fechaInput = $request->input('fecha');
        $turnoInput = $request->input('turno');

        $fecha = null;
        if ($fechaInput) {
            try {
                $fecha = Carbon::createFromFormat('Y-m-d', $fechaInput)->startOfDay();
            } catch (\Throwable $e) {
                return response()->json(['data' => [], 'error' => true, 'message' => 'Formato de fecha invalido (use YYYY-MM-DD)'], 422);
            }
        }

        $turno = $turnoInput ? strtoupper(trim($turnoInput)) : null;

        return [$fecha, $turno];
    }

    /**
     * Igual que resolverPeriodo() pero ademas lee y valida los filtros cruzados
     * (operario/defecto/puesto/modelo): strings simples, trim, max 150 caracteres.
     * Devuelve [Carbon|null $fecha, string|null $turno, array<string,string> $dims]
     * o un JsonResponse de error 422.
     *
     * @return array{0: ?Carbon, 1: ?string, 2: array<string,string>}|JsonResponse
     */
    private function resolverFiltros(Request $request)
    {
        $periodo = $this->resolverPeriodo($request);
        if ($periodo instanceof JsonResponse) {
            return $periodo;
        }
        [$fecha, $turno] = $periodo;

        $dims = [];
        foreach (array_keys(self::DIMENSIONES) as $clave) {
            $valor = $request->input($clave);
            if ($valor === null) {
                continue;
            }
            $valor = trim((string) $valor);
            if ($valor === '') {
                continue;
            }
            if (mb_strlen($valor) > 150) {
                return response()->json([
                    'data'    => [],
                    'error'   => true,
                    'message' => "El filtro '$clave' supera los 150 caracteres",
                ], 422);
            }
            $dims[$clave] = $valor;
        }

        return [$fecha, $turno, $dims];
    }

    /**
     * Aplica el filtro de periodo (fecha puntual o mes actual completo) + turno
     * opcional a un query builder ya armado (sobre eol_defectos o alias).
     */
    private function aplicarFiltrosPeriodo($q, ?Carbon $fecha, ?string $turno): void
    {
        $q->whereNotNull('marcado_at');

        if ($fecha) {
            $q->whereDate('marcado_at', $fecha->format('Y-m-d'));
        } else {
            $ahora = Carbon::now();
            $q->whereYear('marcado_at', $ahora->year)->whereMonth('marcado_at', $ahora->month);
        }

        if ($turno) {
            $q->whereRaw(self::TURNO_EXPR . ' = ?', [$turno]);
        }

        self::excluirPractica($q);
    }

    /**
     * Excluye SIEMPRE los defectos del modo practica/capacitacion de la analitica
     * de produccion. El dashboard no expone un filtro ?flujo= explicito (a
     * diferencia de ReporteQcController), asi que aca no hay forma de "pedirla":
     * queda afuera siempre.
     */
    private static function excluirPractica($q): void
    {
        $q->where(fn ($qq) => $qq->whereNull('flujo')->orWhere('flujo', '<>', 'PRACTICA'));
    }

    /**
     * Aplica los filtros cruzados (operario/defecto/puesto/modelo) a un query builder,
     * salteando la dimension indicada en $excluir (la propia dimension de cada lista,
     * para que Power BI-style no se "auto-filtre" y desaparezca del grafico).
     * Comparacion case-insensitive (UPPER de ambos lados) sobre la expresion normalizada.
     *
     * @param array<string,string> $dims
     */
    private function aplicarFiltrosDimension($q, array $dims, ?string $excluir = null): void
    {
        foreach ($dims as $clave => $valor) {
            if ($clave === $excluir || !isset(self::DIMENSIONES[$clave])) {
                continue;
            }
            $q->whereRaw('UPPER(' . self::DIMENSIONES[$clave] . ') = UPPER(?)', [$valor]);
        }
    }

    /**
     * Query base sobre eol_defectos con periodo + filtros cruzados ya aplicados.
     * $excluirDimension salte la dimension propia de la lista que arma la query.
     *
     * @param array<string,string> $dims
     */
    private function baseQuery(?Carbon $fecha, ?string $turno, array $dims = [], ?string $excluirDimension = null)
    {
        $q = DB::table(self::TABLA);
        $this->aplicarFiltrosPeriodo($q, $fecha, $turno);
        $this->aplicarFiltrosDimension($q, $dims, $excluirDimension);

        return $q;
    }

    /**
     * KPIs por estado ACTUAL de los defectos marcados en el periodo. 'marcados' es
     * el total (independiente del estado); el resto son conteos por estado puntual.
     *
     * @param array<string,string> $dims
     * @return array{marcados:int, retrabajados:int, pendientes:int, validados:int, scrap:int, rechazados:int}
     */
    private function kpis(?Carbon $fecha, ?string $turno, array $dims): array
    {
        // KPIs no tienen dimension propia: aplican TODOS los filtros cruzados.
        $rows = $this->baseQuery($fecha, $turno, $dims)
            ->select([
                DB::raw(self::ESTADO_EXPR . ' as estado'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->groupBy(DB::raw(self::ESTADO_EXPR))
            ->get();

        $porEstado = [];
        $total     = 0;
        foreach ($rows as $r) {
            $cant = (int) $r->cantidad;
            $porEstado[$r->estado] = $cant;
            $total += $cant;
        }

        return [
            'marcados'     => $total,
            'retrabajados' => $porEstado['RETRABAJADO'] ?? 0,
            'pendientes'   => $porEstado['PENDIENTE'] ?? 0,
            'validados'    => $porEstado['VALIDADO'] ?? 0,
            'scrap'        => $porEstado['SCRAP'] ?? 0,
            'rechazados'   => $porEstado['RECHAZADO'] ?? 0,
        ];
    }

    /**
     * Top de fallas del periodo, cantidad desc, con la caracteristica mas grave
     * del grupo (A > B > C). Mismo criterio que ReporteQcController::agregarTopDefectos.
     * Ignora el filtro 'defecto' (dimension propia): con defecto=CD se sigue viendo
     * el resto de los codigos, para que el front pueda resaltar el elegido.
     *
     * @param array<string,string> $dims
     */
    private function topDefectos(?Carbon $fecha, ?string $turno, array $dims, int $limite): array
    {
        $rows = $this->baseQuery($fecha, $turno, $dims, 'defecto')
            ->select([
                DB::raw("COALESCE(NULLIF(falla_codigo, ''), 'S/C') as codigo"),
                DB::raw('MAX(falla_nombre) as nombre'),
                DB::raw(self::CARACTERISTICA_EXPR . ' as caracteristica'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->groupBy(DB::raw("COALESCE(NULLIF(falla_codigo, ''), 'S/C')"))
            ->orderByDesc('cantidad')
            ->limit($limite)
            ->get();

        return $rows->map(fn ($r) => [
            'codigo'         => $r->codigo,
            'nombre'         => $r->nombre,
            'cantidad'       => (int) $r->cantidad,
            'caracteristica' => $r->caracteristica,
        ])->all();
    }

    /**
     * Top operarios (marcado_por_nombre normalizado) del periodo, cantidad desc.
     * Ignora el filtro 'operario' (dimension propia): ver nota en topDefectos().
     *
     * @param array<string,string> $dims
     */
    private function topOperarios(?Carbon $fecha, ?string $turno, array $dims, int $limite): array
    {
        $rows = $this->baseQuery($fecha, $turno, $dims, 'operario')
            ->select([
                DB::raw(self::OPERARIO_EXPR . ' as nombre'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->groupBy(DB::raw(self::OPERARIO_EXPR))
            ->orderByDesc('cantidad')
            ->limit($limite)
            ->get();

        return $rows->map(fn ($r) => [
            'nombre'   => $r->nombre,
            'cantidad' => (int) $r->cantidad,
        ])->all();
    }

    /**
     * Top modelos (normalizado, vacios -> 'SIN MODELO') del periodo, cantidad desc.
     * Ignora el filtro 'modelo' (dimension propia): ver nota en topDefectos().
     *
     * @param array<string,string> $dims
     */
    private function porModelo(?Carbon $fecha, ?string $turno, array $dims, int $limite): array
    {
        $rows = $this->baseQuery($fecha, $turno, $dims, 'modelo')
            ->select([
                DB::raw(self::MODELO_EXPR . ' as modelo'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->groupBy(DB::raw(self::MODELO_EXPR))
            ->orderByDesc('cantidad')
            ->limit($limite)
            ->get();

        return $rows->map(fn ($r) => [
            'modelo'   => $r->modelo,
            'cantidad' => (int) $r->cantidad,
        ])->all();
    }

    /**
     * Combinacion tipo+lado (ej 'BACK LH') del periodo. Se descartan filas donde
     * AMBAS columnas estan vacias (no aportan una etiqueta util); si ninguna fila
     * tiene datos utiles, devuelve [] (el front no dibuja el bloque). No tiene
     * dimension propia en el filtrado cruzado: aplica TODOS los filtros.
     *
     * @param array<string,string> $dims
     */
    private function porTipoLado(?Carbon $fecha, ?string $turno, array $dims): array
    {
        $rows = $this->baseQuery($fecha, $turno, $dims)
            ->select([
                DB::raw(self::TIPO_EXPR . ' as tipo_n'),
                DB::raw(self::LADO_EXPR . ' as lado_n'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->whereRaw('(' . self::TIPO_EXPR . ' IS NOT NULL OR ' . self::LADO_EXPR . ' IS NOT NULL)')
            ->groupBy(DB::raw(self::TIPO_EXPR), DB::raw(self::LADO_EXPR))
            ->get();

        // Combino tipo_n/lado_n en una etiqueta y reagrupo en PHP por si dos
        // combinaciones distintas a nivel DB colapsan en la misma etiqueta final.
        $porEtiqueta = [];
        foreach ($rows as $r) {
            $etiqueta = trim(implode(' ', array_filter([$r->tipo_n, $r->lado_n])));
            if ($etiqueta === '') {
                continue;
            }
            $porEtiqueta[$etiqueta] = ($porEtiqueta[$etiqueta] ?? 0) + (int) $r->cantidad;
        }

        arsort($porEtiqueta);

        $out = [];
        foreach ($porEtiqueta as $etiqueta => $cantidad) {
            $out[] = ['etiqueta' => $etiqueta, 'cantidad' => $cantidad];
        }

        return $out;
    }

    /**
     * Todos los puestos con datos en el periodo (puesto_nombre normalizado), cantidad desc.
     * Ignora el filtro 'puesto' (dimension propia): ver nota en topDefectos().
     *
     * @param array<string,string> $dims
     */
    private function porPuesto(?Carbon $fecha, ?string $turno, array $dims): array
    {
        $rows = $this->baseQuery($fecha, $turno, $dims, 'puesto')
            ->select([
                DB::raw(self::PUESTO_EXPR . ' as puesto'),
                DB::raw('COUNT(*) as cantidad'),
            ])
            ->groupBy(DB::raw(self::PUESTO_EXPR))
            ->orderByDesc('cantidad')
            ->get();

        return $rows->map(fn ($r) => [
            'puesto'   => $r->puesto,
            'cantidad' => (int) $r->cantidad,
        ])->all();
    }

    /**
     * Todos los operarios del periodo con su total y cuantos de sus defectos
     * siguen PENDIENTE, cantidad desc. Misma regla que topOperarios(): ignora el
     * filtro 'operario' (dimension propia).
     *
     * @param array<string,string> $dims
     */
    private function operarios(?Carbon $fecha, ?string $turno, array $dims): array
    {
        $rows = $this->baseQuery($fecha, $turno, $dims, 'operario')
            ->select([
                DB::raw(self::OPERARIO_EXPR . ' as nombre'),
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM(CASE WHEN ' . self::ESTADO_EXPR . " = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes"),
            ])
            ->groupBy(DB::raw(self::OPERARIO_EXPR))
            ->orderByDesc('cantidad')
            ->get();

        return $rows->map(fn ($r) => [
            'nombre'     => $r->nombre,
            'cantidad'   => (int) $r->cantidad,
            'pendientes' => (int) $r->pendientes,
        ])->all();
    }
}
