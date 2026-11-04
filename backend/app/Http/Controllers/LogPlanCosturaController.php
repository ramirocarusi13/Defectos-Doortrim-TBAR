<?php

namespace App\Http\Controllers;

use App\Models\LogPlanCostura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogPlanCosturaController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\LogPlanCostura  $logPlanCostura
     * @return \Illuminate\Http\Response
     */
    public function show(LogPlanCostura $logPlanCostura) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\LogPlanCostura  $logPlanCostura
     * @return \Illuminate\Http\Response
     */
    public function edit(LogPlanCostura $logPlanCostura) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LogPlanCostura  $logPlanCostura
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, LogPlanCostura $logPlanCostura) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\LogPlanCostura  $logPlanCostura
     * @return \Illuminate\Http\Response
     */
    public function destroy(LogPlanCostura $logPlanCostura) {
        //
    }

    public function updateCorte(Request $request) {

        $modelo = $request->modelo;
        $cantidad = $request->cantidad;

        // Log::alert($request);

        if ($cantidad > 0) {
            $data = LogPlanCostura::selectRaw('modelo,cortes_ejecutados,id')->where('modelo', $modelo)
                ->havingRaw('SUM(isnull(cortes_requeridos,0)) >= SUM(isnull(cortes_ejecutados,0))')
                ->groupBy('modelo', 'cortes_ejecutados', 'id')
                ->first();
        } else {
            $data = LogPlanCostura::where('modelo', $modelo)->where('cortes_ejecutados', '>=', 1)->first();
        }

        if ($data) {
            $cortes = is_null($data->cortes_ejecutados) ? 0 : intval($data->cortes_ejecutados);
            if (!$cortes) {
                $cortes = 0;
            }
            // Log::alert($cortes);
            LogPlanCostura::where('id', $data->id)
                ->update([
                    'cortes_ejecutados' => ($cantidad > 0 ? $cortes + 1 : $cortes - 1)
                ]);
        }

        return $this->setResponse([]);
    }
}
