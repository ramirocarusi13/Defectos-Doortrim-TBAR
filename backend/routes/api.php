<?php

use App\Http\Jerarquias;
use App\Http\Kanban;
use App\Http\WmsUnidades;
use App\Models\Despachos;
use App\Models\FallasInformadas;
use App\Models\MovimientosContenido;
use App\Models\StockKanbans;
use App\Models\User;
use App\Models\UserLinea;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Route::get('/temp_dolly', [\App\Http\Controllers\MovimientosController::class, 'moverDollyATemp']);
Route::get('test_kanbans', [\App\Http\Controllers\PcPlanProduccionController::class, 'generaLoteDeKanbansACortar']);

Route::group(['middleware' => ['cors', 'json.response']], function () {

    Route::prefix('auto')->group(function () {
        Route::get('/generaStocKanbanPorFila', [\App\Http\Controllers\AutoController::class, 'creaStockKanban']);
        Route::get('/generaVolumenDespachosProduccion', [\App\Http\Controllers\AutoController::class, 'generaVolumenDespachosPBI']);
        Route::get('/generaDespachoPedidoEnBaseaItems', [\App\Http\Controllers\AutoController::class, 'generaDespachoPedidoEnBaseaItems']);
        Route::get('/generaTemporalHoraHoraEficienciaProduccion', [\App\Http\Controllers\AutoController::class, 'generaTemporalHoraHoraEficienciaProduccion']);
    });
});

Route::group(['middleware' => ['cors', 'json.response']], function () {

    Route::get('print-jobs', [\App\Http\Controllers\FilePrintController::class, 'getFilesToPrint']);
    Route::post('print-jobs/mark-printed', [\App\Http\Controllers\FilePrintController::class, 'markAsPrinted']);

    // Modelos JITMS para la vista /config (portado de eol-tbar)
    Route::get('modelos-jitms', [\App\Http\Controllers\ModeloJitmsController::class, 'modelosJitms']);

    // Trazabilidad de defectos EOL (marcado calidad -> reparacion produccion)
    Route::get('eol-defectos', [\App\Http\Controllers\EolDefectoController::class, 'index']);
    Route::match(['get', 'post'], 'eol-defectos/reporte', [\App\Http\Controllers\EolDefectoController::class, 'reporte']);
    Route::match(['get', 'post'], 'eol-defectos/reporte-fmds', [\App\Http\Controllers\EolDefectoController::class, 'reporteFmds']);
    Route::match(['get', 'post'], 'eol-defectos/reporte-diario', [\App\Http\Controllers\EolDefectoController::class, 'reporteDiario']);
    // Tablero en tiempo real: puesto / turno / operario que genero cada defecto.
    Route::get('eol-defectos/reporte-origen', [\App\Http\Controllers\ReporteOrigenController::class, 'index']);

    // Reporte diario de QC (replica el excel del cliente): TURNO A=AMARILLO, B=BLANCO, N=NARANJA.
    Route::get('reporte-qc/mensual', [\App\Http\Controllers\ReporteQcController::class, 'mensual']);
    Route::get('reporte-qc/top', [\App\Http\Controllers\ReporteQcController::class, 'top']);

    // Dashboard operativo de defectos EOL (KPIs + rankings agregados por periodo).
    Route::get('dashboard-qc', [\App\Http\Controllers\DashboardQcController::class, 'index']);
    Route::get('dashboard-qc/operario', [\App\Http\Controllers\DashboardQcController::class, 'operario']);
    Route::get('dashboard-qc/evolucion', [\App\Http\Controllers\DashboardQcController::class, 'evolucion']);

    // Reporte de consumo de componentes declarados por produccion al retrabajar.
    Route::get('reporte-componentes', [\App\Http\Controllers\ReporteComponentesController::class, 'index']);

    Route::get('eol-defectos/andon', [\App\Http\Controllers\EolDefectoController::class, 'andon']);
    Route::get('eol-defectos/andon-interlock', [\App\Http\Controllers\EolDefectoController::class, 'andonInterlock']);
    Route::post('eol-defecto', [\App\Http\Controllers\EolDefectoController::class, 'store']);
    Route::put('eol-defecto/{id}/reparar', [\App\Http\Controllers\EolDefectoController::class, 'reparar']);
    Route::put('eol-defecto/{id}/validar', [\App\Http\Controllers\EolDefectoController::class, 'validar']);
    Route::get('eol-defecto/{id}/eventos', [\App\Http\Controllers\EolDefectoController::class, 'eventos']);
    // Ficha completa del defecto (marcado, retrabajo, componentes, origen, validacion, eventos).
    Route::get('eol-defecto/{id}/trazabilidad', [\App\Http\Controllers\EolDefectoController::class, 'trazabilidad']);
    Route::match(['get', 'post'], 'eol-defectos/eventos', [\App\Http\Controllers\EolDefectoController::class, 'eventos']);
    Route::put('eol-defecto/{id}/asignar-operador', [\App\Http\Controllers\EolDefectoController::class, 'asignarOperador']);

    // Linea de door (cambio de piezas DOOR / ARM_REST): maestro de piezas + cambios + mini buffer
    Route::post('eol-piezas', [\App\Http\Controllers\EolDoorController::class, 'altaPiezas']);
    Route::get('eol-piezas', [\App\Http\Controllers\EolDoorController::class, 'piezas']);
    Route::get('eol-piezas/{qr}/eventos', [\App\Http\Controllers\EolDoorController::class, 'eventosPieza']);
    Route::get('eol-piezas/{qr}', [\App\Http\Controllers\EolDoorController::class, 'ficha']);

    Route::get('eol-buffer/stock', [\App\Http\Controllers\EolDoorController::class, 'bufferStock']);

    Route::get('eol-door/contexto', [\App\Http\Controllers\EolDoorController::class, 'contexto']);
    Route::get('eol-door/andon-buffer', [\App\Http\Controllers\EolDoorController::class, 'andonBuffer']);
    Route::post('eol-door', [\App\Http\Controllers\EolDoorController::class, 'crearDoor']);
    Route::post('eol-door/marcar-y-cambiar', [\App\Http\Controllers\EolDoorController::class, 'marcarYCambiar']);
    Route::get('eol-door/defectos', [\App\Http\Controllers\EolDoorController::class, 'defectos']);
    Route::post('eol-door/qr', [\App\Http\Controllers\EolDoorController::class, 'interpretarQr']);
    Route::get('eol-door/control-final', [\App\Http\Controllers\EolDoorController::class, 'controlFinal']);
    Route::post('eol-door/marcar-retrabajo', [\App\Http\Controllers\EolDoorController::class, 'marcarRetrabajo']);
    Route::get('eol-door/retrabajos', [\App\Http\Controllers\EolDoorController::class, 'retrabajos']);
    Route::put('eol-door/defecto/{id}/retrabajar', [\App\Http\Controllers\EolDoorController::class, 'retrabajar']);
    Route::get('eol-door/validaciones', [\App\Http\Controllers\EolDoorController::class, 'validaciones']);
    Route::put('eol-door/defecto/{id}/validar', [\App\Http\Controllers\EolDoorController::class, 'validarDefecto']);
    Route::get('eol-door', [\App\Http\Controllers\EolDoorController::class, 'listarDoors']);
    Route::put('eol-door/pieza/{qr}/tomar', [\App\Http\Controllers\EolDoorController::class, 'tomarPieza']);
    Route::put('eol-door/pieza/{qr}/reingresar', [\App\Http\Controllers\EolDoorController::class, 'reingresarPieza']);
    Route::put('eol-door/pieza/{qr}/validar', [\App\Http\Controllers\EolDoorController::class, 'validarPieza']);
    Route::put('eol-door/pieza/{qr}/scrap', [\App\Http\Controllers\EolDoorController::class, 'scrapPieza']);

    Route::get('test_i', function () {

        // $turno = getTurnoActual();
        $turno = obtenerTurnoActual();

        return $turno;
        // Log::alert("PASO");

        // Log::alert(Despachos::where('pendiente', true)
        //     ->whereHas('items', function ($q) {
        //         $q->where('produccion', true);
        //         $q->where('deposito_id', null);
        //         $q->where('modelo', 'SFHG');
        //         $q->where(function ($r) {
        //             $r->where('pickeado', 0);
        //             $r->orWhere('pickeado', null);
        //             $r->orWhere('pickeado', false);
        //         });
        //     })->orderBy('id', 'ASC')
        //     ->toSql());
        // $rolloService = new RollosService(1);

        // $rolloService->procesarIngreso("3.01.251ñ7940Dñ224855ñ39,66ñ39,55ñ03ñ27,02ñCUITÑ30'70829380'2");
    });

    Route::get('fix_turno_defectos', function () {

        $defectos = FallasInformadas::with('user')->whereNotNull('qr')
            // ->where(function ($q) {
            //     $q->where('turno', 'TT');
            //     $q->orWhere('turno', 'TM');
            // })
            ->get();
        // Log::alert($defectos);
        foreach ($defectos as $defecto) {
            // Log::alert($defecto);
            if (!is_null($defecto->user)) {
                FallasInformadas::where('id', $defecto->id)
                    ->update([
                        'turno' => $defecto->user->turno
                    ]);
            } else {
                Log::alert($defecto);
            }
        }

        return 'OK';
    });

    Route::get('test_lectra', [App\Http\Controllers\LectraController::class, 'armaTemporalPlan']);
    // Route::get('fix_tmp_ubicaciones', function () {
    //     $depositos = [8];

    //     foreach ($depositos as $deposito) {
    //         TmpUbicaciones::where('deposito_id', $deposito)->delete();
    //         $ubicaciones = Ubicaciones::where('deposito_id', $deposito)->orderBy('orden')->get();

    //         foreach ($ubicaciones as $ub) {
    //             $ref = null;
    //             //Obtengo el stock de la posicion
    //             $stock = StockKanbans::where('ubicacion', $ub->nombre)->get();
    //             foreach ($stock as $s) {
    //                 //Verifico por cada item en stock, si hay un despacho pendiente que lo haya pickeado, si es asi, no está en posicion

    //             }

    //             TmpUbicaciones::create([
    //                 'deposito_id'   => $ub->deposito_id,
    //                 'ubicacion_id'  => $ub->id,
    //                 'nombre'        => $ub->nombre,
    //                 'ref'           => $ref
    //             ]);
    //         }
    //     }
    // });

    Route::get('test_insert_users', function () {
        $linea = 1;
        $turno = 'A';

        $members = [
            'PAPALEO SOFIA',
            'QUINTERO ALDANA',
            'RICARDO GARAY',
            'VALIENTE SERGIO',
            'RIVAROLA SANDRA',
            'INGA VANESA',
            'JAIME VANESA',
            'VASQUEZ DAIANA',
            'CORIA PABLO',
            'AGUIRRE DAIANA',
        ];

        foreach ($members as $member) {
            //VERIFICO EXISTENCIA
            $user = User::where('email', $member)->first();
            if (!$user) {
                $name = str_replace('.', '', $member);
                $name = str_replace(' ', '', $member);
                $name = strtolower($name);
                $name = 'p' . $name;

                $user = User::create([
                    'name'          => $name,
                    'email'         => $member,
                    'password'      => '$2y$10$6CAhgrXwoMc2xEjTQDtEeOnb/pL4B8bnr046QMLubHDUJn0l67d/m',
                    'tl'            => false,
                    'gl'            => false,
                    'departamento'  => 'PRODUCCION',
                    'rol'           => Jerarquias::MEMBER,
                    'turno'         => $turno
                ]);
            }

            if ($user) {
                UserLinea::updateOrCreate([
                    'user_id'   => $user->id,
                    'linea_id'  => $linea
                ], [
                    'user_id'   => $user->id,
                    'linea_id'  => $linea,
                    'sublinea'  => true
                ]);
            }
        }
    });

    Route::post('oa', [\App\Http\Controllers\OAProduccionController::class, 'store']);

    Route::get('enviar_a_deposito_temp', function () {
        $stockService = new StockService();

        $movimientos = StockKanbans::where('deposito_id', 8)->where('cantidad', '>', 0)->get();
        // $movimientos = StockKanbans::where('deposito_id', 10)->where('cantidad', '>', 0)->get();

        $stockService->generaMovimiento(WmsUnidades::KANBAN);

        foreach ($movimientos as $movimiento) {

            // Log::alert($movimiento);
            //SALE
            $stockService->insertaDetalle(
                $movimiento->ref,
                $movimiento->cantidad * -1,
                $movimiento->ubicacion_id,
                $movimiento->lote,
                WmsUnidades::KANBAN,
                $movimiento->ref_id,
            );

            //INGRESA A TEMPORAL
            $stockService->insertaDetalle(
                $movimiento->ref,
                $movimiento->cantidad,
                12815,
                // 9,
                $movimiento->lote,
                WmsUnidades::KANBAN,
                $movimiento->ref_id,
            );
            // $nuevoMovimiento = $movimiento->replicate();
            // $nuevoMovimiento->ubicacion_id = 11451;
            // $nuevoMovimiento->save();
        }

        return 'OK';
    });

    Route::get('fix_stock', function () {

        //OBTENGO EL STOCK
        $stock = MovimientosContenido::where('id', '>', 38380)->get();

        foreach ($stock as $s) {
            //VERIFICO SI EXISTE EN STOCK DE DESPACHO
            // $existe = MovimientosContenido::where('ubicacion_id', 11451)->where('cantidad', '>', 0)->where('ref', $s->ref)->first();

            // if ($existe) {
            $debeEliminar = MovimientosContenido::where('ubicacion_id', 9)->where('ref', $s->ref)->get()->count();

            if ($debeEliminar > 2) {

                $eliminar = MovimientosContenido::where(function ($q) {
                    $q->where('id', '>=', 38385);
                    $q->where('id', '<', 38604);
                })
                    ->where(function ($q) {
                        $q->where('movimiento_id', '>=', 37678);
                        $q->where('movimiento_id', '<', 37897);
                    })
                    ->where('ubicacion_id', 9)
                    ->where('cantidad', '>', 0)
                    ->where('ref', $s->ref)
                    ->orderBy('id', 'DESC')
                    ->first();

                if ($eliminar) {
                    $eliminar->delete();
                }
                // if ($eliminar) {
                //     Log::alert("ELIMINO : " . $s->ref);
                // }
            }
            //ELIMINO DE DOLLYS
            // MovimientosContenido::where('id', '>', 38385)->where('ubicacion_id', 9)->where('cantidad', '>', 0)->delete();
            // }
        }

        return 'OK';
    });

    Route::get('text_sar', function () {

        $kSar = Kanban::getKanbanSAR("P200521090431039");
        Log::alert($kSar);

        return 'OK';
    });


    Route::prefix('tablero')->group(function () {
        Route::get('/', [\App\Http\Controllers\LineaOperacionesController::class, 'index']);
    });


    Route::get('turno_actual', function () {
        $turno = getTurnoActual();

        // Log::alert("TURNOOO: " . $turno);

        return ["turno" => $turno];
    });


    Route::get('test-dados', [\App\Http\Controllers\LectraController::class, 'testDadosTiempo']);
    Route::get('fix_kanban_modelo', [\App\Http\Controllers\KanbansController::class, 'fixKanbanSinModelo']);

    // Route::get('fix_kanban_modelo', [\App\Http\Controllers\KanbansController::class, 'fixKanbanSinModelo']);


    // Route::get('test/test', [\App\Http\Controllers\MovimientosController::class, 'store']);
    //CONFIGURACION
    Route::get('configuracion', [\App\Http\Controllers\ConfiguracionController::class, 'index']);
    Route::post('configuracion', [\App\Http\Controllers\ConfiguracionController::class, 'store']);
    Route::post('configuracion/valor', [\App\Http\Controllers\ConfiguracionController::class, 'show']);
    Route::get('factory-config/version', [\App\Http\Controllers\FactoryConfigController::class, 'version']);
    Route::get('factory-config', [\App\Http\Controllers\FactoryConfigController::class, 'show']);
    Route::post('factory-config', [\App\Http\Controllers\FactoryConfigController::class, 'save']);
    Route::put('factory-config', [\App\Http\Controllers\FactoryConfigController::class, 'save']);

    Route::get('fallas/fmds', [App\Http\Controllers\FallasInformadasController::class, 'getFmds']);
    Route::post('fallas/fmds', [App\Http\Controllers\FallasInformadasController::class, 'getFmds']);
    Route::get('fallas/andon', [App\Http\Controllers\FallasInformadasController::class, 'getFallasAndon']);

    Route::post('valida_dado_etiqueta_rollo', [App\Http\Controllers\LectraController::class, 'validaDadoEtiquetaCono']);

    Route::get('nl_users', [\App\Http\Controllers\UsersControllers::class, 'index']);

    //QR Etiquetas
    Route::get('qr/pendientes/{linea}', [\App\Http\Controllers\EpEtiquetaController::class, 'getEtiquetasPendientesLinea']);

    Route::get('qr/consultar/{kanbanCode}', [\App\Http\Controllers\EpEtiquetaController::class, 'consultarKanban']);
    Route::post('qr/consultar2', [\App\Http\Controllers\EpEtiquetaController::class, 'consultarKanbans']);
    Route::post('qr/consultar/modelo_airbag', [\App\Http\Controllers\EpEtiquetaController::class, 'consultarModeloAirbag']);
    Route::get('qr/validar/{kanbanCode}/{userId}', [\App\Http\Controllers\EpEtiquetaController::class, 'validarEtiquetas']);
    Route::post('qr/validar_etiqueta', [\App\Http\Controllers\EpEtiquetaController::class, 'validarEtiqueta']);
    Route::get('qr/reimprimir/{id}/{userId}', [\App\Http\Controllers\EpEtiquetaController::class, 'reimprimirEtiqueta']);
    Route::post('qr/generar', [\App\Http\Controllers\EpEtiquetaController::class, 'store']);
    Route::post('qr/generar_reposicion', [\App\Http\Controllers\EpEtiquetaController::class, 'generarEtiquetaReposicion']);
    Route::post('qr/cambiar_estado_etiqueta', [\App\Http\Controllers\EpEtiquetaController::class, 'cambiarEstadoEtiqueta']);
    Route::post('qr/cambiar_estado_etiqueta_qc', [\App\Http\Controllers\EpEtiquetaController::class, 'cambiarEstadoEtiquetaQC']);
    Route::post('qr/anular_etiqueta_qc', [\App\Http\Controllers\EpEtiquetaController::class, 'anularEtiquetaQC']);
    Route::post('qr/actualiza_evento', [\App\Http\Controllers\EpEtiquetaController::class, 'actualizaEvento']);
    Route::post('qr/informacion', [\App\Http\Controllers\EpEtiquetaController::class, 'getQrInfo']);

    Route::post('pc/plan_semanal', [\App\Http\Controllers\PlanCosturaController::class, 'store']);
    Route::get('pc/plan_semanal', [\App\Http\Controllers\PlanCosturaController::class, 'fetchPlan']);
    Route::get('pc/plan_semanal/stock', [\App\Http\Controllers\PlanCosturaController::class, 'fetchStockPlanCostura']);
    Route::get('pc/kanbans_del_dia', [\App\Http\Controllers\PlanCosturaController::class, 'fetchKanbansAImprimirDelDia']);
    Route::post('pc/kanbans_del_dia', [\App\Http\Controllers\PlanCosturaController::class, 'filtrarKanbansDelDia']);

    //Auth
    Route::post('/login', [\App\Http\Controllers\Auth\ApiAuthController::class, 'login'])->name('login');
    // Route::get('/login', [\App\Http\Controllers\Auth\ApiAuthController::class, 'login'])->name('login');

    Route::post('trazabilidad_airbag', [\App\Http\Controllers\TrazabilidadAirbagController::class, 'getAirbagTrazabilidad']);

    //Piezas
    Route::get('pieza/getFileReposicion/{piezaId}/{modelo}/{file}/{capas}', [\App\Http\Controllers\StockPiezasController::class, 'getFileKanbanReposicion']);
    Route::get('test/dados-piezas/sync', [\App\Http\Controllers\PiezasController::class, 'syncDadosPiezasPrueba']);

    Route::get('corte/pendientes_planificacion', [\App\Http\Controllers\LectraController::class, 'getPendientesPlanificacion']);
    Route::get('corte/planificaciones', [\App\Http\Controllers\LectraController::class, 'getPlanificaciones']);
    Route::post('corte/planificacion', [\App\Http\Controllers\LectraController::class, 'getPlanificacion']);
    Route::get('pc/abastecimiento', [\App\Http\Controllers\LectraController::class, 'datosAbastecimiento']);
    Route::get('pc/abastecer/{dadoId}/{porModelo}', [\App\Http\Controllers\LectraController::class, 'abastecerDado']);

    //Ultimos egresos de buffer
    Route::get('buffer/ultimos_egresos', [\App\Http\Controllers\KanbansController::class, 'getKanbanLastOut']);
    Route::get('buffer/ultimos_ingresos', [\App\Http\Controllers\KanbansController::class, 'getKanbanLastIn']);
    Route::post('buffer/corrigekanban', [\App\Http\Controllers\KanbansController::class, 'corrijeKanbanBuffer']);

    //Estado corte lectras
    Route::get('lectras/estado', [\App\Http\Controllers\LectraController::class, 'getEstadoLectras']);
    Route::get('lectras/estado2', [\App\Http\Controllers\LectraController::class, 'getEstadoLectras2']);
    Route::get('lectras/estado3', [\App\Http\Controllers\LectraController::class, 'getEstadoLectras3']);
    Route::get('lectras/estado4', [\App\Http\Controllers\LectraController::class, 'getEstadoLectras4']);
    Route::get('lectras/stock_corte', [\App\Http\Controllers\LectraController::class, 'getEstadoStockBufferGrafico']);
    // Route::get('lectras/stock_corte_v2', [\App\Http\Controllers\LectraController::class, 'getEstadoStockBufferGraficoV2']);
    Route::get('lectra/plan/{lectra}', [\App\Http\Controllers\LectraController::class, 'getPlanLectra']);
    Route::post('corte/fin_dado', [\App\Http\Controllers\LectraController::class, 'finalizarDado']);
    Route::post('corte/actualiza/tiempos', [\App\Http\Controllers\LectraController::class, 'actualizarTiemposCorte']);

    Route::get('pc/pendientes_impresion2', [\App\Http\Controllers\PcPendienteImpresionController::class, 'getPendientes']);

    Route::post('kanban/existencia/estado', [\App\Http\Controllers\KanbansController::class, 'verificaExistenciaCambioEstado']);

    Route::get('linea/users/{linea}', [\App\Http\Controllers\UsersControllers::class, 'getUsersPorLinea']);
    Route::get('users/departamento/{departamento}', [\App\Http\Controllers\UsersControllers::class, 'getUsersPorDepartamento']);
    Route::post('users/ausentismo/search', [\App\Http\Controllers\AusentismoHoraHoraController::class, 'getUsersAusentismoHoraHora']);
    Route::post('users/ausentismo', [\App\Http\Controllers\AusentismoHoraHoraController::class, 'store']);


    Route::post('linea/tablero', [\App\Http\Controllers\LineaOperacionesController::class, 'store']);
    Route::post('linea/actualizar', [\App\Http\Controllers\LineaOperacionesController::class, 'updateTablero']);
    Route::delete('linea/tablero/{userId}', [\App\Http\Controllers\LineaOperacionesController::class, 'destroy']);
    Route::post('linea/reasignar', [\App\Http\Controllers\LineaOperacionesController::class, 'reasignar']);
    Route::get('linea/{linea}/operaciones', [\App\Http\Controllers\LineaOperacionesController::class, 'getOperacionesLinea']);
    Route::get('linea/operaciones', [\App\Http\Controllers\LineaOperacionesController::class, 'index']);
    Route::get('linea/operaciones/{id}', [\App\Http\Controllers\LineaOperacionesController::class, 'get']);
    Route::put('linea/operaciones/{lineaOperaciones}', [\App\Http\Controllers\LineaOperacionesController::class, 'update']);
    Route::post('linea/operaciones', [\App\Http\Controllers\LineaOperacionesController::class, 'saveOperacion']);
    Route::post('user/polivalencias', [\App\Http\Controllers\UserPolivalenciasController::class, 'store']);
    Route::post('user/operacion', [\App\Http\Controllers\LineaOperacionesController::class, 'moverOperador']);
    Route::get('user/{userId}/polivalencias', [\App\Http\Controllers\UserPolivalenciasController::class, 'get']);

    Route::get('user/sin_operacion', [\App\Http\Controllers\LineaOperacionesController::class, 'getUsersDisponibles']);

    Route::get('tienda/info/{codigoKanban}', [\App\Http\Controllers\TiendaController::class, 'getInfoModelByKanban']);

    Route::get('pc/pendientes_planificacion', [\App\Http\Controllers\PcPendienteImpresionController::class, 'getPendientesPlanificacion']);

    Route::get('pc/pendientes_impresion', [\App\Http\Controllers\PcPendienteImpresionController::class, 'getPendientes']);
    Route::get('test', [\App\Http\Controllers\PlanCosturaController::class, 'actualizaCortesPendientesSegunPlan']);
    Route::get('test2', [\App\Http\Controllers\PlanCosturaController::class, 'armarProximosCortes']);
    Route::post('corte/inicio_dado', [\App\Http\Controllers\LectraController::class, 'iniciarDado']);
    Route::post('lectra/estado/frenada', [\App\Http\Controllers\LectraController::class, 'verificarEstadoFrenoLectra']);
    Route::post('lectra/estado/inicio', [\App\Http\Controllers\InicioLectraController::class, 'show']);


    Route::get('lineas/ocupacion', [\App\Http\Controllers\LineasController::class, 'lineasOcupacion']);
    Route::put('kanban', [\App\Http\Controllers\KanbansController::class, 'changeStatus']);

    Route::post('tienda/pedido', [\App\Http\Controllers\TiendaController::class, 'setPedido']);

    // Alta publica para anotador EOL
    Route::post('piezas/public', [App\Http\Controllers\PiezasController::class, 'store']);

    Route::get('ubicacion', [App\Http\Controllers\UbicacionesController::class, 'index']);
    Route::post('deposito/almacenar', [App\Http\Controllers\UbicacionesMovimientosController::class, 'store']);
    Route::post('ubicacion/consultar', [App\Http\Controllers\UbicacionesController::class, 'consultarPosicion']);
    Route::post('ubicacion/kanban', [App\Http\Controllers\UbicacionesController::class, 'consultarKanbanPosicionLibre']);

    Route::post('ubicacion/contenido/consultar', [App\Http\Controllers\UbicacionesController::class, 'consultarContenidoPosicion']);

    Route::get('fallastienda', [App\Http\Controllers\TiendaController::class, 'getFallasTienda']);
    Route::post('auth/validar_usuario', [\App\Http\Controllers\ControlStrapController::class, 'validarUsuarioStrap']);
    Route::post('auth/logout', [\App\Http\Controllers\ControlStrapController::class, 'logoutStrap']);

    Route::post('despachos/boton', [\App\Http\Controllers\DespachosController::class, 'botonPresionado']);
    Route::post('despachos/consulta', [\App\Http\Controllers\DespachosController::class, 'consultaPorModelo']);
    Route::post('despachos', [\App\Http\Controllers\DespachosController::class, 'store']);

    Route::post('despachos/verifica', [\App\Http\Controllers\DespachosController::class, 'verificaYPickea']);
    Route::get('despachos/pendientes/{run}', [\App\Http\Controllers\DespachosController::class, 'getPendientes']);

    Route::post('stock/reporte', [\App\Http\Controllers\UbicacionesMovimientosController::class, 'reporte']);

    Route::post('user/validar_cod_autorizacion', [\App\Http\Controllers\UsersControllers::class, 'userValidoByCodAutorizacion']);
    // Route::post('public/users/qr-autorizacion/validar', [\App\Http\Controllers\UsersControllers::class, 'validarImpresionQrAutorizacion']);
    // Route::post('public/users/qr-autorizacion', [\App\Http\Controllers\UsersControllers::class, 'qrAutorizacionIndex']);


    //Fallas
    Route::get('fallas', [\App\Http\Controllers\CodigoFallaController::class, 'index']);
    Route::get('modelos-activos', [\App\Http\Controllers\ModelosController::class, 'activos']);
    Route::get('partes-activas', [\App\Http\Controllers\PartesController::class, 'activos']);
    Route::get('defectos-cobot', [\App\Http\Controllers\DefectoCobotController::class, 'index']);
    Route::post('defectos-cobot', [\App\Http\Controllers\DefectoCobotController::class, 'store']);
    Route::post('defectos-cobot/fallas', [\App\Http\Controllers\DefectoCobotController::class, 'storeFallas']);
    Route::post('defectos-cobot/import', [\App\Http\Controllers\DefectoCobotController::class, 'import']);
    Route::put('defectos-cobot/{defectoCobot}', [\App\Http\Controllers\DefectoCobotController::class, 'update']);
    Route::delete('defectos-cobot/{defectoCobot}', [\App\Http\Controllers\DefectoCobotController::class, 'destroy']);
    // Route::post('fallas/analisis', [\App\Http\Controllers\AnalisisDefectoController::class, 'store']);
    Route::get('kanban/{kanbanCode}/findelinea', [\App\Http\Controllers\KanbansController::class, 'getKanbanEndOfLine']);
    Route::post('etiqueta/fallas', [\App\Http\Controllers\KanbansController::class, 'getFallasEtiqueta']);


    Route::post('etiqueta/findelinea', [\App\Http\Controllers\KanbansController::class, 'getQrDataEndOfLine']);
    Route::post('fallas_informadas', [\App\Http\Controllers\FallasInformadasController::class, 'filter']);
    Route::post('eol', [\App\Http\Controllers\FallasInformadasController::class, 'store']);
    Route::post('retrabajo', [\App\Http\Controllers\FallasInformadasController::class, 'cambiarEstadoRetrabajo']);
    Route::get('retrabajos/sin_etiqueta', [\App\Http\Controllers\FallasInformadasController::class, 'getFallasSinEtiqueta']);
    Route::get('retrabajos/pendientes/scrap', [\App\Http\Controllers\FallasInformadasController::class, 'getFallasScrapPendientes']);
    Route::post('retrabajos/cancelar_carga', [\App\Http\Controllers\FallasInformadasController::class, 'cancelarCargaOperarioRetrabajo']);


    Route::post('set_operador', [\App\Http\Controllers\FallasInformadasController::class, 'cambiarDatosRetrabajo']);
    Route::post('falla_interna_linea', [\App\Http\Controllers\FallasInformadasController::class, 'storeFallaInterna']);

    Route::post('kanban', [\App\Http\Controllers\KanbansController::class, 'store']);
    Route::get('kanban/reporte/{kanbanCode}', [\App\Http\Controllers\KanbansController::class, 'getReport']);

    Route::post('strap', [\App\Http\Controllers\ControlStrapController::class, 'store']);

    Route::get('pc/ejecutar_plan_auto', [\App\Http\Controllers\PlanCosturaController::class, 'ejecutarPlanAutomaticamente']);
    Route::get('pc/alta_fg', [\App\Http\Controllers\KanbansController::class, 'altaFinishGood']);
    Route::get('sync/etiquetas_back_fileserver', [\App\Http\Controllers\SyncController::class, 'syncEtiquetasBack']);
    Route::get('sync/elementos_funda', [\App\Http\Controllers\SyncController::class, 'syncElementosFunda']);

    Route::get('rrhh_areas', [\App\Http\Controllers\RrhhAusentismoController::class, 'getAreas']);
    Route::get('rrhh_detalles', [\App\Http\Controllers\RrhhAusentismoController::class, 'getDetalles']);
    Route::get('rrhh_causas', [\App\Http\Controllers\RrhhAusentismoController::class, 'getCausas']);
    Route::delete('rrhh_ausentismo/licencias/{ausentismoId}', [\App\Http\Controllers\RrhhAusentismoController::class, 'destroy']);
    Route::get('rrhh_ausentismo', [\App\Http\Controllers\RrhhAusentismoController::class, 'index']);
    Route::post('rrhh_ausentismo', [\App\Http\Controllers\RrhhAusentismoController::class, 'store']);
    Route::get('rrhh_ausentismo/generar', [\App\Http\Controllers\RrhhAusentismoController::class, 'generaAusentismoDia']);
    Route::get('rrhh_ausentismo/{id}', [\App\Http\Controllers\RrhhAusentismoController::class, 'show']);

    Route::get('rrhh_legajos', [\App\Http\Controllers\RrhhLegajosController::class, 'index']);
    Route::get('rrhh_legajos/{legajoId}', [\App\Http\Controllers\RrhhLegajosController::class, 'show']);
    Route::post('rrhh_legajos', [\App\Http\Controllers\RrhhLegajosController::class, 'store']);


    Route::post('import/qre', [\App\Http\Controllers\ImportController::class, 'importQre']);

    Route::post('import/legajos', [\App\Http\Controllers\ImportController::class, 'importLegajos']);
    Route::post('import/ausentismo', [\App\Http\Controllers\ImportController::class, 'importAusentismo']);

    Route::get('linea/flex/modelos', [\App\Http\Controllers\LineasController::class, 'getModelosLineaFlex']);
    Route::get('linea/{linea}/modelos', [\App\Http\Controllers\LineasController::class, 'getModelosLinea']);

    Route::post('piezas/posicion', [\App\Http\Controllers\PiezasController::class, 'setPosicionPiezas']);
    Route::put('partes/{parte}', [\App\Http\Controllers\PartesController::class, 'update']);
    Route::post('partes/{parte}/public-update', [\App\Http\Controllers\PartesController::class, 'update']);
    Route::post('partes/{parte}/imagen', [\App\Http\Controllers\PartesController::class, 'updateImagen']);
    Route::post('modelos/fallas/imagenes', [\App\Http\Controllers\ModeloFallaController::class, 'store']);
    Route::post('modelos/fallas/imagenes/public-store', [\App\Http\Controllers\ModeloFallaController::class, 'store']);
    Route::post('modelos/fallas/imagenes/{modeloFalla}/public-update', [\App\Http\Controllers\ModeloFallaController::class, 'update']);
    Route::get('materiales-piezas', [\App\Http\Controllers\MaterialesPiezasController::class, 'filter']);
    Route::get('materiales-piezas/{material}/ciclicos', [\App\Http\Controllers\MaterialesPiezasController::class, 'ciclicos']);
    Route::put('materiales-piezas/{material}', [\App\Http\Controllers\MaterialesPiezasController::class, 'update']);
    Route::get('materiales-piezas/{materialesPiezas}', [\App\Http\Controllers\MaterialesPiezasController::class, 'show']);

    Route::get('rollos', [\App\Http\Controllers\MaterialesPiezasController::class, 'index']);
    Route::post('kanban_material', [\App\Http\Controllers\RollosController::class, 'generarKanbanMaterial']);
    Route::post('kanban_material/search', [\App\Http\Controllers\RollosController::class, 'search']);
    Route::post('kanban_material/reimprimir', [\App\Http\Controllers\RollosController::class, 'reimprimirKanbanMaterial']);
    Route::post('averiguaInterfazProveedor', [\App\Http\Controllers\RollosController::class, 'averiguarInterfazProveedor']);


    Route::prefix('andon')->group(function () {
        Route::get('actualizar', [\App\Http\Controllers\AndonController::class, 'actualizarAndon']);
        Route::get('lineas', [\App\Http\Controllers\AndonController::class, 'obtenerAndonActualLinea']);

        Route::get('defectos/linea/{linea}', [\App\Http\Controllers\AndonController::class, 'armarAndonDefectosLineaHoraAHora']);
        Route::get('auditoria_hora_hora', [\App\Http\Controllers\AndonAuditoriaController::class, 'reporte']);
    });

    // Route::get('getMateriales', [\App\Http\Controllers\MaterialesController::class, 'index']);

    Route::get('scrap/materiales_piezas/{tipoMaterial?}', [\App\Http\Controllers\MaterialesPiezasController::class, 'getMaterialesPiezasPorTipo']);
    Route::put('scrap/materiales_piezas/{id}', [\App\Http\Controllers\ScrapController::class, 'updateMaterialPieza']);
    Route::get('scrap/sectores', [\App\Http\Controllers\ScrapController::class, 'sectores']);
    Route::post('scrap', [\App\Http\Controllers\ScrapController::class, 'store']);
    Route::post('scrap/filter', [\App\Http\Controllers\ScrapController::class, 'filter']);
    Route::delete('scrap/{scrap}', [\App\Http\Controllers\ScrapController::class, 'destroy']);
    Route::get('modelos/estructura', [\App\Http\Controllers\ModelosController::class, 'getModelosConPartesYPiezas']);
    Route::put('piezas/codigo/{codigo}/area', [App\Http\Controllers\PiezasController::class, 'updateAreaByCodigo']);
    Route::post('import/bom-m11/preview', [\App\Http\Controllers\ImportController::class, 'previewBomM11']);
    Route::post('import/bom-m11/confirm', [\App\Http\Controllers\ImportController::class, 'confirmBomM11']);
    Route::get('tipo_partes', [\App\Http\Controllers\TipoPartesController::class, 'index']);
    //Catalogos partes
    Route::get('lado_partes', [\App\Http\Controllers\LadoPartesController::class, 'index']);
    Route::get('vehiculos', [\App\Http\Controllers\VehiculosController::class, 'index']);
    // Route::post('scrap/funda', [\App\Http\Controllers\PiezasController::class, 'generarScrapFunda']);
    // Route::post('scrap/pendiente', [\App\Http\Controllers\PiezasController::class, 'generarScrapFunda']);
});

Route::group(['middleware' => ['cors', 'json.response']], function () {
    Route::get('wms_depositos', [App\Http\Controllers\DepositosController::class, 'index']);
    Route::get('wms_deposito/vaciar/{depositoId}', [\App\Http\Controllers\DepositosController::class, 'vaciarDeposito']);
    Route::get('wms_depositos/{depositoId}', [App\Http\Controllers\DepositosController::class, 'get']);
    Route::get('wms_deposito/posiciones/{depositoId}', [\App\Http\Controllers\DepositosController::class, 'getUbicaciones']);
    Route::get('wms_deposito/posicion/{posicionId}', [\App\Http\Controllers\DepositosController::class, 'getUbicacionById']);

    Route::get('wms_unidades', [\App\Http\Controllers\UnidadesController::class, 'index']);
    Route::post('wms_unidad', [\App\Http\Controllers\UnidadesController::class, 'store']);
    Route::delete('wms_unidad/{unidadId}', [\App\Http\Controllers\UnidadesController::class, 'destroy']);
    Route::get('wms_unidad/{unidadId}', [\App\Http\Controllers\UnidadesController::class, 'show']);
    Route::post('wms_unidad_posicion', [\App\Http\Controllers\DepositosController::class, 'guardarUnidadPosicion']);
    Route::post('wms_ubicacion/kanban', [App\Http\Controllers\UbicacionesController::class, 'consultarKanbanPosicionLibre']);
    Route::get('wms_ubicacion/libres/{depositoId?}', [App\Http\Controllers\UbicacionesController::class, 'listarPosicionesLibres']);
    Route::get('wms_ubicacion/posiciones_libres/{depositoId?}', [App\Http\Controllers\UbicacionesController::class, 'posicionesLibres']);
    Route::post('wms_ubicacion/cambiarEstado', [App\Http\Controllers\UbicacionesController::class, 'cambiarEstadoUbicacion']);

    Route::post('wms_unidad_posicion/unidad/deposito', [\App\Http\Controllers\DepositosController::class, 'setUnidadCapacidadDeposito']);

    Route::post('wms_almacenar', [\App\Http\Controllers\MovimientosController::class, 'store']);

    Route::get('wms_despachos/dia', [\App\Http\Controllers\DespachosController::class, 'consultaRunDia']);

    Route::get('wms_despachos/{id}/rechazados', [\App\Http\Controllers\DespachosController::class, 'consultaRechazados']);
    Route::get('wms_despachos/{id}/salida', [\App\Http\Controllers\DespachosController::class, 'informaSalidaCamion']);
    Route::post('wms_despachos/consulta', [\App\Http\Controllers\DespachosController::class, 'consultaPorModelo']);
    Route::post('wms_despachos', [\App\Http\Controllers\DespachosController::class, 'store']);
    Route::get('wms_modelos', [\App\Http\Controllers\ModelosController::class, 'index']);
    Route::get('wms_modelos/{modeloId}/partes', [\App\Http\Controllers\PartesController::class, 'getPartesByModel']);
    Route::get('wms_stock', [\App\Http\Controllers\MovimientosController::class, 'getStockKanbans']);

    Route::post('wms_despachos/verifica', [\App\Http\Controllers\DespachosController::class, 'verificaYPickea']);
    Route::post('wms_despachos/rechazo/reemplaza', [\App\Http\Controllers\DespachosController::class, 'intercambiaRechazado']);
    Route::post('wms_despachos/kanban/asignacion', [\App\Http\Controllers\DespachosController::class, 'asignarKanbanEnvio']);
    Route::post('wms_despacho/retornar_dolly', [\App\Http\Controllers\DespachosController::class, 'retornarADollys']);
    Route::delete('wms_despacho/item/{id}', [\App\Http\Controllers\DespachosController::class, 'destroyItem']);

    Route::get('wms_pc/despachos_historial', [\App\Http\Controllers\DespachosController::class, 'getHistory']);
    Route::get('wms_despacho/{despachoId}', [\App\Http\Controllers\DespachosController::class, 'show']);
    Route::post('wms_run', [\App\Http\Controllers\DespachosController::class, 'showRun']);

    Route::get('wms_cuarentena', [\App\Http\Controllers\DespachosController::class, 'getKanbansEnCuarentena']);
    Route::get('wms_cuarentena_materiales', [\App\Http\Controllers\MovimientosController::class, 'getMaterialesEnCuarentena']);
    Route::get('wms_materiales_a_retener', [\App\Http\Controllers\MovimientosController::class, 'getMaterialesARetenerQC']);
    Route::post('wms_materiales_a_retener', [\App\Http\Controllers\MovimientosController::class, 'agregarMaterialesARetenerQC']);
    Route::delete('wms_materiales_a_retener/{id}', [\App\Http\Controllers\MovimientosController::class, 'eliminaMaterialARetenerQC']);

    Route::get('wms_elemento/doortrim', [\App\Http\Controllers\DoorTrimController::class, 'index']);
    Route::get('wms_elemento/armrest', [\App\Http\Controllers\ArmRestController::class, 'index']);
    Route::get('wms_elemento/headrest', [\App\Http\Controllers\HeadRestController::class, 'index']);

    Route::post('wms_cuarentena/cambiarEstado', [\App\Http\Controllers\DespachosController::class, 'cambiarEstadoItemDespacho']);

    Route::get('wms_productos', [\App\Http\Controllers\ProductosController::class, 'index']);
    Route::get('wms_proveedores', [\App\Http\Controllers\ProveedoresController::class, 'index']);
    Route::post('wms_proveedores', [\App\Http\Controllers\ProveedoresController::class, 'store']);
    Route::get('wms_proveedores/interfaces', [\App\Http\Controllers\ProveedoresController::class, 'getInterfaces']);
    Route::get('wms_proveedores/interfaces/{interfaz}', [\App\Http\Controllers\ProveedoresController::class, 'getInterfazById']);
    Route::put('wms_proveedores/interfaces/{interfaz}', [\App\Http\Controllers\ProveedoresController::class, 'guardarInterfaz']);
    Route::get('wms_proveedores/{proveedor}', [\App\Http\Controllers\ProveedoresController::class, 'show']);
    Route::put('wms_proveedores/{proveedor}', [\App\Http\Controllers\ProveedoresController::class, 'update']);
    Route::get('wms_categorias', [\App\Http\Controllers\CategoriasController::class, 'index']);


    Route::post('wms_import/productos', [\App\Http\Controllers\ImportController::class, 'importProductos']);
    Route::post('wms_stock/reporte', [\App\Http\Controllers\MovimientosController::class, 'reporte']);
    Route::post('wms_stock/movimientos', [\App\Http\Controllers\MovimientosController::class, 'registrarMovimientoMateriales']);

    Route::post('wms_stock/transferencias_posicion', [\App\Http\Controllers\UbicacionesMovimientosController::class, 'transferenciaEntrePosiciones']);
    Route::post('wms_stock/mover_rack', [\App\Http\Controllers\UbicacionesMovimientosController::class, 'moverKanbanEntreRacks']);
    Route::post('wms_stock/rechazos', [\App\Http\Controllers\UbicacionesMovimientosController::class, 'moverRechazos']);
    Route::post('wms_stock/mover_cuarentena', [\App\Http\Controllers\MovimientosController::class, 'moverMaterialesEnCuarentena']);
    Route::post('wms_stock/liberar_material_rechazado', [\App\Http\Controllers\MovimientosController::class, 'liberarMaterialRechazado']);
    Route::get('wms_stock/andon_reporte_camion', [\App\Http\Controllers\MovimientosController::class, 'andonCamiones']);
    Route::get('wms_stock/kanban/{kanban}', [\App\Http\Controllers\MovimientosController::class, 'getInfoKanban']);

    // Route::post('wms_stock/rechazos', [\App\Http\Controllers\UbicacionesMovimientosController::class, 'moverRechazos']);

    Route::get('lineas', [\App\Http\Controllers\LineasController::class, 'index']);
    Route::get('plan_lineas', [\App\Http\Controllers\PlanLineaController::class, 'index']);
    Route::post('plan_lineas', [\App\Http\Controllers\PlanLineaController::class, 'store']);

    Route::post('fg_movimientos', [\App\Http\Controllers\KanbansController::class, 'fgMovimientos']);


    //RECEPCION
    Route::prefix('recepcion')->group(function () {

        Route::get('/', [\App\Http\Controllers\RecepcionController::class, 'index']);
        Route::post('/filtrar', [\App\Http\Controllers\RecepcionController::class, 'filtrar']);
        Route::delete('/{recepcion}', [\App\Http\Controllers\RecepcionController::class, 'destroy']);
        Route::get('/en_darsena', [\App\Http\Controllers\RecepcionController::class, 'getRecepcionActiva']);
        Route::get('codigo_operacion', [\App\Http\Controllers\RegistroIngresoRolloController::class, 'obtenerCodigoOperacion']);
        Route::post('verificar_codigo', [\App\Http\Controllers\InterfazProveedorController::class, 'verificarCodigo']);
        Route::post('ingreso', [\App\Http\Controllers\RegistroIngresoRolloController::class, 'store']);
        Route::post('/', [\App\Http\Controllers\RecepcionController::class, 'store']);
        Route::put('/{recepcion}', [\App\Http\Controllers\RecepcionController::class, 'update']);
        Route::post('/auto_upload_packing_list', [\App\Http\Controllers\RecepcionPackingListController::class, 'autoPackingList']);
        Route::post('/upload_packing_list', [\App\Http\Controllers\RecepcionPackingListController::class, 'subirPackingList']);
        Route::get('/{recepcion}', [\App\Http\Controllers\RecepcionController::class, 'edit']);
        // Route::get('/listar', [\App\Http\Controllers\AutoController::class, 'creaStockKanban']);
    });


    Route::get('partes/{parteId}/piezas', [\App\Http\Controllers\PiezasController::class, 'getPiezasByParte']);

    Route::post('plan_produccion', [\App\Http\Controllers\PlanProduccionController::class, 'store']);
    Route::get('plan_produccion', [\App\Http\Controllers\PlanProduccionController::class, 'index']);

    Route::post('inventarios', [\App\Http\Controllers\InventarioMaterialesPiezasController::class, 'getInventarioPorFecha']);

    Route::prefix('hora_hora')->group(function () {

        Route::post('/desdeExcel', [\App\Http\Controllers\HoraHoraProduccionController::class, 'desdeExcel']);

        Route::post('/soporte', [\App\Http\Controllers\HoraHoraProduccionController::class, 'setSoporteTurno']);
        Route::post('/search', [\App\Http\Controllers\HoraHoraProduccionController::class, 'getPlanHoraHora']);
        Route::post('/', [\App\Http\Controllers\HoraHoraProduccionController::class, 'setPlanHoraHora']);
        Route::post('/actualizar', [\App\Http\Controllers\HoraHoraProduccionController::class, 'actualizarPlanHoraHoraSegunAndon']);

        Route::post('/modelos/search', [\App\Http\Controllers\ModelosHoraHoraController::class, 'getModelosHoraHora']);
        Route::post('/modelos', [\App\Http\Controllers\ModelosHoraHoraController::class, 'store']);
        Route::post('/parada', [\App\Http\Controllers\ProduccionParadaController::class, 'store']);
        Route::post('/parada/search', [\App\Http\Controllers\ProduccionParadaController::class, 'search']);
        Route::delete('/parada/eliminar/{parada}', [\App\Http\Controllers\ProduccionParadaController::class, 'destroy']);
        // Route::post('/filtrar', [\App\Http\Controllers\RecepcionController::class, 'filtrar']);
    });
});

Route::group(['middleware' => ['auth:api', 'cors', 'json.response']], function () {

    //TEST
    //*********************************************************************************************************** */
    // Route::get('test', [\App\Http\Controllers\InventarioMaterialesPiezasController::class, 'index']);
    // Route::get('test2/{kanbanCode}', [\App\Http\Controllers\KanbansController::class, 'getHistory']);

    //*********************************************************************************************************** */

    //INICIO LECTRA
    Route::post('lectra/inicio', [\App\Http\Controllers\InicioLectraController::class, 'store']);
    Route::post('corte/actualiza_corte_ejecutado', [\App\Http\Controllers\LogPlanCosturaController::class, 'updateCorte']);

    //Users
    Route::get('users', [\App\Http\Controllers\UsersControllers::class, 'index']);
    Route::get('users/{id}', [\App\Http\Controllers\UsersControllers::class, 'get']);
    Route::post('user/update/data', [\App\Http\Controllers\UsersControllers::class, 'updateUserData']);
    Route::post('user', [\App\Http\Controllers\UsersControllers::class, 'store']);

    //Menu
    Route::get('menu', [\App\Http\Controllers\MenuController::class, 'index']);
    Route::get('menu/{userId}/user', [\App\Http\Controllers\MenuController::class, 'getMenuByUser']);

    Route::post('menu', [\App\Http\Controllers\MenuController::class, 'store']);


    //Tipo partes


    //File upload Image
    Route::post('/file/upload', [\App\Http\Controllers\FileController::class, 'uploadImage']);





    //Kanbans
    Route::get('kanban', [\App\Http\Controllers\KanbansController::class, 'index']);
    // Route::post('kanban/existencia/estado', [\App\Http\Controllers\KanbansController::class, 'verificaExistenciaCambioEstado']);
    Route::get('kanban/{kanban}/existencia', [\App\Http\Controllers\KanbansController::class, 'show']);
    Route::post('kanbanSar', [\App\Http\Controllers\KanbansController::class, 'storeInSar']);
    // Route::put('kanban', [\App\Http\Controllers\KanbansController::class, 'changeStatus']);
    Route::post('kanban/filter', [\App\Http\Controllers\KanbansController::class, 'filter']);

    //Modelos
    Route::get('door_trim', [\App\Http\Controllers\ModelosController::class, 'getDoorTrim']);

    Route::get('modelos', [\App\Http\Controllers\ModelosController::class, 'index']);
    Route::put('modelos/{modelo}', [\App\Http\Controllers\ModelosController::class, 'update']);
    Route::put('modelos', [\App\Http\Controllers\ModelosController::class, 'updateDatosMinPedido']);
    Route::put('modelos/{modelo}/lineas', [\App\Http\Controllers\ModelosController::class, 'updateLineas']);
    Route::get('modelos/{modelo}', [\App\Http\Controllers\ModelosController::class, 'show']);
    Route::get('modelos/{modelo}/archivos', [\App\Http\Controllers\ModelosController::class, 'getArchivos']);
    Route::get('modelos/{modelo}/fallas/imagenes', [\App\Http\Controllers\ModeloFallaController::class, 'getImagenesFallas']);
    Route::get('modelo/{modelo}/kanban_papa', [\App\Http\Controllers\ModeloKanbanPadreController::class, 'getKanbanPapa']);
    Route::delete('modelo/fallas/imagenes/{id}', [\App\Http\Controllers\ModeloFallaController::class, 'destroy']);
    Route::post('modelos/fallas/imagenes', [\App\Http\Controllers\ModeloFallaController::class, 'store']);

    //Partes
    Route::post('partes', [\App\Http\Controllers\PartesController::class, 'store']);
    Route::put('partes/{parte}', [\App\Http\Controllers\PartesController::class, 'update']);
    Route::get('modelos/{modeloId}/partes', [\App\Http\Controllers\PartesController::class, 'getPartesByModel']);



    //Depositos
    Route::get('depositos', [App\Http\Controllers\DepositosController::class, 'index']);

    //Piezas
    // Route::get('partes/{parteId}/piezas', [\App\Http\Controllers\PiezasController::class, 'getPiezasByParte']);
    Route::post('piezas', [App\Http\Controllers\PiezasController::class, 'store']);
    Route::get('piezas', [App\Http\Controllers\PiezasController::class, 'index']);
    Route::put('piezas/{pieza}', [App\Http\Controllers\PiezasController::class, 'update']);
    Route::get('piezas/{pieza}', [App\Http\Controllers\PiezasController::class, 'show']);
    Route::get('kanban/{kanbanCode}/piezas/{esEgreso}', [App\Http\Controllers\PiezasController::class, 'getPiezasBykanbanWithStockTienda']);

    //Import
    Route::post('import/piezas', [\App\Http\Controllers\ImportController::class, 'importPiezas']);
    Route::post('import/materialespiezas', [\App\Http\Controllers\ImportController::class, 'importMaterialesPiezas']);
    Route::post('import/dados', [\App\Http\Controllers\ImportController::class, 'importDados']);
    Route::post('import/materialesproveedores', [\App\Http\Controllers\ImportController::class, 'importMatPiezasProv']);
    Route::post('import/piezasupdate', [\App\Http\Controllers\ImportController::class, 'importPiezasUpdate']);
    Route::post('import/materialespiezasupdate', [\App\Http\Controllers\ImportController::class, 'importMaterialesPiezasUpdate']);
    Route::post('import/datoscorte', [\App\Http\Controllers\ImportController::class, 'importDatosCorte']);
    Route::post('import/tiendalayout', [\App\Http\Controllers\ImportController::class, 'importTiendaLayout']);
    Route::post('import/modelsupdate', [\App\Http\Controllers\ImportController::class, 'importUpdateModels']);
    Route::post('import/codigosfallas', [\App\Http\Controllers\ImportController::class, 'importCodigoFallas']);
    Route::post('import/codigosfallasmodelo', [\App\Http\Controllers\ImportController::class, 'importCodigoFallaModelo']);
    Route::post('import/updatekanbanpadre', [\App\Http\Controllers\ImportController::class, 'importActualizacionKanbanPadre']);
    Route::post('import/updatelectradata', [\App\Http\Controllers\ImportController::class, 'importActualizacionDataLectra']);
    Route::post('import/dadosab', [\App\Http\Controllers\ImportController::class, 'importDadosAB']);
    Route::post('import/modelosublinetiempos', [\App\Http\Controllers\ImportController::class, 'importModeloSublineTiempos']);
    Route::post('import/updatepiezasarea', [\App\Http\Controllers\ImportController::class, 'importUpdatePiezas']);
    Route::post('import/cuerosconcantidad', [\App\Http\Controllers\ImportController::class, 'importCuerosConCantidad']);




    // Route::post('import/bom', [\App\Http\Controllers\ImportController::class, 'importBom']);



    //Stock
    Route::post('stock/piezas', [\App\Http\Controllers\StockPiezasController::class, 'getStockPiezas']);
    Route::post('stock/piezasTienda', [\App\Http\Controllers\StockPiezasController::class, 'stockTiendaTodasPiezas']);


    //Tienda 
    Route::get('tienda/pedidos', [\App\Http\Controllers\TiendaController::class, 'getPedidosPendientes']);

    Route::get('tienda/egreso_pedido/{pedidoId}', [\App\Http\Controllers\TiendaController::class, 'egresoStockTiendaPedido']);
    Route::post('tienda/egreso', [\App\Http\Controllers\StockPiezasController::class, 'egresoStockTienda']);
    Route::post('tienda/ingreso', [\App\Http\Controllers\StockPiezasController::class, 'ingresoStockTienda']);
    Route::get('tienda/reposicion', [\App\Http\Controllers\StockPiezasController::class, 'getStockAReponerTienda']);
    Route::get('tienda/verificarPiezasReemplazoTienda', [\App\Http\StockPiezasLib::class, 'verificarPiezasReemplazoTienda']);
    Route::get('tienda/etiquetas', [App\Http\Controllers\TiendaController::class, 'getEtiquetas']);
    Route::post('tienda/pedido/finalizar', [\App\Http\Controllers\TiendaController::class, 'finalizarPedido']);
    Route::get('tienda/pedido/{kanbanCode}', [\App\Http\Controllers\TiendaController::class, 'getPedidoByKanban']);


    //Dados
    Route::get('dados', [\App\Http\Controllers\DadosController::class, 'index']);

    //Capacidad de producción
    Route::post('capacidadProduccion', [\App\Http\Controllers\StockPiezasController::class, 'capacidadProduccion']);
    Route::get('capacidadProduccionDepositos', [\App\Http\Controllers\StockPiezasController::class, 'capacidadProduccionDepositos']);

    //Lineas

    Route::post('lineas', [\App\Http\Controllers\LineasController::class, 'store']);
    // Route::get('lineas/ocupacion', [\App\Http\Controllers\LineasController::class, 'lineasOcupacion']);

    //Tablas
    Route::get('tabla/{tableName}', [\App\Http\Controllers\TableController::class, 'getTable']);
    Route::post('tabla', [\App\Http\Controllers\TableController::class, 'storeUpdateTable']);
    Route::post('tabla/delete', [\App\Http\Controllers\TableController::class, 'deleteTable']);

    Route::get('colores', [\App\Http\Controllers\ColoresController::class, 'index']);
    Route::get('materiales', [\App\Http\Controllers\MaterialesController::class, 'index']);
    Route::get('filas', [\App\Http\Controllers\FilasController::class, 'index']);

    //Materiales Piezas
    Route::get('materiales_piezas/{tipoMaterial?}', [\App\Http\Controllers\MaterialesPiezasController::class, 'getMaterialesPiezasPorTipo']);

    //Inventario Materiales Piezas
    Route::get('inventario_materiales_piezas/confirmaPesaje/{id}', [\App\Http\Controllers\InventarioMaterialesPiezasController::class, 'confirmaPesaje']);
    Route::get('inventario_materiales_piezas/{tipo}', [\App\Http\Controllers\InventarioMaterialesPiezasController::class, 'getCurrentInventarioByUser']);
    Route::delete('inventario_materiales_piezas/{inventarioMaterialesPiezas}', [\App\Http\Controllers\InventarioMaterialesPiezasController::class, 'destroy']);
    Route::post('inventario_materiales_piezas', [\App\Http\Controllers\InventarioMaterialesPiezasController::class, 'store']);
    // Route::post('inventarios', [\App\Http\Controllers\InventarioMaterialesPiezasController::class, 'getInventarioPorFecha']);
    Route::post('inventarios/material', [\App\Http\Controllers\InventarioMaterialesPiezasController::class, 'getByMaterial']);
    Route::put('inventarios/editarpesaje/{id}', [\App\Http\Controllers\InventarioMaterialesPiezasController::class, 'editarPesaje']);
    Route::put('inventarios/materiales/resultado/{materialId}', [\App\Http\Controllers\InventarioMaterialesPiezasController::class, 'actualizarInventarioMaterialesResultado']);


    //Logistica
    Route::get('logistica/stock/modificacion/{kanban}', [\App\Http\Controllers\StockProductoFinalController::class, 'show']);
    Route::get('logistica/stock/{modelo?}', [\App\Http\Controllers\StockProductoFinalController::class, 'index']);
    Route::post('logistica/stock', [\App\Http\Controllers\StockProductoFinalController::class, 'store']);
    Route::put('logistica/stock', [\App\Http\Controllers\StockProductoFinalController::class, 'update']);
    Route::post('logistica/stock/filters', [\App\Http\Controllers\StockProductoFinalController::class, 'filter']);

    Route::get('calidad/cuarentena', [\App\Http\Controllers\StockProductoFinalController::class, 'getCuarentena']);
    Route::put('calidad/cuarentena/{kanban}', [\App\Http\Controllers\StockProductoFinalController::class, 'cambiarEstadoPorCalidad']);

    //Corte
    Route::get('modelo/kanban_papa/{codigoKanban}/{lectra}', [\App\Http\Controllers\ModeloKanbanPadreController::class, 'getDataPadreFromKanban']);
    Route::get('kanban_papa/dado/{dadoId}', [\App\Http\Controllers\ModeloKanbanPadreController::class, 'getDado']);
    Route::post('kanban_papa/dado', [\App\Http\Controllers\ModeloKanbanPadreController::class, 'store']);
    Route::delete('kanban_papa/dado/{dadoId}', [\App\Http\Controllers\ModeloKanbanPadreController::class, 'destroy']);
    Route::get('corte/pendientes', [\App\Http\Controllers\LectraController::class, 'getKanbansPendienteCorte']);
    Route::post('corte/plan', [\App\Http\Controllers\LectraController::class, 'setPlanCorte']);
    Route::post('corte/plan/existe', [\App\Http\Controllers\LectraController::class, 'existePlanDeCorte']);
    Route::post('corte/plan/intercambia', [\App\Http\Controllers\LectraController::class, 'intercambiaPlan']);
    Route::post('corte/quitar_modelo', [\App\Http\Controllers\LectraController::class, 'quitarModeloPendiente']);
    Route::post('corte/actualiza_estado_dado', [\App\Http\Controllers\LectraController::class, 'actualizarEstadoDado']);
    // Route::get('corte/planificaciones', [\App\Http\Controllers\LectraController::class, 'getPlanificaciones']);
    // Route::post('corte/inicio_dado', [\App\Http\Controllers\LectraController::class, 'iniciarDado']);


    //Pendiente impresionendie
    Route::post('pc/quitar_pendientes', [\App\Http\Controllers\PcPendienteImpresionController::class, 'quitarPendientes']);
    Route::post('pc/guardar_plan', [\App\Http\Controllers\PcPlanProduccionController::class, 'store']);
    Route::get('pc/plan_actual', [\App\Http\Controllers\PcPlanProduccionController::class, 'getCurrentPlan']);
    Route::get('pc/stock_modelos', [\App\Http\Controllers\PcPlanProduccionController::class, 'getEstadoModelosStock']);
    Route::post('pc/planificar', [\App\Http\Controllers\PcPendienteImpresionController::class, 'setPlanificacion']);
    Route::get('pc/situacion_modelos', [\App\Http\Controllers\PcPendienteImpresionController::class, 'getSituacionModelos']);

    Route::get('pc/ejecutar_plan', [\App\Http\Controllers\PlanCosturaController::class, 'armarProximosCortes']);
    Route::post('pc/ejecutar_plan', [\App\Http\Controllers\PlanCosturaController::class, 'armarProximosCortesOrden']);
    Route::get('pc/kanbans_pendientes', [\App\Http\Controllers\PlanCosturaController::class, 'getKanbansPlanificados']);



    Route::get('despacho/{despachoId}', [\App\Http\Controllers\DespachosController::class, 'show']);
    Route::get('pc/despachos_historial', [\App\Http\Controllers\DespachosController::class, 'getHistory']);

    //Strap
    Route::get('strap', [\App\Http\Controllers\ControlStrapController::class, 'index']);
    Route::delete('strap/{id}/{userId}', [\App\Http\Controllers\ControlStrapController::class, 'anularStrap']);
    Route::put('strap/{id}', [\App\Http\Controllers\ControlStrapController::class, 'update']);
    Route::post('strap/filter', [\App\Http\Controllers\ControlStrapController::class, 'filter']);
    Route::post('strap/filterMovimientos', [\App\Http\Controllers\ControlStrapController::class, 'filterMovimientos']);
    Route::get('strap/kanban/{kanbanCode}', [\App\Http\Controllers\ControlStrapController::class, 'verificaKanbanRemoto']);
    Route::get('strap/planilla/{partNumber}', [\App\Http\Controllers\ControlStrapController::class, 'verificaPlanillaReposicionStrap']);
    Route::get('strap/modelo/{modelo}', [\App\Http\Controllers\ControlStrapController::class, 'verificaModeloStrap']);

    Route::post('strap/verificar_modelo', [\App\Http\Controllers\ControlStrapController::class, 'verificaKanbanModeloRemoto']);
    Route::post('strap/valida_evento', [\App\Http\Controllers\ControlStrapController::class, 'validaEventoStrap']);
    Route::get('strap/parts/{modelo}', [\App\Http\Controllers\StrapPartNumberController::class, 'index']);

    Route::get('strap/part_number/{part}', [\App\Http\Controllers\StrapPartNumberController::class, 'verificaPartNumber']);
    Route::get('strap/evento_pendiente', [\App\Http\Controllers\StrapPartNumberController::class, 'verificarEventoPendiente']);

    Route::post('strap/verificar_cantidad_reposicion', [\App\Http\Controllers\ControlStrapController::class, 'verificarCantidadReposicion']);
    Route::post('strap/validar_usuario', [\App\Http\Controllers\ControlStrapController::class, 'validarUsuarioStrap']);
    Route::post('strap/user_valido', [\App\Http\Controllers\ControlStrapController::class, 'userValidoStrapByCodAutorizacion']);

    Route::get('config/vaciar_buffer/{linea}', [\App\Http\Controllers\ConfigController::class, 'vaciarBuffer']);
    Route::get('config/getBuffer/{linea}', [\App\Http\Controllers\ConfigController::class, 'getBuffer']);
    Route::get('config/quitar_buffer/{kanban}', [\App\Http\Controllers\ConfigController::class, 'quitarKanbanDeBuffer']);

    Route::get('deposito/posiciones/{depositoId}', [\App\Http\Controllers\DepositosController::class, 'getUbicaciones']);
    Route::post('deposito/transferencias', [\App\Http\Controllers\UbicacionesMovimientosController::class, 'transferencias']);

    Route::get('open_issues', [\App\Http\Controllers\OpenIssuesController::class, 'index']);
    Route::post('open_issues', [\App\Http\Controllers\OpenIssuesController::class, 'store']);
    Route::delete('open_issues/{openIssues}', [\App\Http\Controllers\OpenIssuesController::class, 'destroy']);
});
