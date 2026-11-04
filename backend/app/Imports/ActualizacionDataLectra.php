<?php

namespace App\Imports;

use App\Models\MaterialesPiezas;
use App\Models\ModeloDado;
use App\Models\ModeloKanbanPadre;
use App\Models\Modelos;
use App\Models\ModelosCompartidos;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;


class ActualizacionDataLectra implements ToModel {

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


    private function getTimeString($time) {

        $tempMinutes = $time / 60;
        $hours = 0;

        if ($tempMinutes >= 60) {
            $hours = 1;
            $tempMinutes = $tempMinutes - 60;
        }

        $minutes = intval($tempMinutes);
        $seconds = ceil(($tempMinutes - $minutes) * 60);

        if ($seconds >= 60) {
            $seconds = $seconds - 60;
            $minutes = $minutes + 1;
        }

        return $this->format($hours) . ":" . $this->format($minutes) . ":" . $this->format($seconds);
    }

    public function model(array $row) {

        try {
            $finalizado = trim($row[2]);
            $datosFiables = trim($row[3]);

            // Log::alert($finalizado);
            // Log::alert("@@@@@@@@@@@@@@@@@@@");
            // Log::alert($datosFiables);
            // Log::alert($row[2]);
            // Log::alert("@@@@@@@@@@@@@@@@@@@");


            if (!$finalizado || !$datosFiables) {
                //Si no está finalizado, lo tomo como incorrecto o erroneo
                return null;
            }

            $dado = $row[4];
            $lectra = str_replace("LECTRA ", "", $row[6]);
            $timeSeconds = floatval($row[16]);

            if ($timeSeconds < 30) {
                //Si dura menos de 30 segundos no es un corte valido
                return null;
            }

            $time = $this->getTimeString($timeSeconds);
            // $time = $this->transformTime($timeSeconds);

            if ($dado == '' || is_null($dado) || $dado == "NULL") {
                return null;
            }

            // $fechaActualizacion = DateTime::createFromFormat('U.u', $row[1]);
            // Log::alert($row);
            // Log::alert($fechaActualizacion->format('Y-m-d H:i:s'));

            $dado = str_replace(".PLX", "", $dado);
            $dadoTemp = str_replace("'", "-", $dado);
            $dadoTemp = str_replace("?", "_", $dadoTemp);
            $dadoTemp = str_replace("IX6-", "", $dadoTemp);
            $dadoTemp = str_replace("I6-", "", $dadoTemp);
            $datosDado = explode("-", $dadoTemp);


            // Log::alert($dado);
            // Log::alert($time);
            // Log::alert(json_encode($datosDado, JSON_PRETTY_PRINT));

            if (count($datosDado) == 0) {
                return null;
            }

            if (count($datosDado) < 4) {
                $material = $datosDado[count($datosDado) - 1];
            } else {
                $material = $datosDado[count($datosDado) - 3];
            }

            //Separo el dado por _ para obtener los modelos
            $datosDadoModelos = explode("_", $dadoTemp);
            if (count($datosDadoModelos) == 0) {
                $modelos = [$datosDado[0]];
            } else {
                $modelos = explode("-", $datosDadoModelos[0]);
            }

            if (count($datosDado) < 4) {
                //ES UNA REPOSICIÓN POSIBLEMENTE
                $existe = ModeloKanbanPadre::with(['modelo'])
                    ->where('dado', $dado)
                    ->get();
            } else {
                if (count($modelos) > 1) {
                    $existe = ModeloKanbanPadre::with(['modelo'])
                        ->whereHas('modelo', function ($q) use ($modelos) {
                            $q->whereIn('nombre', $modelos);
                        })
                        ->whereRaw("dado like '%-" . $material . "-%'")
                        ->get();
                } else {
                    $existe = ModeloKanbanPadre::with(['modelo'])
                        ->whereHas('modelo', function ($q) use ($modelos) {
                            $q->where('nombre', $modelos[0]);
                        })
                        ->whereRaw("dado like '%-" . $material . "-%'")
                        ->get();
                }
            }


            if (count($existe) > 0) {
                foreach ($existe as $e) {
                    // Log::alert($e);
                    //Actualizo el tiempo del dado en la lectra que leo
                    if ($lectra == 1 || $lectra == 2) {
                        ModeloKanbanPadre::where('id', $e->id)
                            ->update([
                                't_lectra1'             => $time,
                                't_lectra2'             => $time,
                                'dado'                  => $dado,
                                // 'fecha_actualizacion'   => $fechaActualizacion->format('Y-m-d H:i:s')
                            ]);
                    } else if ($lectra == 3 || $lectra == 4) {
                        ModeloKanbanPadre::where('id', $e->id)
                            ->update([
                                't_lectra3'                 => $time,
                                't_lectra4'                 => $time,
                                'dado'                      => $dado,
                                // 'fecha_actualizacion2'      => $fechaActualizacion->format('Y-m-d H:i:s')
                            ]);
                    }
                }
            } else {

                $modelo = Modelos::where('nombre', $modelos[0])->first();
                if (count($modelos) > 1 && $modelo) {
                    $existeCompartido = ModelosCompartidos::where(function ($q) use ($modelo) {
                        $q->where('modelo1_id', $modelo->id);
                        $q->orWhere('modelo2_id', $modelo->id);
                        $q->orWhere('modelo3_id', $modelo->id);
                    })->first();
                } else {
                    $existeCompartido = false;
                }

                $mat = MaterialesPiezas::where('codigo_interno', $material)->first();

                ModeloKanbanPadre::create([
                    't_lectra1'         => $this->transformTime($time),
                    't_lectra2'         => $this->transformTime($time),
                    't_lectra3'         => $this->transformTime($time),
                    't_lectra4'         => $this->transformTime($time),
                    'dado'              => $dado,
                    't_posicionamiento' => "00:02:00",
                    'modelo_id'         => $existeCompartido ? null : ($modelo ? $modelo->id : null),
                    'material_id'       => $mat ? $mat->id : null,
                    'compartido_id'     => $existeCompartido ? $existeCompartido->id : null
                ]);
            }

            // Log::alert("======================================");


            // Log::alert($existe);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }
}
