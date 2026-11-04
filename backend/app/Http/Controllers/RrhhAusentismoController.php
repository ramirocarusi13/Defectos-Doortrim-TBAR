<?php

namespace App\Http\Controllers;

use App\Models\AusentismoDiario;
use App\Models\rrhhAreas;
use App\Models\rrhhAusentismo;
use App\Models\rrhhCausaAusentismo;
use App\Models\rrhhDetalleAusentismo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RrhhAusentismoController extends Controller {

    public function getAreas() {
        return $this->setResponse(rrhhAreas::get()->toArray());
    }

    public function getDetalles() {
        return $this->setResponse(rrhhDetalleAusentismo::get()->toArray());
    }

    public function getCausas() {
        return $this->setResponse(rrhhCausaAusentismo::get()->toArray());
    }

    public function index() {

        return $this->setResponse(rrhhAusentismo::with(['causa', 'detalle', 'legajo.area'])->get()->toArray());
    }

    public function generaAusentismoDia() {

        $fecha = date('Y-m-d');

        //Elimino lo del día
        AusentismoDiario::where('fecha', $fecha)->delete();

        $ausentes = rrhhAusentismo::with(['legajo.area', 'detalle', 'causa'])
            ->where('fecha_real', null)->get();

        $ausentesArray = [];

        foreach ($ausentes as $ausente) {
            AusentismoDiario::create([
                'fecha'         => $fecha,
                'ausentismo_id' => $ausente->id
            ]);
            array_push($ausentesArray, $ausente);
        }

        return $this->setResponse($ausentesArray);
    }

    public function create() {
        //
    }

    public function store(Request $request) {

        if (!is_null($request->editId)) {
            $data = $request->toArray();
            unset($data['editId']);
            rrhhAusentismo::where('id', $request->editId)->update($data);
        } else {
            rrhhAusentismo::create($request->toArray());
        }

        return $this->setResponse([]);
    }

    public function show($id) {
        return $this->setResponse(rrhhAusentismo::with(['causa', 'detalle', 'legajo'])->where('id', $id)->first()->toArray());
    }

    public function edit(rrhhAusentismo $rrhhAusentismo) {
        //
    }

    public function update(Request $request, rrhhAusentismo $rrhhAusentismo) {
        //
    }

    public function destroy($ausentismoId) {

        rrhhAusentismo::where('id', $ausentismoId)->delete();

        return $this->setResponse([]);
    }
}
