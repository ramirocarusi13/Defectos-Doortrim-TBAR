<?php

namespace App\Http\Controllers;

use App\Models\Lineas;
use App\Models\PlanLinea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanLineaController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $lineaId = request('linea_id');

        $query = PlanLinea::orderBy('linea_id')
            ->orderBy('turno')
            ->orderBy('orden');

        if ($lineaId) {
            $query->where('linea_id', intval($lineaId));
        }

        $items = $query->get()->map(function ($item) {
            return $this->formatPlanLinea($item);
        })->toArray();

        return $this->setResponse($items);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'linea_id' => ['required', 'integer'],
            'turnos' => ['required', 'array'],
            'turnos.M' => ['array'],
            'turnos.T' => ['array'],
            'turnos.*.*.hora_desde' => ['required', 'date_format:H:i'],
            'turnos.*.*.hora_hasta' => ['required', 'date_format:H:i'],
            'turnos.*.*.plan' => ['required', 'integer', 'min:0'],
        ]);

        $linea = Lineas::where('id', intval($request->linea_id))->first();
        if (!$linea) {
            return $this->setResponse([], 'La linea seleccionada no existe', true);
        }

        try {
            DB::transaction(function () use ($request) {
                $lineaId = intval($request->linea_id);
                $turnos = $request->turnos;

                PlanLinea::where('linea_id', $lineaId)
                    ->whereIn('turno', ['M', 'T'])
                    ->delete();

                foreach (['M', 'T'] as $turno) {
                    $acumulado = 0;
                    $items = $turnos[$turno] ?? [];

                    foreach (array_values($items) as $idx => $item) {
                        $plan = intval($item['plan'] ?? 0);
                        $acumulado += $plan;

                        PlanLinea::create([
                            'linea_id' => $lineaId,
                            'turno' => $turno,
                            'orden' => $idx + 1,
                            'hora_desde' => $this->normalizarHora($item['hora_desde']),
                            'hora_hasta' => $this->normalizarHora($item['hora_hasta']),
                            'plan' => $plan,
                            'plan_acumulado' => $acumulado,
                        ]);
                    }
                }
            });

            $data = PlanLinea::where('linea_id', intval($request->linea_id))
                ->whereIn('turno', ['M', 'T'])
                ->orderBy('turno')
                ->orderBy('orden')
                ->get()
                ->map(function ($item) {
                    return $this->formatPlanLinea($item);
                })->toArray();

            return $this->setResponse($data, 'Plan actualizado');
        } catch (\Throwable $th) {
            Log::error('PlanLineaController::store - Error: ' . $th->getMessage());
            return $this->setResponse([], 'Ocurrio un error al guardar el plan de la linea', true);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PlanLinea  $planLinea
     * @return \Illuminate\Http\Response
     */
    public function show(PlanLinea $planLinea)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PlanLinea  $planLinea
     * @return \Illuminate\Http\Response
     */
    public function edit(PlanLinea $planLinea)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PlanLinea  $planLinea
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PlanLinea $planLinea)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PlanLinea  $planLinea
     * @return \Illuminate\Http\Response
     */
    public function destroy(PlanLinea $planLinea)
    {
        //
    }

    private function formatPlanLinea(PlanLinea $item) {
        return [
            'id' => $item->id,
            'linea_id' => $item->linea_id,
            'turno' => $item->turno,
            'orden' => $item->orden,
            'hora_desde' => $this->horaHHMM($item->hora_desde),
            'hora_hasta' => $this->horaHHMM($item->hora_hasta),
            'plan' => intval($item->plan),
            'plan_acumulado' => intval($item->plan_acumulado),
        ];
    }

    private function horaHHMM($hora) {
        if (is_null($hora)) return null;
        return substr((string) $hora, 0, 5);
    }

    private function normalizarHora($hora) {
        return substr((string) $hora, 0, 5) . ':00';
    }
}
