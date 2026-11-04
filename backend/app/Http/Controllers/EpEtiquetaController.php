<?php

namespace App\Http\Controllers;

use App\Http\EstadoFalla;
use App\Http\Estados;
use App\Http\EstadosQr;
use App\Http\EventosQr;
use App\Http\Kanban;
use App\Http\LineasModelo;
use App\Models\Configuracion;
use App\Models\EpEtiqueta;
use App\Models\EpEtiquetaMid;
use App\Models\EpEtiquetasEventos;
use App\Models\EpKanbanEstado;
use App\Models\EpKanbanLote;
use App\Models\FallasInformadas;
use App\Models\Kanbans;
use App\Models\ModeloLinea;
use App\Models\Modelos;
use App\Models\Partes;
use App\Models\Sar\TEtiquetaAirbag;
use App\Models\Sar\TModelosAirbag;
use App\Models\User;
use App\Services\EtiquetaService;
use DateInterval;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EpEtiquetaController extends Controller {

    public function getEtiquetasPendientesLinea($linea) {

        $kanbansPendientes = EpEtiqueta::select('kanban_impresion')->where('kanban', null)->where('estado', '<>', 'RETRABAJO')->where('estado', '<>', 'SCRAP')->where('airbag', false)->where('linea', $linea)->groupBy('kanban_impresion')->get()->toArray();

        $kanbans = [];
        $etiquetas = [];

        foreach ($kanbansPendientes as $k) {
            array_push($kanbans, $k['kanban_impresion']);
        }

        $res = Kanbans::with(['modelo.partes.lado', 'modelo.partes.tipo', 'etiquetasQrGeneradasModelo.modelod', 'etiquetasQrGeneradas.modelod'])
            ->withCount(['pendienteValidarQrQcModelo', 'pendienteValidarQrModelo', 'etiquetasQrGeneradasModelo', 'pendienteValidarQrQc', 'pendienteValidarQr', 'etiquetasQrGeneradas'])
            ->whereIn('codigo', $kanbans)->get();

        foreach ($res as $r) {
            array_push($etiquetas, $r);
        }

        // Log::alert($kanbans);

        return $this->setResponse($etiquetas);
    }

    public function consultarModeloAirbag(Request $request) {
        // $modelo = $request->modelo;

        // $res = Modelos::with(['partes.lado', 'partes.tipo'])
        //     ->where('nombre', $modelo)
        //     ->where('airbag', true)
        //     ->first();

        // if ($res) {
        //     return $this->setResponse($res->toArray());
        // } else {
        //     return $this->setResponse([]);
        // }

        $kanbanCode = $request->kanban;
        // $kanbanCode2 = $request->kanban2;


        //KANBAN 1
        try {
            $kanban = Kanban::registrarKanbanSiNoExiste($kanbanCode);
        } catch (\Throwable $th) {
            return $this->setResponse([], 'Incorrecto. No hay información del kanban.', true);
        }

        if (!$kanban) {
            return $this->setResponse([], 'Incorrecto. No hay información del kanban.', true);
        }

        if (!$kanban->modelo->airbag) {
            return $this->setResponse([], 'Incorrecto. El modelo del kanban no tiene airbag.', true);
        }

        $kanban = Kanbans::with(['etiquetasAsignadasKanbanAirbag', 'modelo.partes.lado', 'modelo.partes.tipo'])
            ->where('codigo', $kanbanCode)
            ->whereHas('modelo', function ($q) {
                $q->where('airbag', true);
            })
            ->first()->toArray();


        return $this->setResponse($kanban);
    }

    public function consultarKanbans(Request $request) {
        //Verifico si existe
        $kanbanCode = $request->kanban;
        // $kanbanCode2 = $request->kanban2;


        //KANBAN 1
        try {
            $kanban1 = Kanban::registrarKanbanSiNoExiste($kanbanCode);
        } catch (\Throwable $th) {
            return $this->setResponse([], 'Incorrecto. No hay información del kanban.', true);
        }

        if (!$kanban1) {
            return $this->setResponse([], 'Incorrecto. No hay información del kanban.', true);
        }

        $kanban1 = Kanbans::with(['etiquetasQrValidadas', 'modelo.partes.lado', 'modelo.partes.tipo', 'etiquetasQrGeneradas.modelod', 'etiquetasQrGeneradasModelo.modelod'])
            ->withCount(['pendienteValidarQrQc', 'pendienteValidarQr', 'etiquetasQrGeneradas', 'etiquetasQrGeneradasModelo'])
            ->where('codigo', $kanbanCode)->first()->toArray();
        // $kanban1 = Kanbans::with(['modelo.partes.lado', 'modelo.partes.tipos', 'etiquetasQrValidadas', 'etiquetasQrGeneradas.modelod'])
        //     ->withCount(['pendienteValidarQr', 'etiquetasQrGeneradas'])
        //     ->where('codigo', $kanbanCode)->first()->toArray();

        $lineas = LineasModelo::MODELOS[$kanban1['modelo']['nombre']];
        $kanban1['lineas'] = $lineas;

        return $this->setResponse([
            'kanban1'   => $kanban1,
            'kanban2'   => null,
        ]);
    }

    public function consultarKanban($kanbanCode) {
        //Verifico si existe
        // Log::alert($kanbanCode);
        try {
            $kanban = Kanban::registrarKanbanSiNoExiste($kanbanCode);
        } catch (\Throwable $th) {
            return $this->setResponse([], 'No hay información del kanban', true);
        }

        if (!$kanban) {
            return $this->setResponse([], 'No hay información del kanban', true);
        }


        // $etiquetas = EpEtiqueta::where('kanban_impresion', $kanbanCode)->first();
        // Log::alert($etiquetas);
        //SI HAY 1 ETIQUETA, ENTONCES ES REIMPRESION, TRAIGO TODAS LAS DEL MODELO
        // if ($etiquetas) {
        //     $kanban = Kanbans::with(['estado', 'modelo.partes.lado', 'modelo.partes.tipo', 'etiquetasQrGeneradasModelo.modelod', 'etiquetasQrGeneradas.modelod'])
        //         ->withCount(['pendienteValidarQrQcModelo', 'pendienteValidarQrModelo', 'etiquetasQrGeneradasModelo', 'pendienteValidarQrQc', 'pendienteValidarQr', 'etiquetasQrGeneradas'])
        //         ->where('codigo', $kanbanCode)->first()
        //         ->toArray();
        // } else {
        $kanban = Kanbans::with(['estado', 'modelo.partes.lado', 'modelo.partes.tipo', 'etiquetasQrGeneradas.modelod'])
            ->withCount(['pendienteValidarQrQc', 'pendienteValidarQr', 'etiquetasQrGeneradas'])
            ->where('codigo', $kanbanCode)->first()
            ->toArray();
        // }

        // Log::alert($kanban['estado']);

        try {
            if ($kanban['estado']['estado_id'] != Estados::SUB_ASSY) {
                return $this->setResponse([], 'EL KANBAN NO SE ENCUENTRA EN LINEA', true);
            }
        } catch (\Throwable $th) {
            return $this->setResponse([], 'EL KANBAN NO SE ENCUENTRA EN LINEA', true);
        }

        $lineas = LineasModelo::MODELOS[$kanban['modelo']['nombre']];
        $kanban['lineas'] = $lineas;

        return $this->setResponse($kanban);
    }

    public function reimprimirEtiqueta($id, $userId) {
        $etiqueta = EpEtiqueta::with('modelod')->where('id', $id)->first();

        if ($etiqueta) {
            $idEtiqueta = intval($etiqueta->id_etiqueta) + 1;
            $secuencia = str_pad($idEtiqueta, 3, "0", STR_PAD_LEFT);
            $linea = substr($etiqueta->qr, 33, 1);

            $qr = date("Ydm") . "1" . $etiqueta->secuencia . $etiqueta->codigo . $etiqueta->ubicacion . $etiqueta->lado . $etiqueta->tipo  . $linea . '1SAR' . $secuencia;

            EpEtiqueta::where('id', $id)->update([
                'estado'                => 'IMPRESION',
                'id_etiqueta'           => $idEtiqueta,
                'qr'                    => $qr,
                'user_id_reimpresion'   => $userId
            ]);

            //Genero evento
            EpEtiquetasEventos::create([
                'kanban'         => $etiqueta->kanban,
                'qr_etiqueta'    => $qr,
                'user_id'        => $userId,
                'evento'         => EventosQr::REEMPLAZO_ETIQUETA,
                'info_adi'       => $etiqueta->qr,
            ]);

            // $etiquetas = EpEtiqueta::with('modelod')->where('kanban', $etiqueta->kanban)->get()->ToArray();

            $etiquetasExistentes = EpEtiqueta::where('kanban_impresion', $etiqueta->kanban_impresion)->where('airbag', false)->first();
            #Log::alert($etiquetasExistentes);
            //SI HAY 1 ETIQUETA, ENTONCES ES REIMPRESION, TRAIGO TODAS LAS DEL MODELO
            if ($etiquetasExistentes) {
                $etiquetas = Kanbans::with(['modelo.partes.lado', 'modelo.partes.tipo', 'etiquetasQrGeneradasModelo.modelod', 'etiquetasQrGeneradas.modelod'])
                    ->withCount(['pendienteValidarQrQcModelo', 'pendienteValidarQrModelo', 'etiquetasQrGeneradasModelo', 'pendienteValidarQrQc', 'pendienteValidarQr', 'etiquetasQrGeneradas'])
                    ->where('codigo', $etiqueta->kanban_impresion)->first();
            } else {
                $etiquetas = Kanbans::with(['modelo.partes.lado', 'modelo.partes.tipo', 'etiquetasQrGeneradas.modelod'])
                    ->withCount(['pendienteValidarQrQc', 'pendienteValidarQr', 'etiquetasQrGeneradas'])
                    ->where('codigo', $etiqueta->kanban_impresion)->first();
            }

            if ($etiquetas) {
                return $this->setResponse($etiquetas->toArray());
            }

            return $this->setResponse([]);
        }
    }

    public function validarEtiqueta(Request $request) {

        $qr = $request->qr;
        $qr = str_replace("'", '-', $qr);
        $qr = strtoupper($qr);

        //VERIFICO ESTADO ACTUAL
        $existente = EpEtiqueta::where('qr', $qr)->where('airbag', false)->first();
        if ($existente) {

            if ($existente->estado == EstadosQr::CREADA) {
                return $this->setResponse([], 'La etiqueta no está pendiente de validación', true);
            }

            if ($existente->estado != EstadosQr::IMPRESION) {
                $evento = EpEtiquetasEventos::create([
                    'kanban'         => $existente->kanban,
                    'qr_etiqueta'    => $qr,
                    'user_id'        => $request->user,
                    'evento'         => EventosQr::ETIQUETA_YA_VALIDADA,
                    'info_adi'       => null,
                ]);

                return $this->setResponse(['evento' => $evento], 'La etiqueta ya fue validada', true);
            }
        }

        try {
            EpEtiqueta::where('qr', $qr)->update(['estado' => EstadosQr::VALIDADA]);
            $data = EpEtiqueta::where('qr', $qr)->first();
            // Log::alert($data);
            $etiquetas = Kanbans::with(['modelo.partes.lado', 'modelo.partes.tipo', 'etiquetasQrGeneradasModelo.modelod', 'etiquetasQrGeneradas.modelod'])
                ->withCount(['pendienteValidarQrQcModelo', 'pendienteValidarQrModelo', 'etiquetasQrGeneradasModelo', 'pendienteValidarQrQc', 'pendienteValidarQr', 'etiquetasQrGeneradas'])
                ->where('codigo', $data->kanban_impresion)->first()->toArray();

            return $this->setResponse($etiquetas, 'Etiqueta validada.');
            // return $this->setResponse(
            //     EpEtiqueta::with(['modelod', 'etiquetasQrGeneradasModelo'])->where('kanban', $data->kanban)
            //         ->get()->toArray(),
            //     'Etiqueta validada.'
            // );
        } catch (\Throwable $th) {
            Log::error("EpEtiquetaController::validarEtiqueta : " . $th->getMessage());
            return $this->setResponse([], 'No se pudo validar la etiqueta o no existe', true);
            //throw $th;
        }
    }

    public function actualizaEvento(Request $request) {
        $eventoId = $request->evento;
        $userAutorizanteId = $request->userId;

        $user = User::select('email')->where('id', $userAutorizanteId)->first();

        EpEtiquetasEventos::where('id', $eventoId)->update(['info_adi' => 'Autoriza : ' . $user->email]);

        return $this->setResponse([]);
    }

    public function cambiarEstadoEtiqueta(Request $request) {
        $qr = $request->qr;
        $qr = str_replace("'", '-', $qr);
        $kanban = $request->kanban;
        $userId = $request->userId;

        try {
            $data = EpEtiqueta::where('qr', $qr)->where('kanban', $kanban)->first();

            if (!$data) {
                return $this->setResponse([], 'La etiqueta escaneada no existe.', true);
            }

            $etiquetaAbierta = EpEtiqueta::where('kanban', $kanban)
                ->where('qr', '<>', $qr)
                ->where('estado', EstadosQr::EN_COSTURA)->first();

            if ($etiquetaAbierta) {
                //Si hay una etiqueta abierta que es distinta a la que estoy consultando, cancelo el proceso
                return $this->setResponse([], 'Ya hay una etiqueta en costura', true);
            }


            if ($data->estado == EstadosQr::VALIDADA) {
                $estado = EstadosQr::EN_COSTURA;
                $mensajeSuccess = 'En proceso de costura.';

                $existeEstado = EpKanbanEstado::where('user_costura', null)->where('kanban', $kanban)->first();
                // Log::alert($existeEstado);
                if ($existeEstado) {
                    EpKanbanEstado::where('kanban', $kanban)->update([
                        'user_costura' => $userId,
                    ]);
                }
            } else if ($data->estado == EstadosQr::EN_COSTURA) {
                $estado = EstadosQr::COSIDA;
                $mensajeSuccess = 'Etiqueta costurada.';
            } else if ($data->estado == EstadosQr::COSIDA) {
                //Genero evento
                EpEtiquetasEventos::create([
                    'kanban'         => $kanban,
                    'qr_etiqueta'    => $qr,
                    'user_id'        => $userId,
                    'evento'         => EventosQr::ETIQUETA_YA_COSIDA,
                    'info_adi'       => '',
                ]);
                return $this->setResponse([], 'La etiqueta ya fue cosida.', true);
            }

            EpEtiqueta::where('qr', $qr)->update(['estado' => $estado]);
            // $data = EpEtiqueta::where('qr', $qr)->first();

            return $this->setResponse(EpEtiqueta::with('modelod')->where('kanban', $kanban)->get()->toArray(), $mensajeSuccess);
        } catch (\Throwable $th) {
            Log::error("EpEtiquetaController::cambiarEstadoEtiqueta : " . $th->getMessage());
            return $this->setResponse([], 'No se pudo actualizar la etiqueta o no existe', true);
            //throw $th;
        }
    }

    private function mascaraValida($modelo, $modeloMascara, $lado, $ladoMascara, $tipo, $tipoMascara) {
        // $inicio = microtime(true);
        [$modelo, $modeloMascara, $tipoMascara, $ladoMascara, $lado, $tipo,] = array_map('strtoupper', [$modelo, $modeloMascara, $tipoMascara, $ladoMascara, $lado, $tipo]);

        // Log::alert($modeloMascara);
        // Log::alert($modelo);
        // Log::alert($tipoMascara);
        // Log::alert($tipo);
        // Log::alert($ladoMascara);
        // Log::alert($lado);
        // $validaciones = [
        //     'SFHG|CS|LH/SFHG|CS|LH' => true,
        //     'SFHG|CS|LH/SFHP|CS|LH' => true,
        //     'SFHP|CS|LH/SFHP|CS|LH' => true,
        //     'SFHP|CS|LH/SFHG|CS|LH' => true,
        //     'SFHG|CS|RH/SFHP|CS|RH' => true,
        //     'SFHG|CS|RH/SFHG|CS|RH' => true,
        //     'SFHP|CS|RH/SFHP|CS|RH' => true,
        //     'SFHP|CS|RH/SFHG|CS|RH' => true,
        // ];

        // $clave = "$modelo|$tipo|$lado/$modeloMascara|$tipoMascara|$ladoMascara";

        // if (array_key_exists($clave, $validaciones)) {
        //     return true;
        // }

        // return false;

        //TODO OPTIMIZAR URGENTE
        if ($tipoMascara == 'CS') {
            if ($tipo != 'CS') {
                return false;
            }
            if (
                ($modelo == 'SFLE' || $modelo == 'SFLC' || $modelo == 'SFLA' || $modelo == 'SFLB')
                && ($modeloMascara == 'SFLE' || $modeloMascara == 'SFLC' || $modeloMascara == 'SFLA' || $modeloMascara == 'SFLB')
                && $ladoMascara == "LH" && $lado == 'LH'
            ) {
                //ESTOS MODELOS EL CS LH ES IGUAL
                return true;
            } else if (
                ($modelo == 'SFLB' || $modelo == 'SFLE' || $modelo == 'SFLC' || $modelo == 'SFBN' || $modelo == 'SFMR' || $modelo == 'SFJJ') &&
                ($modeloMascara == 'SFLB' || $modeloMascara == 'SFLE' || $modeloMascara == 'SFBN' || $modeloMascara == 'SFLC' || $modeloMascara == 'SFMR' || $modeloMascara == 'SFJJ')
            ) {
                //ESTOS MODELOS EL CS LH/RH ES IGUAL
                return true;
            } else if (($modelo == 'SFPA' || $modelo == 'SFPB' || $modelo == 'SFPC') && ($modeloMascara == 'SFPA' || $modeloMascara == 'SFPB' || $modeloMascara == 'SFPC') &&
                $ladoMascara == 'LH' && $lado == 'LH'
            ) {
                //ESTOS MODELOS EL CS LH ES IGUAL
                return true;
            } else if (($modelo == 'SFPB' || $modelo == 'SFPC') && ($modeloMascara == 'SFPB' || $modeloMascara == 'SFPC') &&
                $ladoMascara == 'RH' && $lado == 'RH'
            ) {
                //ESTOS MODELOS EL CS RH ES IGUAL
                return true;
            } else if (($modelo == 'SFHP' || $modelo == 'SFHG')  && ($modeloMascara == 'SFHP' || $modeloMascara == 'SFHG') && $ladoMascara == 'LH' && $lado == 'LH') {
                //ESTOS MODELOS EL CS LH ES IGUAL
                return true;
            } else if (($modelo == 'SFHP' || $modelo == 'SFHG')  && ($modeloMascara == 'SFHP' || $modeloMascara == 'SFHG') && $ladoMascara == 'RH' && $lado == 'RH') {
                //ESTOS MODELOS EL CS RH ES IGUAL
                return true;
            } else if (($modelo == 'SFNG' || $modelo == 'SFNJ')  && ($modeloMascara == 'SFNG' || $modeloMascara == 'SFNJ') && $ladoMascara == 'RH' && $lado == 'RH') {
                //ESTOS MODELOS EL CS RH ES IGUAL
                return true;
            } else if (($modelo == 'SFNG' || $modelo == 'SFNJ')  && ($modeloMascara == 'SFNG' || $modeloMascara == 'SFNJ') && $ladoMascara == 'LH' && $lado == 'LH') {
                //ESTOS MODELOS EL CS LH ES IGUAL
                return true;
            } else {
                //VALIDO QUE EL MODELO, LADO Y TIPO SEA IGUAL A LA MASCARA
                if ($ladoMascara != '') {
                    if (($modelo != $modeloMascara) || ($tipoMascara != $tipo) || ($lado != $ladoMascara)) {
                        return false;
                    } else {
                        return true;
                    }
                } else {
                    if (($modelo != $modeloMascara) || ($tipoMascara != $tipo)) {
                        return false;
                    } else {
                        return true;
                    }
                }
            }
        } else if ($tipoMascara == 'BC') {
            if ($tipo != 'BC') {
                return false;
            }

            if (($modelo == 'SFPC' || $modelo == 'SFPB') && ($modeloMascara == 'SFPC' || $modeloMascara == 'SFPB')) {
                //ESTOS MODELOS EL BACK LH/RH ES IGUAL
                return true;
            } else if (($modelo == 'SFPC' || $modelo == 'SFPB' || $modelo == 'SFPA') && ($modeloMascara == 'SFPC' || $modeloMascara == 'SFPB' || $modelo == 'SFPA') && $ladoMascara == 'LH' && $lado == 'LH') {
                //ESTOS MODELOS EL BACK LH ES IGUAL
                return true;
            } else if (($modelo == 'SFLB' || $modelo == 'SFLC') && ($modeloMascara == 'SFLB' || $modeloMascara == 'SFLC')) {
                //ESTOS MODELOS EL BACK LH/RH ES IGUAL
                return true;
            } else if (($modelo == 'SFHP' || $modelo == 'SFHG')  && ($modeloMascara == 'SFHP' || $modeloMascara == 'SFHG') && $ladoMascara == 'LH' && $lado == 'LH') {
                //ESTOS MODELOS EL CS LH ES IGUAL
                return true;
            } else if (($modelo == 'SFHP' || $modelo == 'SFHG')  && ($modeloMascara == 'SFHP' || $modeloMascara == 'SFHG') && $ladoMascara == 'RH' && $lado == 'RH') {
                //ESTOS MODELOS EL CS RH ES IGUAL
                return true;
            } else if (($modelo == 'SFEP' && $modeloMascara == 'SFEP') || ($modelo == 'SFKQ' && $modeloMascara == 'SFKQ')
                || ($modelo == 'SFKN' && $modeloMascara == 'SFKN') || ($modelo == 'SFBN' && $modeloMascara == 'SFBN')
                || ($modelo == 'SFMR' && $modeloMascara == 'SFMR') || ($modelo == 'SFTS' && $modeloMascara == 'SFTS')
                || ($modelo == 'SFNG' && $modeloMascara == 'SFNG') || ($modelo == 'SFNJ' && $modeloMascara == 'SFNJ')
                || ($modelo == 'SFJJ' && $modeloMascara == 'SFJJ')
            ) {
                //ESTOS MODELOS EL BACK LH/RH ES IGUAL
                return true;
            } else {
                //VALIDO QUE EL MODELO, LADO Y TIPO SEA IGUAL A LA MASCARA
                if ($ladoMascara != '') {
                    if (($modelo != $modeloMascara) || ($tipoMascara != $tipo) || ($lado != $ladoMascara)) {
                        return false;
                    } else {
                        return true;
                    }
                } else {
                    if (($modelo != $modeloMascara) || ($tipoMascara != $tipo)) {
                        return false;
                    } else {
                        return true;
                    }
                }
            }
        }
    }

    public function cambiarEstadoEtiquetaQC(Request $request) {
        $qr = $request->qr;
        $qr = str_replace("\'", '-', $qr);
        $qr = str_replace("'", '-', $qr);
        $kanban = strtoupper($request->kanban);
        $userId = $request->userId;
        $mascara = $request->mascara;
        $mascara =  str_replace("-", '|', $mascara);
        $mascara = str_replace("]", '|', $mascara);
        $kanbanActivo = $kanban;
        $esAirbag = $request->airbag;

        if (is_null($esAirbag)) {
            $esAirbag = 0;
        }

        try {
            $kanbanData = Kanbans::with(['modelo'])->where('codigo', $kanban)->first();

            if (!$kanbanData) {
                $evento = EpEtiquetasEventos::create([
                    'kanban'         => $kanbanActivo,
                    'qr_etiqueta'    => $qr,
                    'user_id'        => $userId,
                    'evento'         => EventosQr::KANBAN_INCONSISTENTE,
                    'info_adi'       => '',
                ]);
                return $this->setResponse(['evento' => $evento], 'El kanban ingresado no existe.', true);
            }

            $data = EpEtiqueta::select('id', 'estado', 'modelo', 'lado', 'tipo')->where('qr', $qr)
                ->where('modelo', $kanbanData->modelo->nombre)
                ->whereIn('estado', [EstadosQr::VALIDADA, EstadosQr::CREADA])
                ->where('airbag', $esAirbag)
                ->first();

            if (!$data) {
                //PUEDE SER BACK, PRIMERO VERIFICO QUE NO ESTE VALIDADA YA, SI NO VERIFICO EN BASE REMOTA
                $data = EpEtiqueta::select('id', 'estado', 'modelo', 'lado', 'tipo')->where('qr', $qr)
                    ->where('modelo', $kanbanData->modelo->nombre)
                    ->where('estado', EstadosQr::QCP_OK)
                    ->first();

                if ($data) {
                    //YA FUE VALIDADA
                    $evento = EpEtiquetasEventos::create([
                        'kanban'         => $kanbanActivo,
                        'qr_etiqueta'    => $qr,
                        'user_id'        => $userId,
                        'evento'         => EventosQr::KANBAN_INCONSISTENTE,
                        'info_adi'       => '',
                    ]);
                    return $this->setResponse(['evento' => $evento], 'La etiqueta escaneada ya fue controlada.', true);
                }

                $data = EtiquetaService::creaEtiquetaAirbagDesdeBaseSAR($qr, $kanbanActivo);
            }

            if (!$data) {

                $evento = EpEtiquetasEventos::create([
                    'kanban'         => $kanbanActivo,
                    'qr_etiqueta'    => $qr,
                    'user_id'        => $userId,
                    'evento'         => EventosQr::KANBAN_INCONSISTENTE,
                    'info_adi'       => '',
                ]);
                return $this->setResponse(['evento' => $evento], 'La etiqueta escaneada no existe, ya fue controlada o no corresponde al modelo del kanban.', true);
            }

            //VALIDO MODELO KANBAN Y MODELO ETIQUETA
            if ($kanbanData->modelo->nombre != $data->modelo) {
                $evento = EpEtiquetasEventos::create([
                    'kanban'         => $kanbanActivo,
                    'qr_etiqueta'    => $qr,
                    'user_id'        => $userId,
                    'evento'         => EventosQr::MODELO_INCONSISTENTE,
                    'info_adi'       => 'Modelo Kanban : ' . $kanbanData->modelo->nombre,
                ]);
                return $this->setResponse(['evento' => $evento], 'El modelo del kanban no coincide con el modelo de la etiqueta', true);
            }

            // if ($data->estado == EstadosQr::CREADA) {
            //     EpEtiqueta::where('qr', $qr)->where('modelo', $kanbanData->modelo->nombre)->update(['estado' => EstadosQr::VALIDADA]);
            // }

            // Log::alert($mascara);

            if (!$mascara) {
                $evento = EpEtiquetasEventos::create([
                    'kanban'         => $kanbanActivo,
                    'qr_etiqueta'    => $qr,
                    'user_id'        => $userId,
                    'evento'         => EventosQr::MASCARA_INCONSISTENTE . ' - Máscara : ' . $mascara,
                    'info_adi'       => '',
                ]);

                return $this->setResponse(['evento' => $evento], 'La máscara no corresponde a la etiqueta escaneada.', true);
            }

            // if ($mascara) {
            //SI VIENE MASCARA, LA VALIDO CONTRA LA ETIQUETA
            $mascaraArr = explode("|", $mascara);
            // Log::alert($mascaraArr);
            $modeloMascara = $mascaraArr[0];
            $tipoMascara = $mascaraArr[1];

            if (count($mascaraArr) == 2) {
                $ladoMascara = "";
            } else {
                $ladoMascara = $mascaraArr[2];
            }

            $mascaraValida = true;
            $mascaraValida = $this->mascaraValida($data->modelo, $modeloMascara, $data->lado, $ladoMascara, $data->tipo, $tipoMascara);

            //Verifico que la mascara escaneada sea correspondiente a la etiqueta escaneada
            if (!$mascaraValida) {
                $evento = EpEtiquetasEventos::create([
                    'kanban'         => $kanbanActivo,
                    'qr_etiqueta'    => $qr,
                    'user_id'        => $userId,
                    'evento'         => EventosQr::MASCARA_INCONSISTENTE . ' - Máscara : ' . $mascara,
                    'info_adi'       => '',
                ]);

                return $this->setResponse(['evento' => $evento], 'La máscara no corresponde a la etiqueta escaneada.', true);
            }
            // }



            $tieneFallas = FallasInformadas::select('id')->where('qr', $qr)->where('estado', EstadoFalla::PENDIENTE)->first();
            if ($tieneFallas) {
                $evento = EpEtiquetasEventos::create([
                    'kanban'         => $kanbanActivo,
                    'qr_etiqueta'    => $qr,
                    'user_id'        => $userId,
                    'evento'         => EventosQr::RETRABAJO_PENDIENTE,
                    'info_adi'       => '',
                ]);
                return $this->setResponse(['evento' => $evento], 'La funda tiene retrabajos pendientes.', true);
            }

            if ($data->estado == EstadosQr::VALIDADA || $data->estado == EstadosQr::CREADA) {
                $cantidad = $kanbanData->modelo->cantidad;
                $vehiculo = LineasModelo::VEHICULOS[$kanbanData->modelo->nombre];
                $vehiculo = strpos($vehiculo, '/') !== false ? substr($vehiculo, 0, strpos($vehiculo, '/')) : $vehiculo;

                $tipos = Partes::select('id')->whereHas('vehiculo', fn($q) => $q->where('codigo', 'like', "%$vehiculo%"))
                    ->whereHas('tipo', function ($q) use ($data) {
                        if ($data->tipo === 'CS') {
                            $q->where('tipo', 'CUSHION');
                        } elseif ($data->tipo === 'BC') {
                            $q->whereIn('tipo', ['BACK', 'T-UP']);
                        }
                    })
                    ->when(!empty($data->lado), function ($query) use ($data) {
                        $lado = $data->lado === '00' ? '100%' : $data->lado;
                        $query->whereHas('lado', fn($q) => $q->where('lado', $lado));
                    })
                    ->where([
                        ['modelo_id', '=', $kanbanData->modelo_id],
                        ['activo', '=', true]
                    ])
                    ->orderBy('vehiculo_id')
                    ->orderBy('lado_id')
                    ->count();

                $etLimite = $tipos * $cantidad;

                $etValidadas = EpEtiqueta::select('id')->where([
                    ['kanban', '=', $kanban],
                    ['tipo', '=', $data->tipo],
                    ['estado', '=', EstadosQr::QCP_OK]
                ])
                    ->when(!empty($data->lado), fn($q) => $q->where('lado', $data->lado))
                    ->count();

                if ($etLimite == $etValidadas) {
                    //SI LA CANTIDAD ES LA MISMA, ESTOY AL TOPE
                    $evento = EpEtiquetasEventos::create([
                        'kanban'         => $kanbanActivo,
                        'qr_etiqueta'    => $qr,
                        'user_id'        => $userId,
                        'evento'         => EventosQr::MODELO_LADO_TIPO_COMPLETO,
                        'info_adi'       => '',
                    ]);
                    return $this->setResponse([], 'Ya se completaron las cantidades en dolly para ese modelo/lado/tipo. Cambie de Kanban para poder escanear la funda.', true);
                }

                $estado = EstadosQr::QCP_OK;
                $mensajeSuccess = 'Validación correcta.';
            } else if ($data->estado == EstadosQr::QCP_OK) {
                //Genero evento
                $evento = EpEtiquetasEventos::create([
                    'kanban'         => $kanbanActivo,
                    'qr_etiqueta'    => $qr,
                    'user_id'        => $userId,
                    'evento'         => EventosQr::ETIQUETA_YA_CONTROLADA_QC,
                    'info_adi'       => '',
                ]);
                return $this->setResponse(['evento' => $evento], 'La etiqueta ya fue controlada.', true);
            } else {
                $estado = EstadosQr::QCP_OK;
                $mensajeSuccess = 'Validación correcta.';
            }

            EpEtiqueta::where('id', $data->id)->update([
                'estado'            => $estado,
                'kanban'            => $kanbanActivo,
                'user_validacion'   => $userId,
                'hora_validacion'   => date('Y-m-d H:i:s')
            ]);

            // $kanban1 = Kanbans::with(['etiquetasQrValidadas', 'modelo.partes.lado', 'modelo.partes.tipo', 'etiquetasQrGeneradas.modelod', 'etiquetasQrGeneradasModelo.modelod'])
            // ->withCount(['pendienteValidarQrQc', 'pendienteValidarQr', 'etiquetasQrGeneradas', 'etiquetasQrGeneradasModelo'])
            // ->where('codigo', $kanbanActivo)->first()->toArray();

            // $kanban1 = Kanbans::with(['etiquetasQrValidadas',  'etiquetasQrGeneradas.modelod'])
            //     ->where('codigo', $kanbanActivo)->first()->toArray();

            if ($esAirbag || $esAirbag == 1) {

                $kanban1 = Kanbans::with([
                    'modelo',
                    'modelo.partes.lado',
                    'modelo.partes.tipo',
                    'etiquetasAsignadasKanbanAirbag'
                ])
                    ->where('codigo', $kanbanActivo)
                    ->firstOrFail();
            } else {
                $kanban1 = Kanbans::select('codigo')
                    ->with([
                        'etiquetasQrValidadas',
                        'etiquetasQrGeneradas.modelod'
                    ])
                    ->where('codigo', $kanbanActivo)
                    ->firstOrFail();
            }

            $response = [
                'etiquetas' => $kanban1,
                'kanban'    => $kanbanActivo
            ];

            return $this->setResponse($response, $mensajeSuccess);
        } catch (\Throwable $th) {
            Log::error("EpEtiquetaController::cambiarEstadoEtiquetaQc : " . $th->getMessage());
            return $this->setResponse([], 'No se pudo actualizar la etiqueta o no existe', true);
            //throw $th;
        }
    }

    public function anularEtiquetaQC(Request $request) {
        $qr = $request->qr;
        $qr = str_replace("\'", '-', $qr);
        $qr = str_replace("'", '-', $qr);
        $kanban = $request->kanban;
        // $kanban2 = $request->kanban2;
        $userId = $request->userId;
        $kanbanActivo = $kanban;

        try {
            $data = EpEtiqueta::where('qr', $qr)->where('kanban', $kanbanActivo)->first();

            if (!$data) {
                return $this->setResponse([], 'La etiqueta escaneada no existe o no corresponde al kanban/s ingresado/s.', true);
            }

            $evento = EpEtiquetasEventos::create([
                'kanban'         => $kanbanActivo,
                'qr_etiqueta'    => $qr,
                'user_id'        => $userId,
                'evento'         => EventosQr::ANULACION_ETIQUETA,
                'info_adi'       => '',
            ]);

            $estado = EstadosQr::VALIDADA;

            EpEtiqueta::where('qr', $qr)->update(['estado' => $estado, 'kanban' => null]);
            // $data = EpEtiqueta::where('qr', $qr)->first();

            return $this->setResponse(EpEtiqueta::with('modelod')->where(function ($q) use ($kanban) {
                $q->where('kanban', $kanban);
            })->get()->toArray());
        } catch (\Throwable $th) {
            Log::error("EpEtiquetaController::anularEtiquetaQc : " . $th->getMessage());
            return $this->setResponse([], 'No se pudo actualizar la etiqueta o no existe', true);
            //throw $th;
        }
    }

    public function validarEtiquetas($kanbanCode, $userId) {
        // $etiquetas = EpEtiqueta::where('kanban', $kanbanCode)->where('estado', 'CREADA')->get();
        //USADO PARA LA PRIMERA IMPRESION DE LAS ETIQUETAS
        try {
            // EpEtiqueta::where('kanban_impresion', $kanbanCode)->where('airbag', false)->where('estado', EstadosQr::CREADA)->update(['estado' => EstadosQr::VALIDADA]);

            EpKanbanEstado::create([
                'user_impresion' => $userId,
                'kanban'         => $kanbanCode
            ]);

            return $this->setResponse([], 'Todas las etiquetas fueron validadas, puede proceder con la costura.');
        } catch (\Throwable $th) {
            Log::error("EpEtiquetaController::validarEtiquetas : " . $th->getMessage());
            return $this->setResponse([], 'No se pudo validar todas las etiquetas', true);
            //throw $th;
        }
    }

    public function generarEtiquetaReposicion(Request $request) {
        $kanban = $request->kanban;
        $lado = $request->lado;
        $tipo = $request->tipo;
        $user = $request->user;
        $modelo = $request?->modelo; //Para imprimir FLEX

        if ($lado == '100%') {
            $lado = '00';
        }

        if ($tipo == 'CUSHION') {
            $tipo = 'CS';
        } else {
            if (($lado != 'RH' && $lado != 'LH') || $tipo == 'BACK') {
                $tipo = 'BC';
            }
        }

        if ($modelo) {
            $etiqueta = EpEtiqueta::where('modelo_id', $modelo)->where('airbag', false)->where('lado', $lado)->where('tipo', $tipo)->first();
            $linea = "F";
            $linea_real = "F";
        } else {
            $etiqueta = EpEtiqueta::where('kanban_impresion', $kanban)->where('airbag', false)->where('lado', $lado)->where('tipo', $tipo)->first();
            $linea = $etiqueta->linea;
            $linea_real = $etiqueta->linea_real;
        }

        $secuenciaInicial = EtiquetaService::getProximoNumeroSecuencia();

        $secuencia = str_pad($secuenciaInicial, 4, "0", STR_PAD_LEFT);

        $qr = date("Ymd") . "1" . $secuencia . $etiqueta->codigo . $etiqueta->ubicacion . $lado . $etiqueta->tipo  . $linea . '1SAR000';

        $creada = EpEtiqueta::create([
            'kanban_impresion'  => $modelo ? null : $etiqueta->kanban_impresion,
            'id_etiqueta'       => 0,
            'modelo'            => $etiqueta->modelo,
            'modelo_id'         => $etiqueta->modelo_id,
            'lado'              => $lado,
            'secuencia'         => $secuencia,
            'estado'            => EstadosQr::VALIDADA,
            'qr'                => $qr,
            'ubicacion'         => $etiqueta->ubicacion,
            'tipo'              => $etiqueta->tipo,
            'linea'             => $linea,
            'linea_real'        => $linea_real,
            'codigo'            => $etiqueta->codigo, //$tipo->codigo,
            'user_id'           => $user,
            'vehiculo'          => LineasModelo::VEHICULOS[$etiqueta->modelo],
            'airbag'            => false
        ]);

        if ($creada) {
            EpEtiquetaMid::create([
                'qr'            => $qr,
                'modelo_id'     => $etiqueta->modelo_id,
                'linea_real'    => $etiqueta->linea_real
            ]);
        }

        $etiquetas = EpEtiqueta::with('modelod')->where('id', $creada->id)->get()->ToArray();

        return $this->setResponse($etiquetas);
    }

    private function validaEtiquetaDuplicada(string $qr): bool {
        //VERIFICO SI LA ETIQUETA EXISTE EN EL ÚLTIMO DÍA

        $fecha = new DateTime();
        $fecha->sub(new DateInterval('P1D'));
        $existe = EpEtiqueta::where('qr', $qr)->where('estado', '<>', 'RETRABAJO')->where('estado', '<>', 'SCRAP')->where('airbag', false)->where('created_at', '>=', $fecha)->first();

        return ($existe ? false : true);
    }

    public function store(Request $request) {

        $kanban = Kanbans::with(['modelo.partes.vehiculo', 'modelo.fila', 'estado'])
            ->where('codigo', $request->kanban)->first();
        $linea = $request->linea;
        $user = $request->userId;
        $cantidad = $kanban->modelo->cantidad;
        $lineaOriginal = '';

        // Log::alert($kanban);

        if (is_null($linea)) {
            $lineasModelo = ModeloLinea::where('modelo_id', $kanban->modelo->id)->first();
            if ($lineasModelo) {
                $linea = $lineasModelo->linea_id;
            }
        }

        if (is_null($linea)) {
            $linea = "1";
        }

        $lineaOriginal = $linea;

        if (strlen($linea) > 1) {
            $linea = "0";
        }

        //Inicio la impresión generando los registros

        $tipos = Partes::with(['tipo', 'lado'])->whereHas('tipo', function ($q) use ($kanban) {
            if ($kanban->modelo->airbag) {
                //SI TIENE AIRBAG LO SACO SIN BACK
                $q->where('tipo', 'CUSHION');
                $q->orWhere('tipo', 'T-UP');
            } else {
                $q->where('tipo', 'CUSHION');
                $q->orWhere('tipo', 'T-UP');
                $q->orWhere('tipo', 'BACK');
            }
        })
            ->whereHas('vehiculo', function ($q) use ($kanban) {
                $vehiculo = LineasModelo::VEHICULOS[$kanban->modelo->nombre];
                if (strpos($vehiculo, '/') > 0) {
                    $vehiculo = substr($vehiculo, 0, strpos($vehiculo, '/'));
                }
                $q->where('codigo', 'like', '%' . $vehiculo . '%');
            })
            ->where('modelo_id', $kanban->modelo_id)
            ->where('activo', true)
            ->orderBy('vehiculo_id', 'ASC')
            ->orderBy('lado_id', 'ASC')
            ->get();

        // Log::alert(json_encode($tipos, JSON_PRETTY_PRINT));

        $ubicacion = $kanban->modelo->fila->fila;

        if ($ubicacion == 'FRONT') {
            $ubicacion = 'FR';
        } else if ($ubicacion == 'REAR 1') {
            $ubicacion = 'R1';
        } else if ($ubicacion == 'REAR 2') {
            $ubicacion = 'R2';
        } else if ($ubicacion == 'REAR 3') {
            $ubicacion = 'R3';
        } else if ($ubicacion == 'SUP') {
            $ubicacion = 'SU';
        }

        //TOMO LA SECUENCIA Y ACUMULO HASTA 9999, LUEGO REINICIO
        // $existente = EpEtiqueta::where('estado', '<>', 'RETRABAJO')->where('airbag', false)->orderBy('id', 'DESC')->first();
        // $existente = EpEtiqueta::where('estado', '<>', 'RETRABAJO')
        //     ->where(function ($q) {
        //         $q->where('airbag', false);
        //         $q->orWhere('airbag', null);
        //     })
        //     ->orderBy('id', 'DESC')->first();


        // if ($existente) {
        //     $secuenciaInicial = intval($existente->secuencia) + 1;
        //     if ($secuenciaInicial >= 9999) {
        //         $secuenciaInicial = 0;
        //     }
        // } else {
        //     $secuenciaInicial = 0;
        // }

        $secuenciaInicial = EtiquetaService::getProximoNumeroSecuencia();

        DB::beginTransaction();

        foreach ($tipos as $tipo) {
            for ($i = 0; $i < $cantidad; $i++) {

                $lado = $tipo->lado->lado;
                if ($lado == '100%') {
                    $lado = '00';
                }

                $tipoS = 'CS';

                if ($tipo->tipo->tipo == 'CUSHION') {
                    $tipoS = 'CS';
                } else {
                    if (($lado != 'RH' && $lado != 'LH') || $tipo->tipo->tipo == 'BACK') {
                        $tipoS = 'BC';
                    }
                }

                $tipoCodigo = $tipo->codigo;

                if (strlen($tipo->codigo) == 13) {
                    $tipoCodigo = $tipo->codigo . '0';
                } else if (strlen($tipo->codigo) == 12) {
                    $tipoCodigo = $tipo->codigo . '00';
                } else if (strlen($tipo->codigo) == 11) {
                    $tipoCodigo = $tipo->codigo . '-00';
                }

                $secuencia = str_pad($secuenciaInicial, 4, "0", STR_PAD_LEFT);
                $qr = date("Ymd") . "1" . $secuencia . $tipoCodigo . $ubicacion . $lado . $tipoS  . $linea . '1SAR000';

                if (!$this->validaEtiquetaDuplicada($qr)) {
                    DB::rollBack();
                    Log::error('EpEtiquetaController::store : Se aborta creación de etiquetas. Etiqueta duplicada en el último día : ' . $qr);
                    return $this->setResponse([], 'El sistema detecto que se van a generar etiquetas duplicadas. Comuníquese con el encargado de IT.', true);
                }

                // $qr = date("Ydm") . $linea . $secuencia . $tipo->codigo . $ubicacion . $lado . $tipoS  . '11SAR000';

                EpEtiqueta::create([
                    'kanban_impresion'  => $kanban->codigo,
                    'id_etiqueta'       => 0,
                    'modelo'            => $kanban->modelo->nombre,
                    'modelo_id'         => $kanban->modelo->id,
                    'lado'              => $lado,
                    'secuencia'         => $secuencia,
                    'estado'            => EstadosQr::VALIDADA,
                    'qr'                => $qr,
                    'ubicacion'         => $ubicacion,
                    'tipo'              => $tipoS,
                    'linea'             => $linea,
                    'linea_real'        => $lineaOriginal,
                    'codigo'            => $tipoCodigo, //$tipo->codigo,
                    'user_id'           => $user,
                    'vehiculo'          => LineasModelo::VEHICULOS[$kanban->modelo->nombre],
                    'airbag'            => false
                ]);

                $secuenciaInicial = $secuenciaInicial + 1;
                if ($secuenciaInicial >= 9999) {
                    $secuenciaInicial = 0;
                }
            }
        }

        DB::commit();

        $etiquetas = EpEtiqueta::with('modelod')->where('kanban_impresion', $kanban->codigo)->get()->ToArray();

        return $this->setResponse($etiquetas);
    }

    public function getQrInfo(Request $request) {
        $qr = $request->qr;
        $qr = str_replace("'", '-', $qr);
        $qr = strtoupper($qr);

        //Verifico existencia
        $existe = EpEtiqueta::with(['historial.linea', 'historial.userValidacion', 'historial.userValidacionCaraB', 'modelod', 'reparaciones.falla', 'reparaciones.user_retrabajo', 'reparaciones.imagen', 'userImpresion', 'userValidacion', 'userValidacionCaraB'])->where('qr', $qr)->first();

        if (!$existe) {
            return $this->setResponse([], 'No existe una etiqueta asociada al codigo ingresado', true);
        }


        return $this->setResponse($existe->toArray());
    }
}
