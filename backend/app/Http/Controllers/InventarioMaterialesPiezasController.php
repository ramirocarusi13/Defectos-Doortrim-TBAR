<?php

namespace App\Http\Controllers;

use App\Http\Roles;
use App\Http\TipoMaterial;
use App\Models\InventarioMaterialesPiezas;
use App\Models\InventarioMaterialesResultadoOverride;
use App\Models\MaterialesPiezas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InventarioMaterialesPiezasController extends Controller {

    private $limiteCantidad = 10;

    public function index() {
        $data = MaterialesPiezas::withSum('inventario', 'cantidad')
            // ->where('orden', '>', 0)
            ->where('tipo', TipoMaterial::TELA)
            ->orderBy('orden', 'ASC')
            ->get()->toArray();

        // Log::alert($data);

        return $this->setResponse($data);
    }

    public function getInventarioPorFecha(Request $request) {

        $fechaD = date($request->fecha);
        $detalle = $request->detailed == 1;
        $sector = $request->sector;

        try {
            $tipo = $request->tipo;

            if ($tipo == '') {
                $tipo = TipoMaterial::TELA;
            }
        } catch (\Throwable $th) {
            $tipo = TipoMaterial::TELA;
        }

        if ($detalle) {
            $data = MaterialesPiezas::with(['inventario.user', 'inventario' => function ($q) use ($fechaD, $sector) {
                $q->whereBetween('created_at', [$fechaD . " 00:00:01", $fechaD . " 23:59:59"]);
                $q->when(!empty($sector), function ($w) use ($sector) {
                    $w->where('sector', $sector);
                });
            }])
                ->withSum(['inventario' => function ($q) use ($fechaD, $sector) {
                    $q->whereBetween('created_at', [$fechaD . " 00:00:01", $fechaD . " 23:59:59"]);
                    $q->when(!empty($sector), function ($w) use ($sector) {
                        $w->where('sector', $sector);
                    });
                }], 'cantidad')

                ->where('tipo', $tipo)
                // ->where('orden', '>', 0)
                ->orderBy('orden', 'ASC')
                ->get()->toArray();
        } else {
            $data = MaterialesPiezas::withSum(['inventario' => function ($q) use ($fechaD, $sector) {
                $q->whereBetween('created_at', [$fechaD . " 00:00:01", $fechaD . " 23:59:59"]);
                $q->when(!empty($sector), function ($w) use ($sector) {
                    $w->where('sector', $sector);
                });
            }], 'cantidad')
                ->where('tipo', $tipo)
                ->orderBy('orden', 'ASC')
                ->get()->toArray();
        }

        // Log::alert($data);

        return $this->setResponse($this->applyResultadoOverrides($data, $fechaD));
    }

    public function getByMaterial(Request $request) {

        $fechaD = date($request->fecha);
        $materialId = $request->material;
        $userId = $request->userId;

        try {
            $tipo = $request->tipo;
        } catch (\Throwable $th) {
            $tipo = '';
        }

        //Verifico permisos del usuario, si esta autorizado a todo, y no manda usuario, muestro todo
        //Si no, solo muestro lo suyo

        $user = User::with(['rol'])->where('id',  auth()->guard('api')->user()->id)->first();

        if ($user->rol->id == Roles::ADMINISTRADOR || $user->rol->id == Roles::IT || $user->rol->id == Roles::DESARROLLO) {
            $data = InventarioMaterialesPiezas::with(['material' => function ($q) use ($tipo) {
                $q->when(!empty($tipo), function ($qr) use ($tipo) {
                    $qr->where('tipo', $tipo);
                });
            }, 'user'])
                ->where('material_id', $materialId)
                ->when(!empty($userId), function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->whereBetween('created_at', [$fechaD . " 00:00:01", $fechaD . " 23:59:59"])
                ->orderBy('created_at', 'DESC')
                ->get()->toArray();
        } else {
            //Si no esta autorizado siempre muestro solo lo suyo
            $data = InventarioMaterialesPiezas::with(['material' => function ($q) use ($tipo) {
                $q->when(!empty($tipo), function ($qr) use ($tipo) {
                    $qr->where('tipo', $tipo);
                });
            }, 'user'])
                ->where('material_id', $materialId)
                ->where('user_id', auth()->guard('api')->user()->id)
                ->whereBetween('created_at', [$fechaD . " 00:00:01", $fechaD . " 23:59:59"])
                ->orderBy('created_at', 'DESC')
                ->get()->toArray();
        }



        return $this->setResponse($data);
    }

    public function editarPesaje(Request $request, $id) {
        $cantidad = floatval($request->cantidad);

        $data = [
            'cantidad'      => $cantidad,
            'confirmado'    => $cantidad < $this->limiteCantidad
        ];

        try {
            InventarioMaterialesPiezas::where('id', $id)->update($data);
            return $this->setResponse([]);
        } catch (\Throwable $th) {
            Log::error("InventarioMaterialesPiezasController::editarPesaje : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas.");
        }
    }

    public function actualizarInventarioMaterialesResultado(Request $request, int $materialId)
    {
        $user = auth()->guard('api')->user();
        if (!$this->isDevUser($user)) {
            return $this->setResponse([], "Solo el usuario dev puede editar este resultado", true);
        }

        $validated = $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
            'subtotal_kg' => 'nullable|numeric',
            'densidad' => 'nullable|numeric',
            'total_m2' => 'nullable|numeric',
            'ancho' => 'nullable|numeric',
            'total_ml' => 'nullable|numeric',
        ]);

        $material = MaterialesPiezas::where('id', $materialId)->first();
        if (!$material) {
            return $this->setResponse([], "No existe el material indicado", true);
        }

        try {
            $override = InventarioMaterialesResultadoOverride::updateOrCreate(
                [
                    'material_id' => $materialId,
                    'fecha' => $validated['fecha'],
                ],
                [
                    'subtotal_kg' => array_key_exists('subtotal_kg', $validated) ? $validated['subtotal_kg'] : null,
                    'densidad' => array_key_exists('densidad', $validated) ? $validated['densidad'] : null,
                    'total_m2' => array_key_exists('total_m2', $validated) ? $validated['total_m2'] : null,
                    'ancho' => array_key_exists('ancho', $validated) ? $validated['ancho'] : null,
                    'total_ml' => array_key_exists('total_ml', $validated) ? $validated['total_ml'] : null,
                    'updated_by_user_id' => $user->id,
                ]
            );
        } catch (\Throwable $th) {
            Log::error("InventarioMaterialesPiezasController::actualizarInventarioMaterialesResultado : " . $th->getMessage());
            return $this->setResponse([], "No se pudo guardar el resultado. Verifique migraciones pendientes.", true);
        }

        return $this->setResponse([
            'id' => $override->id,
            'material_id' => $override->material_id,
            'fecha' => $override->fecha,
            'subtotal_kg' => $override->subtotal_kg,
            'densidad' => $override->densidad,
            'total_m2' => $override->total_m2,
            'ancho' => $override->ancho,
            'total_ml' => $override->total_ml,
            'updated_by_user_id' => $override->updated_by_user_id,
        ]);
    }

    public function getCurrentInventarioByUser($tipo) {


        $fechaD = date("Y-m-d");

        $data = InventarioMaterialesPiezas::with(['material' => function ($q) use ($tipo) {
            $q->where('tipo', $tipo);
        }, 'user'])
            ->where('user_id', auth()->guard('api')->user()->id)
            ->whereRelation('material', 'tipo', '=', $tipo)
            ->whereBetween('created_at', [$fechaD . " 00:00:01", $fechaD . " 23:59:59"])
            ->orderBy('id', 'DESC')
            ->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function store(Request $request) {

        // Log::alert($request);
        $confirmado = floatval($request->cantidad) < $this->limiteCantidad;
        $modelo = $request?->modelo;

        if (!empty($modelo)) {
            //ES POR MODELO Y ES CUERO
            $materiales = MaterialesPiezas::where('tipo', 'CUERO')->where('modelo', 'like', '%' . $modelo . '%')->get();
            foreach ($materiales as $material) {
                $data = [
                    'material_id'   => $material->id,
                    'user_id'       => auth()->guard('api')->user()->id,
                    'cantidad'      => floatval($request->cantidad),
                    'confirmado'    => true,
                    'sector'        => $request->sector,
                ];

                $creo = InventarioMaterialesPiezas::create($data);

                if (!$creo) {
                    return $this->setResponse([], "No se pudo grabar el pesaje", true);
                }
            }

            return $this->setResponse([]);
        }

        $data = [
            'material_id'   => $request->material['id'],
            'user_id'       => auth()->guard('api')->user()->id,
            'cantidad'      => floatval($request->cantidad),
            'confirmado'    => $confirmado,
            'sector'        => $request->sector,
        ];

        try {
            $creo = InventarioMaterialesPiezas::create($data);
            if ($creo) {
                return $this->setResponse([]);
            } else {
                return $this->setResponse([], "No se pudo grabar el pesaje", true);
            }
        } catch (\Throwable $th) {
            Log::error("InventarioMaterialesPiezasController::store - " . $th->getMessage());
            return $this->setResponse([], "Ocurrión un error. Comuníquese con el encargado de sistemas.", true);
            //throw $th;
        }
    }

    public function show(InventarioMaterialesPiezas $inventarioMaterialesPiezas) {
        //
    }

    public function edit(InventarioMaterialesPiezas $inventarioMaterialesPiezas) {
        //
    }

    public function update(Request $request, InventarioMaterialesPiezas $inventarioMaterialesPiezas) {
        //
    }

    public function confirmaPesaje($id) {
        try {
            InventarioMaterialesPiezas::where('id', $id)->update(['confirmado' => true]);
            return $this->setResponse([]);
        } catch (\Throwable $th) {
            Log::alert("InventarioMaterialesPiezas::confirmaPesaje : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuniquese con el encargado de sistemas.", true);
        }
    }

    public function destroy(InventarioMaterialesPiezas $inventarioMaterialesPiezas) {

        if ($inventarioMaterialesPiezas) {
            $inventarioMaterialesPiezas->delete();
        }

        return $this->setResponse();
    }

    private function applyResultadoOverrides(array $data, string $fecha): array
    {
        if (empty($data)) {
            return $data;
        }

        $materialIds = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = intval($row['id'] ?? 0);
            if ($id > 0) {
                $materialIds[] = $id;
            }
        }

        $materialIds = array_values(array_unique($materialIds));

        $overrides = [];
        if (!empty($materialIds)) {
            try {
                $overrides = InventarioMaterialesResultadoOverride::where('fecha', $fecha)
                    ->whereIn('material_id', $materialIds)
                    ->get()
                    ->keyBy('material_id');
            } catch (\Throwable $th) {
                Log::warning("InventarioMaterialesPiezasController::applyResultadoOverrides : " . $th->getMessage());
                return $data;
            }
        }

        foreach ($data as &$row) {
            if (!is_array($row)) {
                continue;
            }

            $materialId = intval($row['id'] ?? 0);

            $subtotalBase = $this->toFloat($row['inventario_sum_cantidad'] ?? 0) + $this->toFloat($row['codigo_proveedor'] ?? 0);
            $densidadBase = $this->toFloat($row['densidad'] ?? 0);
            $anchoBase = $this->toFloat($row['ancho'] ?? 0);
            $m2Base = ($densidadBase > 0) ? ($subtotalBase / $densidadBase) : 0;
            $mlBase = ($anchoBase > 0 && $m2Base > 0) ? ($m2Base / $anchoBase) : 0;

            $subtotal = $subtotalBase;
            $densidad = $densidadBase;
            $ancho = $anchoBase;
            $m2 = $m2Base;
            $ml = $mlBase;
            $overrideAplicado = false;

            $override = $overrides[$materialId] ?? null;
            if ($override) {
                $subtotal = is_null($override->subtotal_kg) ? $subtotal : floatval($override->subtotal_kg);
                $densidad = is_null($override->densidad) ? $densidad : floatval($override->densidad);
                $m2 = is_null($override->total_m2) ? $m2 : floatval($override->total_m2);
                $ancho = is_null($override->ancho) ? $ancho : floatval($override->ancho);
                $ml = is_null($override->total_ml) ? $ml : floatval($override->total_ml);
                $overrideAplicado = true;
            }

            $row['subtotal_kg'] = round($subtotal, 3);
            $row['densidad'] = round($densidad, 3);
            $row['total_m2'] = round($m2, 3);
            $row['ancho'] = round($ancho, 3);
            $row['total_ml'] = round($ml, 3);
            $row['resultado_override'] = $overrideAplicado;
        }
        unset($row);

        return $data;
    }

    private function toFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return floatval($value);
        }
        return 0;
    }

    private function isDevUser($user): bool
    {
        if (!$user) {
            return false;
        }

        return strtolower(trim((string) $user->name)) === 'dev';
    }
}
