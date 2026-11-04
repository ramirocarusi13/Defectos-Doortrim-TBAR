<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\CarbonInterval;

class LectraEstado extends Model {
    use HasFactory;

    protected $fillable = [
        'lectra',
        'dado_id',
        'pieza_id',
        'esA',
        'esB',
        'esC',
        'es_reposicion',
        'fin',
        'inicio',
        'inicio_plan',
        'fin_plan',
        'modelo',
        'operacion',
        'mtto_falla_maquina',
        'mtto_cambio_cuchilla',
        'mtto_perdida_destino',
        'pc_falta_material',
        'pc_falta_carros',
        'qc_problema_calidad',
        'qc_defectos_proveedor',
        'kz_setup',
        'pr_falta_tendido',
        'pr_retendido_nylon',
        'pr_reposicion',
        'pr_habilidad',
        'pr_piqueo',
        'rrhh_rotacion',
        'rrhh_ausentismo',
        'abastecido',
        'fecha_abastecido',
        'fin_estimado',
        'demora',
        'id_reanudar',
        'es_plan_anterior'
    ];

    protected $casts = [
        'esA' => 'boolean',
        'esB' => 'boolean',
        'esC' => 'boolean',
        'es_reposicion' => 'boolean',
        'abastecido' => 'boolean',
        'es_plan_anterior' => 'boolean',
    ];

    public static function boot() {
        parent::boot();

        function calcularDemoraExcluyendoLapsos(Carbon $inicio, Carbon $fin): int {
            // $totalMinutos = 0;
            // $actual = $inicio->copy();

            return (int) $fin->diffInMinutes($inicio, true); // Carbon 3: float con signo -> entero absoluto (como Carbon 2)

            // while ($actual < $fin) {
            //     // $hora = $actual->format('H:i');

            //     // if (
            //     //     !(
            //     //         ($hora >= '15:10' && $hora < '15:40') ||
            //     //         ($hora >= '00:50' && $hora < '06:00')
            //     //     )
            //     // ) {
            //     //     $totalMinutos++;
            //     // }

            //     // if (
            //     //     !(
            //     //         ($hora >= '09:30' && $hora < '09:40') ||
            //     //         ($hora >= '00:50' && $hora < '06:00')
            //     //     )
            //     // ) {
            //     $totalMinutos++;
            //     // }

            //     $actual->addMinute();
            // }

            // return $totalMinutos;
        }

        static::updating(function ($model) {
            if (!is_null($model->fin)) {

                $inicio = Carbon::parse($model->inicio);
                $fin = Carbon::parse($model->fin);
                if (!is_null($model->fin_estimado)) {
                    $finEstimada = Carbon::parse($model->fin_estimado);
                } else {
                    //Obtengo la fecha fin en base a el inicio + el tiempo del dado
                    $existe = ModeloKanbanPadre::where('dado', $model->dado_id)->first();
                    $tiempos = $existe->{"t_lectra" . $model->lectra};
                    try {
                        if (is_null($tiempos) || $tiempos == '') {
                            if ($model->lectra == 1) {
                                $tiempos = $existe->{"t_lectra2"};
                            } else if ($model->lectra == 2) {
                                $tiempos = $existe->{"t_lectra1"};
                            } else if ($model->lectra == 3) {
                                $tiempos = $existe->{"t_lectra4"};
                            } else if ($model->lectra == 4) {
                                $tiempos = $existe->{"t_lectra3"};
                            }

                            if (is_null($tiempos) || $tiempos == '') {
                                $tiempos = "00:00:00";
                            }
                        }
                    } catch (\Throwable $th) {
                        $tiempos = "00:00:00";
                    }
                    $times = explode(":", $tiempos);

                    $finEstimada = $inicio->copy();
                    $finEstimada->addHours(intval($times[0]))->addMinutes(intval($times[1]))->addSeconds(intval($times[2]));
                }

                // Demora real en minutos sin contar lapsos excluidos
                $minutosReales = calcularDemoraExcluyendoLapsos($inicio, $fin);
                $minutosEstimados = calcularDemoraExcluyendoLapsos($inicio, $finEstimada);

                $demora = $minutosReales - $minutosEstimados;

                $model->demora = $demora > 0 ? $demora : 0;
                // $model->save();
            }
        });

        // static::updating(function ($model) {
        //     if (!is_null($model->fin)) {

        //         $fechaActual = new DateTime();
        //         //SI INFORMA EL FIN, ENTONCES CALCULO LA DEMORA
        //         if (!is_null($model->fin_estimado)) {
        //             $demora = "00:00";
        //             $fechaFinEstimada = DateTime::createFromFormat('Y-m-d H:i:s.v', $model->fin_estimado);
        //         } else {
        //             //BUSCO EL TIEMPO DE DADO
        //             try {
        //                 $existe = ModeloKanbanPadre::where('dado', $model->dado_id)->first();
        //                 $tiempos = $existe->{"t_lectra" . $model->lectra};

        //                 if (is_null($tiempos) || $tiempos == '') {
        //                     if ($$model->lectra == 1) {
        //                         $tiempos = $existe->{"t_lectra2"};
        //                     } else if ($$model->lectra == 2) {
        //                         $tiempos = $existe->{"t_lectra1"};
        //                     } else if ($$model->lectra == 3) {
        //                         $tiempos = $existe->{"t_lectra4"};
        //                     } else if ($$model->lectra == 4) {
        //                         $tiempos = $existe->{"t_lectra3"};
        //                     }

        //                     if (is_null($tiempos) || $tiempos == '') {
        //                         $tiempos = "00:00:00";
        //                     }
        //                 }
        //             } catch (\Throwable $th) {
        //                 $tiempos = "00:00:00";
        //             }

        //             $times = explode(":", $tiempos);

        //             //FIN = INICIO REAL + DURACION
        //             $fechaFinEstimada = new DateTime($model->inicio);
        //             $fechaFinEstimada->modify('+' . intval($times[1]) . ' minutes');
        //             $fechaFinEstimada->modify('+' . intval($times[0]) . ' hours');
        //             $fechaFinEstimada->modify('+' . intval($times[2]) . ' seconds');
        //             $demora = "00:00";
        //         }

        //         if ($fechaFinEstimada < $fechaActual) {

        //             $intervalDiff = $fechaActual->diff($fechaFinEstimada);

        //             $horasDiferencia = $intervalDiff->format('%H');
        //             $minutosDiferencia = $intervalDiff->format('%i');
        //             $diasDiferencia = $intervalDiff->format('%d');

        //             $horasDiferencia = (intval($horasDiferencia) + ($diasDiferencia * 24));
        //             if ($minutosDiferencia >= 60) {
        //                 $minutosDiferencia = $minutosDiferencia / 60;
        //                 $horasDiferencia = $horasDiferencia + intval($minutosDiferencia);

        //                 $minutosDiferencia = ($minutosDiferencia - intval($minutosDiferencia)) * 60;
        //             }
        //             $demora = str_pad($horasDiferencia, 2, "0", STR_PAD_LEFT) . ":" . str_pad($minutosDiferencia, 2, "0", STR_PAD_LEFT);
        //         }

        //         $model->demora = $demora;
        //     }
        // });
    }

    public function dado(): HasOne {
        return $this->hasOne(ModeloKanbanPadre::class, 'dado', 'dado_id');
    }

    public function pieza(): HasOne {
        return $this->hasOne(Piezas::class, 'id', 'pieza_id');
    }
}
