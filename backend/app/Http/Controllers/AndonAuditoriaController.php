<?php

namespace App\Http\Controllers;

use App\Models\EpEtiqueta;
use App\Models\EpEtiquetasEventos;
use App\Models\FallasInformadas;
use App\Models\Partes;
use App\Models\PlanLinea;
use App\Models\User;
use DateInterval;
use DateTime;
use Illuminate\Http\Request;

class AndonAuditoriaController extends Controller {

    /**
     * Redondeo distribuido por resto mayor (mismo criterio de HoraHoraProduccionController).
     */
    private function roundDistributed(array $valores, string $modo = 'UP'): array {
        $map = [
            'UP'   => PHP_ROUND_HALF_UP,
            'DOWN' => PHP_ROUND_HALF_DOWN,
            'EVEN' => PHP_ROUND_HALF_EVEN,
            'ODD'  => PHP_ROUND_HALF_ODD,
        ];
        $mode = $map[strtoupper($modo)] ?? PHP_ROUND_HALF_UP;

        $n = count($valores);
        if ($n === 0) return [];

        $floors = [];
        $rema   = [];
        $sumFloors = 0;
        $sum = 0.0;

        foreach ($valores as $i => $v) {
            $f = (int) floor($v);
            $r = $v - $f;
            $floors[$i] = $f;
            $rema[$i] = $r;
            $sumFloors += $f;
            $sum += $v;
        }

        $target = (int) round($sum, 0, $mode);
        $faltan = $target - $sumFloors;

        if ($faltan <= 0) {
            if ($faltan < 0) {
                $idx = array_keys($valores);
                usort($idx, function ($a, $b) use ($rema, $valores) {
                    if ($rema[$a] == $rema[$b]) {
                        if ($valores[$a] == $valores[$b]) return $a <=> $b;
                        return $valores[$a] <=> $valores[$b];
                    }
                    return $rema[$a] <=> $rema[$b];
                });

                $quita = min(-$faltan, count($idx));
                for ($k = 0; $k < $quita; $k++) {
                    $i = $idx[$k];
                    $floors[$i] = max(0, $floors[$i] - 1);
                }
            }
            return array_values($floors);
        }

        $idx = array_keys($valores);
        usort($idx, function ($a, $b) use ($rema, $valores) {
            if ($rema[$a] == $rema[$b]) {
                if ($valores[$a] == $valores[$b]) return $a <=> $b;
                return $valores[$b] <=> $valores[$a];
            }
            return $rema[$b] <=> $rema[$a];
        });

        $asigna = min($faltan, count($idx));
        for ($k = 0; $k < $asigna; $k++) {
            $i = $idx[$k];
            $floors[$i] += 1;
        }

        return array_values($floors);
    }

    public function reporte(Request $request) {
        $fecha = $this->resolverFechaOperativa($request->query('fecha'));
        $turno = strtoupper(trim((string) $request->query('turno', '')));
        $turno = in_array($turno, ['M', 'T'], true) ? $turno : null;

        $lineasFiltro = $this->parseIntCsv($request->query('linea'));
        $operadorId = intval($request->query('operador', 0)) ?: null;
        $estadoIntervalo = strtolower(trim((string) $request->query('estado_intervalo', 'todos')));
        $buscar = trim((string) $request->query('buscar', ''));
        $limite = intval($request->query('limite', 250));
        $limite = min(max($limite, 20), 1000);

        $planQuery = PlanLinea::select('linea_id', 'turno', 'hora_desde', 'hora_hasta', 'plan', 'orden')
            ->orderBy('linea_id')
            ->orderBy('turno')
            ->orderBy('orden');

        if (count($lineasFiltro) > 0) {
            $planQuery->whereIn('linea_id', $lineasFiltro);
        }

        if (!is_null($turno)) {
            $planQuery->where('turno', $turno);
        }

        $planRows = $planQuery->get();
        if ($planRows->isEmpty()) {
            return $this->setResponse([
                'filters' => [
                    'applied' => [
                        'fecha' => $fecha,
                        'turno' => $turno,
                        'linea' => count($lineasFiltro) > 0 ? implode(',', $lineasFiltro) : null,
                        'operador' => $operadorId,
                        'estado_intervalo' => $estadoIntervalo,
                        'buscar' => $buscar
                    ],
                    'options' => [
                        'lineas' => [],
                        'turnos' => [
                            ['value' => 'M', 'label' => 'Manana'],
                            ['value' => 'T', 'label' => 'Tarde']
                        ],
                        'operadores' => []
                    ]
                ],
                'resumen' => [],
                'intervalos' => [],
                'fundas' => [],
                'errores_scaneo' => [
                    'resumen' => [],
                    'ultimos' => []
                ],
                'top_operadores' => [],
                'defectos_por_estado' => []
            ]);
        }

        $intervalos = [];
        $intervalosPorLinea = [];
        $planTotal = 0;
        $minFecha = null;
        $maxFecha = null;

        foreach ($planRows as $row) {
            $intervalo = $this->armarIntervalo($fecha, intval($row->linea_id), (string) $row->turno, (string) $row->hora_desde, (string) $row->hora_hasta, intval($row->plan));
            if (is_null($intervalo)) {
                continue;
            }

            $planTotal += intval($intervalo['plan']);
            if (is_null($minFecha) || $intervalo['inicio_obj'] < $minFecha) {
                $minFecha = clone $intervalo['inicio_obj'];
            }
            if (is_null($maxFecha) || $intervalo['fin_obj'] > $maxFecha) {
                $maxFecha = clone $intervalo['fin_obj'];
            }

            if (!isset($intervalosPorLinea[$intervalo['linea']])) {
                $intervalosPorLinea[$intervalo['linea']] = [];
            }
            $intervalosPorLinea[$intervalo['linea']][] = $intervalo;
            $intervalos[] = $intervalo;
        }

        if (is_null($minFecha) || is_null($maxFecha)) {
            return $this->setResponse([], 'No se pudieron construir intervalos de plan para la fecha indicada', true);
        }

        $windowStart = (clone $minFecha)->sub(new DateInterval('PT90M'));
        $windowEnd = (clone $maxFecha)->add(new DateInterval('PT90M'));
        $lineasPlan = array_values(array_unique(array_map(fn($i) => $i['linea'], $intervalos)));

        $scansQuery = EpEtiqueta::selectRaw('id, qr, kanban, modelo, modelo_id, linea_real, hora_validacion, hora_validacion_carab, user_validacion, user_validacion_carab, created_at')
            ->whereIn('linea_real', $lineasPlan)
            ->where(function ($q) use ($windowStart, $windowEnd) {
                $q->whereBetween('hora_validacion', [$windowStart->format('Y-m-d H:i:s'), $windowEnd->format('Y-m-d H:i:s')])
                    ->orWhereBetween('hora_validacion_carab', [$windowStart->format('Y-m-d H:i:s'), $windowEnd->format('Y-m-d H:i:s')])
                    ->orWhereBetween('created_at', [$windowStart->format('Y-m-d H:i:s'), $windowEnd->format('Y-m-d H:i:s')]);
            });

        if (!is_null($operadorId)) {
            $scansQuery->where(function ($q) use ($operadorId) {
                $q->where('user_validacion', $operadorId)
                    ->orWhere('user_validacion_carab', $operadorId);
            });
        }

        if ($buscar !== '') {
            $scansQuery->where(function ($q) use ($buscar) {
                $q->where('qr', 'like', '%' . $buscar . '%')
                    ->orWhere('kanban', 'like', '%' . $buscar . '%')
                    ->orWhere('modelo', 'like', '%' . $buscar . '%');
            });
        }

        $scans = $scansQuery->get();
        $partesPorModelo = $this->obtenerPartesPorModeloDesdeScans($scans);

        $realTotal = 0;
        $realEtiquetasDentro = 0;
        $fueraIntervalo = 0;
        $sinTimestampPrincipal = 0;
        $enIntervaloPorAlternativo = 0;

        $fundas = [];
        $topOperadores = [];
        $userIds = [];
        $intervalosModelosCantidad = [];
        $intervaloIndexMap = [];
        $fueraIntervaloModelos = [];
        $sinTimestampModelos = [];

        foreach ($intervalos as $idx => $intervalo) {
            $intervalos[$idx]['real'] = 0;
            $intervalos[$idx]['real_etiquetas'] = 0;
            $intervalos[$idx]['real_fundas_decimal'] = 0;
            $intervalos[$idx]['diferencia'] = 0;
            $intervaloIndexMap[$this->intervaloKey(intval($intervalo['linea']), (string) $intervalo['turno'], (string) $intervalo['intervalo'])] = $idx;
        }

        foreach ($scans as $scan) {
            $linea = intval($scan->linea_real);
            $esLineaCaraB = $linea === 1;
            $modeloId = intval($scan->modelo_id ?? 0);
            $partesModelo = $this->resolverPartesModelo($partesPorModelo, $modeloId);

            $principalRaw = $esLineaCaraB ? $scan->hora_validacion_carab : $scan->hora_validacion;
            $alternativoRaw = $esLineaCaraB ? $scan->hora_validacion : $scan->hora_validacion_carab;

            $principalTime = $this->parseFecha($principalRaw);
            $alternativoTime = $this->parseFecha($alternativoRaw);

            $principalUser = $esLineaCaraB ? $scan->user_validacion_carab : $scan->user_validacion;
            $alternativoUser = $esLineaCaraB ? $scan->user_validacion : $scan->user_validacion_carab;
            $operador = $principalUser ?: $alternativoUser;

            if (!is_null($operador)) {
                $userIds[] = intval($operador);
                if (!isset($topOperadores[$operador])) {
                    $topOperadores[$operador] = [
                        'operador_id' => intval($operador),
                        'cantidad' => 0,
                        'dentro_intervalo' => 0,
                        'fuera_intervalo' => 0
                    ];
                }
                $topOperadores[$operador]['cantidad'] += 1;
            }

            $intervaloPrincipal = $this->buscarIntervalo($linea, $principalTime, $intervalosPorLinea);
            $intervaloAlternativo = $this->buscarIntervalo($linea, $alternativoTime, $intervalosPorLinea);

            $estado = 'FUERA';
            $estadoVisual = 'FUERA PLAN';
            $motivo = 'Escaneada fuera de los intervalos del plan';
            $intervaloAsignado = null;

            if (!is_null($intervaloPrincipal)) {
                $estado = 'DENTRO';
                $estadoVisual = 'DENTRO PLAN';
                $motivo = 'Dentro de intervalo por timestamp principal';
                $intervaloAsignado = $intervaloPrincipal['intervalo'];

                $key = $this->intervaloKey($linea, (string) $intervaloPrincipal['turno'], (string) $intervaloPrincipal['intervalo']);
                if (isset($intervaloIndexMap[$key])) {
                    $idx = $intervaloIndexMap[$key];
                    $intervalos[$idx]['real_etiquetas'] += 1;

                    if (!isset($intervalosModelosCantidad[$key])) {
                        $intervalosModelosCantidad[$key] = [];
                    }
                    if (!isset($intervalosModelosCantidad[$key][$modeloId])) {
                        $intervalosModelosCantidad[$key][$modeloId] = 0;
                    }
                    $intervalosModelosCantidad[$key][$modeloId] += 1;
                }

                $realEtiquetasDentro += 1;
                if (!is_null($operador) && isset($topOperadores[$operador])) {
                    $topOperadores[$operador]['dentro_intervalo'] += 1;
                }
            } else {
                $fueraIntervalo += 1;
                if (!isset($fueraIntervaloModelos[$modeloId])) {
                    $fueraIntervaloModelos[$modeloId] = 0;
                }
                $fueraIntervaloModelos[$modeloId] += 1;

                if (!is_null($operador) && isset($topOperadores[$operador])) {
                    $topOperadores[$operador]['fuera_intervalo'] += 1;
                }

                if (is_null($principalTime)) {
                    $sinTimestampPrincipal += 1;
                    if (!isset($sinTimestampModelos[$modeloId])) {
                        $sinTimestampModelos[$modeloId] = 0;
                    }
                    $sinTimestampModelos[$modeloId] += 1;

                    $estado = 'SIN_TIMESTAMP_PRINCIPAL';
                    $estadoVisual = 'SIN CARAB';
                    $motivo = 'Sin timestamp principal para esta linea';

                    if (!is_null($intervaloAlternativo)) {
                        $enIntervaloPorAlternativo += 1;
                        $motivo = 'Solo aparece en timestamp alternativo';
                        $intervaloAsignado = $intervaloAlternativo['intervalo'];
                    }
                } else if (!is_null($intervaloAlternativo)) {
                    $motivo = 'Fuera por timestamp principal, dentro por alternativo';
                    $intervaloAsignado = $intervaloAlternativo['intervalo'];
                    $enIntervaloPorAlternativo += 1;
                }
            }

            $timestampRef = $principalTime ?: $alternativoTime ?: $this->parseFecha($scan->created_at);
            $carabEstado = $linea === 1 ? (!is_null($principalTime) ? 'CON_CARAB' : 'SIN_CARAB') : 'NO_APLICA';

            $fundas[] = [
                'id' => intval($scan->id),
                'linea' => $linea,
                'modelo' => $scan->modelo,
                'modelo_id' => $modeloId > 0 ? $modeloId : null,
                'partes_por_funda' => $partesModelo,
                'qr' => $scan->qr,
                'kanban' => $scan->kanban,
                'estado_intervalo' => $estado,
                'estado_visual' => $estadoVisual,
                'carab_estado' => $carabEstado,
                'motivo' => $motivo,
                'intervalo_relacionado' => $intervaloAsignado,
                'timestamp_referencia' => !is_null($timestampRef) ? $timestampRef->format('Y-m-d H:i:s') : null,
                'timestamp_principal' => !is_null($principalTime) ? $principalTime->format('Y-m-d H:i:s') : null,
                'timestamp_alternativo' => !is_null($alternativoTime) ? $alternativoTime->format('Y-m-d H:i:s') : null,
                'operador_id' => !is_null($operador) ? intval($operador) : null
            ];
        }

        foreach ($intervalos as $idx => $intervalo) {
            $key = $this->intervaloKey(intval($intervalo['linea']), (string) $intervalo['turno'], (string) $intervalo['intervalo']);
            $fundasDecimales = 0;

            if (isset($intervalosModelosCantidad[$key])) {
                foreach ($intervalosModelosCantidad[$key] as $modeloId => $cantidadEtiquetas) {
                    $divisor = $this->resolverPartesModelo($partesPorModelo, intval($modeloId));
                    $fundasDecimales += (intval($cantidadEtiquetas) / $divisor);
                }
            }

            $fundasRedondeadas = $fundasDecimales > 0 ? $this->roundDistributed([$fundasDecimales])[0] : 0;
            $intervalos[$idx]['real_fundas_decimal'] = round($fundasDecimales, 2);
            $intervalos[$idx]['real'] = $fundasRedondeadas;
            $intervalos[$idx]['diferencia'] = intval($fundasRedondeadas) - intval($intervalo['plan']);
            $realTotal += intval($fundasRedondeadas);
        }

        $fueraIntervaloFundasDec = $this->calcularFundasEquivalentes($fueraIntervaloModelos, $partesPorModelo);
        $sinTimestampFundasDec = $this->calcularFundasEquivalentes($sinTimestampModelos, $partesPorModelo);
        $fueraIntervaloFundasAprox = $fueraIntervaloFundasDec > 0 ? $this->roundDistributed([$fueraIntervaloFundasDec])[0] : 0;
        $sinTimestampFundasAprox = $sinTimestampFundasDec > 0 ? $this->roundDistributed([$sinTimestampFundasDec])[0] : 0;

        usort($fundas, function ($a, $b) {
            return strcmp((string) ($b['timestamp_referencia'] ?? ''), (string) ($a['timestamp_referencia'] ?? ''));
        });

        if ($estadoIntervalo !== 'todos') {
            $fundas = array_values(array_filter($fundas, function ($item) use ($estadoIntervalo) {
                if ($estadoIntervalo === 'dentro') return $item['estado_intervalo'] === 'DENTRO';
                if ($estadoIntervalo === 'fuera') return $item['estado_intervalo'] === 'FUERA';
                if ($estadoIntervalo === 'sin_timestamp') return $item['estado_intervalo'] === 'SIN_TIMESTAMP_PRINCIPAL';
                return true;
            }));
        }

        $fundas = array_slice($fundas, 0, $limite);

        $eventosQuery = EpEtiquetasEventos::query()
            ->leftJoin('ep_etiquetas', 'ep_etiquetas_eventos.qr_etiqueta', '=', 'ep_etiquetas.qr')
            ->selectRaw('ep_etiquetas_eventos.id, ep_etiquetas_eventos.kanban, ep_etiquetas_eventos.qr_etiqueta, ep_etiquetas_eventos.user_id, ep_etiquetas_eventos.evento, ep_etiquetas_eventos.info_adi, ep_etiquetas_eventos.created_at, ep_etiquetas.linea_real')
            ->whereBetween('ep_etiquetas_eventos.created_at', [$windowStart->format('Y-m-d H:i:s'), $windowEnd->format('Y-m-d H:i:s')]);

        if (count($lineasPlan) > 0) {
            $eventosQuery->whereIn('ep_etiquetas.linea_real', $lineasPlan);
        }

        if (!is_null($operadorId)) {
            $eventosQuery->where('ep_etiquetas_eventos.user_id', $operadorId);
        }

        if ($buscar !== '') {
            $eventosQuery->where(function ($q) use ($buscar) {
                $q->where('ep_etiquetas_eventos.qr_etiqueta', 'like', '%' . $buscar . '%')
                    ->orWhere('ep_etiquetas_eventos.kanban', 'like', '%' . $buscar . '%')
                    ->orWhere('ep_etiquetas_eventos.evento', 'like', '%' . $buscar . '%');
            });
        }

        $eventos = $eventosQuery->orderBy('ep_etiquetas_eventos.created_at', 'desc')->limit($limite)->get();
        $erroresMap = [];
        $eventosRows = [];

        foreach ($eventos as $e) {
            if (!is_null($e->user_id)) {
                $userIds[] = intval($e->user_id);
            }

            $eventoNormalizado = $this->normalizarEvento((string) $e->evento);
            $eventoFecha = $this->parseFecha($e->created_at);
            if (!isset($erroresMap[$eventoNormalizado])) {
                $erroresMap[$eventoNormalizado] = 0;
            }
            $erroresMap[$eventoNormalizado] += 1;

            $eventosRows[] = [
                'id' => intval($e->id),
                'linea' => !is_null($e->linea_real) ? intval($e->linea_real) : null,
                'evento' => (string) $e->evento,
                'evento_resumen' => $eventoNormalizado,
                'qr' => $e->qr_etiqueta,
                'kanban' => $e->kanban,
                'operador_id' => !is_null($e->user_id) ? intval($e->user_id) : null,
                'info_adi' => $e->info_adi,
                'created_at' => !is_null($eventoFecha) ? $eventoFecha->format('Y-m-d H:i:s') : null
            ];
        }

        arsort($erroresMap);
        $erroresResumen = [];
        foreach ($erroresMap as $evento => $cantidad) {
            $erroresResumen[] = [
                'evento' => $evento,
                'cantidad' => $cantidad
            ];
        }

        $defectosQuery = FallasInformadas::selectRaw('estado, count(*) as cantidad')
            ->whereIn('linea', $lineasPlan)
            ->whereBetween('created_at', [$windowStart->format('Y-m-d H:i:s'), $windowEnd->format('Y-m-d H:i:s')])
            ->groupBy('estado');

        if (!is_null($operadorId)) {
            $defectosQuery->where('user_operacion_id', $operadorId);
        }

        $defectosEstado = $defectosQuery->get()->map(function ($item) {
            return [
                'estado' => (string) $item->estado,
                'cantidad' => intval($item->cantidad)
            ];
        })->toArray();

        $defectosMap = [];
        $defectosTotal = 0;
        foreach ($defectosEstado as $defecto) {
            $defectosMap[$defecto['estado']] = $defecto['cantidad'];
            $defectosTotal += $defecto['cantidad'];
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        $users = [];
        if (count($userIds) > 0) {
            $users = User::selectRaw('id, isnull(email, name) as nombre')
                ->whereIn('id', $userIds)
                ->get()
                ->keyBy('id')
                ->toArray();
        }

        $topOperadoresRows = array_values($topOperadores);
        foreach ($topOperadoresRows as $idx => $op) {
            $nombre = null;
            if (isset($users[$op['operador_id']])) {
                $nombre = $users[$op['operador_id']]['nombre'];
            }
            $topOperadoresRows[$idx]['operador'] = $nombre;
        }

        usort($topOperadoresRows, fn($a, $b) => intval($b['cantidad']) <=> intval($a['cantidad']));
        $topOperadoresRows = array_slice($topOperadoresRows, 0, 12);

        $operadorOptions = $topOperadoresRows;
        foreach ($operadorOptions as $idx => $op) {
            $operadorOptions[$idx] = [
                'value' => intval($op['operador_id']),
                'label' => $op['operador'] ? $op['operador'] : ('Operador ' . $op['operador_id'])
            ];
        }

        $lineaOptions = array_values(array_map(fn($l) => [
            'value' => intval($l),
            'label' => 'M' . intval($l)
        ], $lineasPlan));

        $lineaOptions = array_values(array_unique($lineaOptions, SORT_REGULAR));

        $diagnostico = 'OK';
        if ($sinTimestampPrincipal > 0 && $sinTimestampPrincipal >= $fueraIntervalo) {
            $diagnostico = 'SIN_CARAB';
        } else if ($fueraIntervalo > 0) {
            $diagnostico = 'FUERA_DE_HORARIO';
        } else if (($realTotal - $planTotal) < 0) {
            $diagnostico = 'DEFICIT_PRODUCTIVO';
        }

        $resumen = [
            'plan_total' => $planTotal,
            'real_dentro_intervalo' => $realTotal,
            'real_dentro_intervalo_etiquetas' => $realEtiquetasDentro,
            'diferencia' => $realTotal - $planTotal,
            'fundas_fuera_intervalo' => $fueraIntervalo,
            'fundas_fuera_intervalo_equivalente_fundas' => round($fueraIntervaloFundasDec, 2),
            'fundas_fuera_intervalo_equivalente_fundas_aprox' => $fueraIntervaloFundasAprox,
            'fundas_sin_timestamp_principal' => $sinTimestampPrincipal,
            'fundas_sin_timestamp_principal_equivalente_fundas' => round($sinTimestampFundasDec, 2),
            'fundas_sin_timestamp_principal_equivalente_fundas_aprox' => $sinTimestampFundasAprox,
            'en_intervalo_por_timestamp_alternativo' => $enIntervaloPorAlternativo,
            'errores_scaneo' => count($eventosRows),
            'defectos_total' => $defectosTotal,
            'defectos_pendiente' => intval($defectosMap['PENDIENTE'] ?? 0),
            'defectos_retrabajado' => intval($defectosMap['RETRABAJADO'] ?? 0),
            'defectos_scrap' => intval($defectosMap['SCRAP'] ?? 0),
            'diagnostico_principal' => $diagnostico,
            'ventana_desde' => $windowStart->format('Y-m-d H:i:s'),
            'ventana_hasta' => $windowEnd->format('Y-m-d H:i:s')
        ];

        foreach ($intervalos as $idx => $it) {
            $intervalos[$idx]['diferencia'] = intval($it['real']) - intval($it['plan']);
            unset($intervalos[$idx]['inicio_obj']);
            unset($intervalos[$idx]['fin_obj']);
        }

        return $this->setResponse([
            'filters' => [
                'applied' => [
                    'fecha' => $fecha,
                    'turno' => $turno,
                    'linea' => count($lineasFiltro) > 0 ? implode(',', $lineasFiltro) : null,
                    'operador' => $operadorId,
                    'estado_intervalo' => $estadoIntervalo,
                    'buscar' => $buscar
                ],
                'options' => [
                    'lineas' => $lineaOptions,
                    'turnos' => [
                        ['value' => 'M', 'label' => 'Manana'],
                        ['value' => 'T', 'label' => 'Tarde']
                    ],
                    'operadores' => $operadorOptions
                ]
            ],
            'resumen' => $resumen,
            'intervalos' => $intervalos,
            'fundas' => $this->inyectarNombreOperador($fundas, $users),
            'errores_scaneo' => [
                'resumen' => $erroresResumen,
                'ultimos' => $this->inyectarNombreOperador($eventosRows, $users)
            ],
            'top_operadores' => $topOperadoresRows,
            'defectos_por_estado' => $defectosEstado,
            'meta' => [
                'actualizado_en' => date('Y-m-d H:i:s'),
                'lineas_plan' => $lineasPlan
            ]
        ]);
    }

    private function inyectarNombreOperador(array $rows, array $users): array {
        foreach ($rows as $idx => $row) {
            $opId = $row['operador_id'] ?? null;
            $rows[$idx]['operador'] = null;
            if (!is_null($opId) && isset($users[$opId])) {
                $rows[$idx]['operador'] = $users[$opId]['nombre'];
            }
        }
        return $rows;
    }

    private function resolverFechaOperativa(?string $fecha): string {
        if (!is_null($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $fecha;
        }

        $now = new DateTime();
        $hora = intval($now->format('H'));
        $min = intval($now->format('i'));

        if (($hora >= 0 && $hora < 6) || ($hora === 6 && $min < 10)) {
            $now->sub(new DateInterval('P1D'));
        }

        return $now->format('Y-m-d');
    }

    private function parseIntCsv($raw): array {
        if (is_null($raw)) return [];

        $items = explode(',', (string) $raw);
        $values = [];
        foreach ($items as $item) {
            $item = trim($item);
            if ($item === '') continue;
            $values[] = intval($item);
        }

        $values = array_values(array_filter(array_unique($values), fn($n) => $n > 0));
        sort($values);

        return $values;
    }

    private function armarIntervalo(string $fecha, int $linea, string $turno, string $horaDesdeRaw, string $horaHastaRaw, int $plan): ?array {
        $desde = substr($horaDesdeRaw, 0, 8);
        $hasta = substr($horaHastaRaw, 0, 8);

        if (!$this->esHoraValida($desde) || !$this->esHoraValida($hasta)) {
            return null;
        }

        $startObj = DateTime::createFromFormat('Y-m-d H:i:s', $fecha . ' ' . $desde);
        $endObj = DateTime::createFromFormat('Y-m-d H:i:s', $fecha . ' ' . $hasta);
        if (!$startObj || !$endObj) {
            return null;
        }

        if ($turno === 'T' && $desde === '00:00:00') {
            $startObj->add(new DateInterval('P1D'));
            $endObj->add(new DateInterval('P1D'));
        }

        return [
            'linea' => $linea,
            'turno' => $turno,
            'intervalo' => substr($desde, 0, 5) . ' - ' . substr($hasta, 0, 5),
            'inicio' => $startObj->format('Y-m-d H:i:s'),
            'fin' => $endObj->format('Y-m-d H:i:s'),
            'inicio_obj' => $startObj,
            'fin_obj' => $endObj,
            'plan' => $plan,
            'real' => 0,
            'diferencia' => 0
        ];
    }

    private function esHoraValida(string $hora): bool {
        return preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora) === 1;
    }

    private function parseFecha($value): ?DateTime {
        if (is_null($value) || $value === '') {
            return null;
        }

        try {
            return new DateTime((string) $value);
        } catch (\Throwable $th) {
            return null;
        }
    }

    private function buscarIntervalo(int $linea, ?DateTime $fecha, array $intervalosPorLinea): ?array {
        if (is_null($fecha)) {
            return null;
        }

        if (!isset($intervalosPorLinea[$linea])) {
            return null;
        }

        foreach ($intervalosPorLinea[$linea] as $intervalo) {
            if ($fecha >= $intervalo['inicio_obj'] && $fecha <= $intervalo['fin_obj']) {
                return $intervalo;
            }
        }

        return null;
    }

    private function intervaloKey(int $linea, string $turno, string $intervalo): string {
        return $linea . '|' . trim($turno) . '|' . trim($intervalo);
    }

    private function obtenerPartesPorModeloDesdeScans($scans): array {
        $modeloIds = $scans->pluck('modelo_id')
            ->filter(fn($id) => intval($id) > 0)
            ->map(fn($id) => intval($id))
            ->unique()
            ->values()
            ->toArray();

        $result = [];
        if (count($modeloIds) === 0) {
            return $result;
        }

        $rows = Partes::selectRaw('modelo_id, count(*) as cantidad')
            ->whereIn('modelo_id', $modeloIds)
            ->where(function ($q) {
                $q->where('tipo_id', 1)
                    ->orWhere('tipo_id', 2)
                    ->orWhere('tipo_id', 9);
            })
            ->groupBy('modelo_id')
            ->get();

        foreach ($rows as $row) {
            $cantidad = intval($row->cantidad);
            $result[intval($row->modelo_id)] = $cantidad > 0 ? $cantidad : 4;
        }

        return $result;
    }

    private function resolverPartesModelo(array $partesPorModelo, int $modeloId): int {
        if ($modeloId > 0 && isset($partesPorModelo[$modeloId]) && intval($partesPorModelo[$modeloId]) > 0) {
            return intval($partesPorModelo[$modeloId]);
        }

        return 4;
    }

    private function calcularFundasEquivalentes(array $cantidadesPorModelo, array $partesPorModelo): float {
        $total = 0.0;
        foreach ($cantidadesPorModelo as $modeloId => $cantidadEtiquetas) {
            $divisor = $this->resolverPartesModelo($partesPorModelo, intval($modeloId));
            $total += (intval($cantidadEtiquetas) / $divisor);
        }
        return $total;
    }

    private function normalizarEvento(string $evento): string {
        $evento = trim($evento);
        if ($evento === '') {
            return 'SIN_DESCRIPCION';
        }

        $partes = explode(' - ', $evento);
        return trim($partes[0]);
    }
}
