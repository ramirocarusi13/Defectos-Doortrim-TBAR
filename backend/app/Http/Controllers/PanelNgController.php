<?php

namespace App\Http\Controllers;

use App\Services\DoorReetiquetaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * App PANEL NG (puestos Produccion 4 / Produccion 3): panel que vuelve con un
 * defecto -> queda NG -> se escanea el nuevo -> se le reimprime la etiqueta con la
 * secuencia del NG. Tabla propia eol_door_produccion_ng; no toca nada de door.
 */
class PanelNgController extends Controller
{
    private function respuesta(callable $f): JsonResponse
    {
        try {
            return response()->json(['data' => $f(), 'error' => false]);
        } catch (\RuntimeException $e) {
            $code = $e->getCode();
            return response()->json(
                ['data' => null, 'error' => true, 'message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }
    }

    /** Puestos e impresoras disponibles. */
    public function puestos(): JsonResponse
    {
        $data = [];
        foreach ((array) config('door.impresoras') as $puesto => $ip) {
            $data[] = ['puesto' => $puesto, 'impresora' => $ip];
        }
        return response()->json(['data' => $data, 'error' => false]);
    }

    /** 1) Escanea el panel con defecto: queda NG. */
    public function marcarNg(Request $request): JsonResponse
    {
        $d = $request->validate([
            'qr'      => 'required|string|max:120',
            'puesto'  => 'required|string|max:40',
            'usuario' => 'nullable|string|max:120',
        ]);
        return $this->respuesta(fn () => DoorReetiquetaService::marcarNg($d['qr'], $d['puesto'], $d['usuario'] ?? null));
    }

    /** 2) Escanea el panel nuevo: valida y reimprime la etiqueta con la secuencia del NG. */
    public function reetiquetar(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'qr_nuevo' => 'required|string|max:120',
            'puesto'   => 'nullable|string|max:40',
            'prueba'   => 'nullable|boolean',
        ]);
        return $this->respuesta(fn () => DoorReetiquetaService::reetiquetar($id, $d['qr_nuevo'], (string) ($d['puesto'] ?? ''), (bool) ($d['prueba'] ?? false)));
    }

    /** Reimprime la ultima etiqueta de un NG (si se trabo la impresora, etc). */
    public function reimprimir(Request $request, int $id): JsonResponse
    {
        return $this->respuesta(fn () => DoorReetiquetaService::reimprimir($id, (bool) $request->input('prueba', false)));
    }

    /** Ultimos paneles NG del puesto (para ver en la app). */
    public function listar(Request $request): JsonResponse
    {
        $q = DB::table('eol_door_produccion_ng')->orderByDesc('id')->limit(50);
        if ($request->filled('puesto')) {
            $q->where('puesto', strtoupper(trim((string) $request->input('puesto'))));
        }
        return response()->json(['data' => $q->get(), 'error' => false]);
    }
}
