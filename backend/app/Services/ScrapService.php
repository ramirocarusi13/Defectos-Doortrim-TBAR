<?php

namespace App\Services;

use App\Models\FallasInformadas;
use App\Models\MaterialesPiezas;
use App\Models\Partes;
use App\Models\Piezas;
use App\Models\Scrap;

class ScrapService {

    private function calcularKgsPieza(Piezas $pieza, MaterialesPiezas $material): float {

        if (!$pieza || !$material) {
            return 0;
        }

        $area = floatval($pieza?->area);
        if ($area <= 0) return 0;

        $densidad = $material?->densidad;
        if ($densidad <= 0) return 0;

        $area = $area / 1000000;
        $pesoKg = ($area * $densidad);

        return $pesoKg;
    }

    public function generaScrapDeFundaCompleta(Partes $parte, int $userId, int $lineaId, int|null $fallaId) {

        $piezas = Piezas::where('parte_id', $parte->id)->get();

        foreach ($piezas as $pieza) {
            $this->registraScrapPieza(
                $userId,
                $pieza->material_pieza_id,
                'Funda completa',
                $lineaId,
                '',
                '',
                $pieza->id,
                $fallaId
            );
        }

        return true;
    }


    public function registraScrapPieza(int $userId, int $materialId, string $motivo, int $lineaId, string $tipoLinea, string $sector, int $piezaId, int|null $fallaId) {

        $pieza = Piezas::where('id', $piezaId)->first();
        $material = MaterialesPiezas::where('id', $materialId)->first();
        $tipoLinea = empty($tipoLinea) ? 'M' : strtoupper(trim($tipoLinea));
        $turno = getTurnoActual();
        $codigoFallaId = $fallaId ? FallasInformadas::where('id', $fallaId)->value('falla_id') : null;

        $pesoEnKg = $this->calcularKgsPieza($pieza, $material);

        Scrap::create([
            'user_id'       => $userId,
            'material_id'   => $materialId,
            'kg'            => $pesoEnKg,
            'precio'        => '',
            'motivo'        => $motivo,
            'linea_id'      => $lineaId,
            'tipo_linea'    => $tipoLinea,
            'sector'        => $sector,
            'turno'         => $turno,
            'falla_id'      => $codigoFallaId,
            'defecto_id'    => $fallaId
        ]);
    }

    public function filtrar(object $filtros): array {

        $material = $filtros->material_id ?? $filtros->material ?? null;
        $linea = $filtros->linea_id ?? $filtros->linea ?? null;
        $defecto = $filtros->defecto_id ?? $filtros->defecto ?? null;
        $user = $filtros->user_id ?? $filtros->usuario_id ?? $filtros->usuario ?? $filtros->user ?? null;
        $tipoLinea = $filtros->tipo_linea ?? $filtros->tipoLinea ?? null;
        $fechaDesde = $filtros->fecha_desde ?? $filtros->desde ?? null;
        $fechaHasta = $filtros->fecha_hasta ?? $filtros->hasta ?? null;

        if (!empty($tipoLinea)) {
            $tipoLinea = strtoupper(trim($tipoLinea));

            if ($tipoLinea === 'MAIN') {
                $tipoLinea = 'M';
            } else if ($tipoLinea === 'SUB') {
                $tipoLinea = 'S';
            }
        }

        $scraps = Scrap::query()
            ->leftJoin('materiales_piezas as material', 'material.id', '=', 'scraps.material_id')
            ->leftJoin('lineas as linea', 'linea.id', '=', 'scraps.linea_id')
            ->leftJoin('fallas_informadas as falla_informada', 'falla_informada.id', '=', 'scraps.defecto_id')
            ->leftJoin('codigo_fallas as codigo_falla', function ($join) {
                $join->on('codigo_falla.id', '=', 'scraps.falla_id')
                    ->orOn('codigo_falla.id', '=', 'falla_informada.falla_id');
            })
            ->leftJoin('users as usuario', 'usuario.id', '=', 'scraps.user_id')
            ->select([
                'scraps.*',
                'usuario.name as usuario_user',
                'usuario.email as usuario_nombre',
                'material.codigo as material_codigo',
                'material.codigo_interno as material_codigo_interno',
                'material.nombre as material_nombre',
                'linea.codigo as linea_codigo',
                'linea.nombre as linea_nombre',
                'falla_informada.qr as defecto_qr',
                'codigo_falla.nombre as defecto_nombre'
            ])
            ->selectRaw("COALESCE(scraps.falla_id, falla_informada.falla_id) as codigo_falla_id")
            ->selectRaw("COALESCE(scraps.turno, falla_informada.turno) as turno")
            ->selectRaw("CASE WHEN scraps.tipo_linea IS NULL OR scraps.tipo_linea = '' THEN 'M' ELSE scraps.tipo_linea END as tipo_linea")
            ->when(!empty($material), function ($q) use ($material) {
                if (is_numeric($material)) {
                    $q->where('scraps.material_id', intval($material));
                    return;
                }

                $q->where(function ($query) use ($material) {
                    $query->where('material.nombre', 'like', '%' . $material . '%')
                        ->orWhere('material.codigo', 'like', '%' . $material . '%')
                        ->orWhere('material.codigo_interno', 'like', '%' . $material . '%');
                });
            })
            ->when(!empty($linea), function ($q) use ($linea) {
                if (is_numeric($linea)) {
                    $q->where('scraps.linea_id', intval($linea));
                    return;
                }

                $q->where(function ($query) use ($linea) {
                    $query->where('linea.nombre', 'like', '%' . $linea . '%')
                        ->orWhere('linea.codigo', 'like', '%' . $linea . '%');
                });
            })
            ->when(!empty($defecto), function ($q) use ($defecto) {
                if (is_numeric($defecto)) {
                    $q->where(function ($query) use ($defecto) {
                        $query->where('scraps.falla_id', intval($defecto))
                            ->orWhere('scraps.defecto_id', intval($defecto))
                            ->orWhere('falla_informada.falla_id', intval($defecto));
                    });
                    return;
                }

                $q->where('codigo_falla.nombre', 'like', '%' . $defecto . '%');
            })
            ->when(!empty($user), function ($q) use ($user) {
                $q->where('scraps.user_id', intval($user));
            })
            ->when(!empty($tipoLinea), function ($q) use ($tipoLinea) {
                if ($tipoLinea === 'M') {
                    $q->where(function ($query) {
                        $query->where('scraps.tipo_linea', 'M')
                            ->orWhereNull('scraps.tipo_linea')
                            ->orWhere('scraps.tipo_linea', '');
                    });
                    return;
                }

                $q->where('scraps.tipo_linea', $tipoLinea);
            })
            ->when(!empty($fechaDesde) || !empty($fechaHasta), function ($q) use ($fechaDesde, $fechaHasta) {
                $timestampDesde = !empty($fechaDesde) ? strtotime($fechaDesde) : false;
                $timestampHasta = !empty($fechaHasta) ? strtotime($fechaHasta) : false;

                $fechaDesdeFormateada = $timestampDesde !== false ? date('Y-m-d', $timestampDesde) . ' 00:00:01' : '1900-01-01 00:00:00';
                $fechaHastaFormateada = $timestampHasta !== false ? date('Y-m-d', $timestampHasta) . ' 23:59:59' : '2999-12-31 23:59:59';

                $q->whereBetween('scraps.created_at', [
                    $fechaDesdeFormateada,
                    $fechaHastaFormateada
                ]);
            })
            ->orderBy('scraps.created_at', 'DESC')
            ->get();

        return $scraps->toArray();
    }
}
