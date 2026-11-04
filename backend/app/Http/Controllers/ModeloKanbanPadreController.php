<?php

namespace App\Http\Controllers;

use App\Http\Estados;
use App\Http\Kanban;
use App\Models\Kanbans;
use App\Models\LectraEstado;
use App\Models\ModeloDado;
use App\Models\ModeloKanbanPadre;
use App\Models\Modelos;
use App\Models\ModelosCompartidos;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ModeloKanbanPadreController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }

    public function getDado($dadoId) {

        $data = ModeloKanbanPadre::with('modeloDado')->where('id', $dadoId)->first();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
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
        // Log::alert("PASO");
        $id = $request->id;

        $dadoOriginal = ModeloKanbanPadre::where('id', $id)->first();

        $esA = $request->esA;
        $esB = $request->esB;
        $ordenA = $request->ordenA;
        $ordenB = $request->ordenB;
        $ordenCompleto = $request->ordenCompleto;
        $modeloId = $request->modeloId;

        $compartido = $request->modeloCompartido;


        $data = [
            'consumo'               => $request->consumo,
            'material_id'           => $request->material,
            'dado'                  => $request->dado,
            'corte'                 => $request->corte,
            't_lectra1'             => $request->t_lectra1,
            't_lectra2'             => $request->t_lectra2,
            't_lectra3'             => $request->t_lectra3,
            't_lectra4'             => $request->t_lectra4,
            't_posicionamiento'     => $request->t_posicionamiento,
        ];

        if ($id) {
            try {
                ModeloDado::where('dado_id', $id)->delete();
                ModeloKanbanPadre::where('id', $id)->update($data);

                $mod = ModeloKanbanPadre::where('id', $id)->first();

                ModeloDado::create([
                    'dado_id'       => $id,
                    'modelo_id'     => $mod->modelo_id,
                    'esA'           => $esA,
                    'esB'           => $esB,
                    'ordenA'        => $ordenA,
                    'ordenB'        => $ordenB,
                    'ordenCompleto' => $ordenCompleto,
                ]);


                $fecha = new DateTime();

                //Actualizo las lectras 
                LectraEstado::where('dado_id', $dadoOriginal->dado)
                    ->where('created_at', '>=', $fecha->format('Y-m-d ') . " 00:50:00")
                    ->update([
                        'dado_id'   => $request->dado
                    ]);

                return $this->setResponse([]);
            } catch (\Throwable $th) {
                //throw $th;
                Log::error("ModeloKanbanPadreController::store " . $th->getMessage());
                return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
            }
        } else {
            //ES ALTA
            try {
                if ($compartido) {
                    $modelo = Modelos::where('id', $modeloId)->first();
                    $modeloCompartido = ModelosCompartidos::whereRaw("name like '%" . $modelo->nombre . "%'")->first();
                    $data['modelo_id'] = null;
                    $data['compartido_id'] = $modeloCompartido->id;
                } else {
                    $data['modelo_id'] = $modeloId;
                }

                ModeloKanbanPadre::create($data);

                ModeloDado::create([
                    'dado_id'       => $id,
                    'modelo_id'     => $compartido ? null : $modeloId,
                    'esA'           => $esA,
                    'esB'           => $esB,
                    'ordenA'        => $ordenA,
                    'ordenB'        => $ordenB,
                    'ordenCompleto' => $ordenCompleto,
                ]);

                return $this->setResponse([]);
            } catch (\Throwable $th) {
                //throw $th;
                Log::error("ModeloKanbanPadreController::store " . $th->getMessage());
                return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ModeloKanbanPadre  $modeloKanbanPadre
     * @return \Illuminate\Http\Response
     */
    public function show(ModeloKanbanPadre $modeloKanbanPadre) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ModeloKanbanPadre  $modeloKanbanPadre
     * @return \Illuminate\Http\Response
     */
    public function edit(ModeloKanbanPadre $modeloKanbanPadre) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ModeloKanbanPadre  $modeloKanbanPadre
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ModeloKanbanPadre $modeloKanbanPadre) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ModeloKanbanPadre  $modeloKanbanPadre
     * @return \Illuminate\Http\Response
     */
    public function destroy($dadoId) {

        try {

            ModeloKanbanPadre::where('id', $dadoId)->delete();
            return $this->setResponse([]);
        } catch (\Throwable $th) {
            //throw $th;
            Log::alert('Error al eliminar el dado' . $th->getMessage());
            return $this->setResponse([], 'Error al eliminar', true);
        }
    }

    public function getKanbanPapa($modelo) {

        $compartido = ModelosCompartidos::where('modelo1_id', $modelo)
            ->orWhere('modelo2_id', $modelo)->orWhere('modelo3_id', $modelo)->first();

        if ($compartido) {
            $data = ModeloKanbanPadre::with(['compartido', 'modeloDado', 'material', 'modelo.fila', 'modelo.color', 'modelo.material'])
                ->where('modelo_id', $modelo)
                ->orWhere('compartido_id', $compartido->id)
                ->get();
        } else {
            $data = ModeloKanbanPadre::with(['compartido', 'modeloDado', 'material', 'modelo.fila', 'modelo.color', 'modelo.material'])
                ->where('modelo_id', $modelo)->get();
        }

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getDataPadreFromKanban($codigoKanban, $lectra) {

        try {
            //Si no existe, doy de alta el kanban en sistema y lo registro en corte
            $kanban = Kanban::registrarKanbanSiNoExiste($codigoKanban);
        } catch (\Throwable $th) {
            //throw $th;
            return $this->setResponse([], $th->getMessage(), true);
        }

        //Averiguo modelo del kanban
        $kanban = Kanbans::with(['estado', 'modelo'])->where('codigo', $codigoKanban)->first();

        if (!$kanban) {
            return $this->setResponse([], "El kanban ingresado no existe", true);
        }

        if ($kanban->estado->estado_id != Estados::PLANIFICADO && $kanban->estado->estado_id != Estados::EN_CORTE) {
            return $this->setResponse([], "El kanban ingresado no se encuentra pendiente de corte", true);
        }


        //Obtengo los dados del kanban papá
        // $data = ModeloKanbanPadre::with(['material', 'modelo.fila', 'modelo.color', 'modelo.material'])
        //     ->where('modelo_id', intval($kanban->modelo_id))->get();
        $response = [];

        //Obtengo el kanban papá de acuerdo a lo que tiene asignada la lectra
        $data = LectraEstado::with(['dado.material', 'dado.modelo.fila', 'dado.modelo.color', 'dado.modelo.material'])->where('modelo', $kanban->modelo->nombre)->where('lectra', $lectra)->get();

        // Log::alert($data);
        foreach ($data as $d) {
            $info = $d->dado;
            $info->inicio = $d->inicio;
            $info->fin = $d->fin;
            array_push($response, $info);
        }

        // Log::alert($data);

        if ($response) {
            // $response = $data->toArray();
            if (count($response) == 0) {
                return $this->setResponse([], "No existe información sobre el modelo", true);
            }
            return $this->setResponse($response);
            // return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([], "No existe información sobre el modelo", true);
        }
    }
}
