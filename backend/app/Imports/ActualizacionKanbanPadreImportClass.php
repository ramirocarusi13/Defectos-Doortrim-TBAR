<?php

namespace App\Imports;

use App\Models\MaterialesPiezas;
use App\Models\ModeloDado;
use App\Models\ModeloKanbanPadre;
use App\Models\Modelos;
use App\Models\ModelosCompartidos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;


class ActualizacionKanbanPadreImportClass implements ToModel {

    private function format($number) {

        $tmp = strval($number);
        if (strlen($tmp) < 2) {
            return "0" . $tmp;
        }

        return  $tmp;
    }

    private function transformTime($time) {

        if ($time == '' || is_null($time) || $time == "NULL") {
            return null;
        }

        $total = floatval($time) * 24;
        $hours = floor($total);
        $minute_fraction = $total - $hours;
        $minutes = $minute_fraction * 60;

        $minutes_whole = floor($minutes);
        $seconds_fraction = $minutes - $minutes_whole;
        $seconds = floor($seconds_fraction * 60);



        $display = $this->format($hours) . ":" . $this->format($minutes_whole) . ":" . $this->format($seconds);

        return $display;
    }

    public function model(array $row) {

        try {
            // Log::alert("PASO POR IMPORT");
            // Log::alert($row);
            $tLectra1 = $row[8];
            $tLectra2 = $row[8];
            $tLectra3 = $row[9];
            $tLectra4 = $row[10];
            $posicionamiento = "00:02:00";
            $dado = $row[2];

            $modelos = $row[0];
            $dado = str_replace('.PLX', '', $dado);

            $dadoParseado = str_replace('I6-', '', $dado);
            $dadoParseado = str_replace('IX6-', '', $dadoParseado);

            if ($dado == '' || is_null($dado) || $dado == "NULL") {
                return null;
            }

            $mods = explode("-", $modelos);

            $esCompartido = count($mods) > 1;
            $idCompartido = null;

            foreach ($mods as $m) {
                $modelo = Modelos::where('nombre', $m)->first();

                $tmpMateriales = explode("_", $dadoParseado);

                if (count($tmpMateriales) > 0) {
                    if (count($tmpMateriales) == 1) {
                        $materiales = explode("-", $tmpMateriales[0]);
                    } else {
                        $materiales = explode("-", $tmpMateriales[1]);
                    }
                    $material = MaterialesPiezas::where('codigo_interno', strval($materiales[1]))->first();
                } else {
                    $material = null;
                }

                if ($esCompartido) {
                    $existeCompartido = ModelosCompartidos::where(function ($q) use ($modelo) {
                        $q->where('modelo1_id', $modelo->id);
                        $q->orWhere('modelo2_id', $modelo->id);
                        $q->orWhere('modelo3_id', $modelo->id);
                    })->first();

                    if (!$existeCompartido) {
                        //CREO EL COMPARTIDO
                        $mod1 = null;
                        $mod2 = null;
                        $mod3 = null;
                        $name = '';
                        $index = 0;

                        foreach ($mods as $m) {
                            $modeloTmp = Modelos::where('nombre', $m)->first();
                            if ($modeloTmp) {
                                $name = $name != '' ? $name . ' - ' . $modeloTmp->nombre : $modeloTmp->nombre;

                                if ($index == 0) {
                                    $mod1 = $modeloTmp->id;
                                } else if ($index == 1) {
                                    $mod2 = $modeloTmp->id;
                                } else if ($index == 2) {
                                    $mod3 = $modeloTmp->id;
                                }
                            }

                            $index = $index + 1;
                        }
                        $existeCompartido = ModelosCompartidos::create([
                            'name'          => $name,
                            'modelo1_id'    => $mod1,
                            'modelo2_id'    => $mod2,
                            'modelo3_id'    => $mod3,
                        ]);
                    }

                    $idCompartido = $existeCompartido->id;
                }

                if ($modelo) {
                    $data = [
                        't_lectra1'         => $this->transformTime($tLectra1),
                        't_lectra2'         => $this->transformTime($tLectra2),
                        't_lectra3'         => $this->transformTime($tLectra3),
                        't_lectra4'         => $this->transformTime($tLectra4),
                        't_posicionamiento' => $this->transformTime($posicionamiento),
                        'modelo_id'         => $idCompartido ? null : $modelo->id,
                        'material_id'       => $material ? $material->id : null,
                        'compartido_id'     => $idCompartido
                    ];

                    if ($idCompartido) {
                        if($material){
                            $existe = ModeloKanbanPadre::where('material_id', $material->id)->where('compartido_id', $idCompartido)->first();
                        }else{
                            $existe = ModeloKanbanPadre::where('dado', $dado)->where('compartido_id', $idCompartido)->first();
                        }

                        if ($existe) {
                            ModeloKanbanPadre::where('id', $existe->id)->update($data);
                        } else {
                            $data['dado'] = $dado;
                            $existe = ModeloKanbanPadre::create($data);
                        }
                    } else {
                        if ($material) {
                            $existe = ModeloKanbanPadre::where('material_id', $material->id)->where('modelo_id', $modelo->id)->first();
                        } else {
                            $existe = ModeloKanbanPadre::where('dado', $dado)->where('modelo_id', $modelo->id)->first();
                        }

                        if ($existe) {
                            if ($material) {
                                ModeloKanbanPadre::where('material_id', $material->id)->where('modelo_id', $modelo->id)->update($data);
                            } else {
                                ModeloKanbanPadre::where('dado', $dado)->where('modelo_id', $modelo->id)->update($data);
                            }
                        } else {
                            $data['dado'] = $dado;
                            $existe = ModeloKanbanPadre::create($data);
                        }

                        // $existe = ModeloKanbanPadre::where('dado', $dado)->where('modelo_id', $modelo->id)->first();
                        $orden = ModeloDado::selectRaw('MAX(ordenCompleto) as orden')->where('modelo_id', $modelo->id)->first();
                    }

                    //Creo el dato en modelo_dados
                    //Verifico si existe, si no, actualizo
                    $data = [
                        'dado_id'       => $existe->id,
                        'tipo'          => '',
                        'modelo_id'     => $idCompartido > 0 ? null : $modelo->id,
                        'esA'           => false,
                        'esB'           => false,
                        'ordenA'        => 0,
                        'ordenB'        => 0,
                        'ordenCompleto' => $idCompartido ? 1 : ($orden->orden ?  intval($orden->orden) + 1 : 1)
                    ];

                    $existeModeloDado = ModeloDado::where('dado_id', $existe->id)->first();
                    if ($existeModeloDado) {
                        $data['esA'] = $existeModeloDado->esA;
                        $data['esB'] = $existeModeloDado->esB;
                        $data['ordenA'] = $existeModeloDado->ordenA;
                        $data['ordenB'] = $existeModeloDado->ordenB;
                        $data['ordenCompleto'] = $existeModeloDado->ordenCompleto;

                        ModeloDado::where('id', $existeModeloDado->id)->update($data);
                    } else {
                        ModeloDado::create($data);
                    }
                }
            }
        } catch (\Throwable $th) {
            Log::alert("ActualizacionKanbanPadreImportClass::model - " . $th->getMessage());
            // Log::alert($row);
        }
    }
}
