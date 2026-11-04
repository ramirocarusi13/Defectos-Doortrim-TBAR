<?php

namespace App\Http\Controllers;

use App\Models\ModeloJitms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModeloJitmsController extends Controller
{
    public function modelosJitms(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $familia = trim((string) $request->query('familia', ''));

        $query = ModeloJitms::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sd', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhere('id_producto', 'like', "%{$search}%")
                    ->orWhere('familia', 'like', "%{$search}%");
            });
        }

        if ($familia !== '') {
            $query->where('familia', $familia);
        }

        $rows = $query
            ->orderBy('familia')
            ->orderBy('sd')
            ->orderBy('codigo')
            ->get();

        $familias = ModeloJitms::query()
            ->whereNotNull('familia')
            ->distinct()
            ->orderBy('familia')
            ->pluck('familia')
            ->all();

        return response()->json([
            'data'     => $rows,
            'familias' => $familias,
            'total'    => $rows->count(),
            'error'    => false,
        ]);
    }
}
