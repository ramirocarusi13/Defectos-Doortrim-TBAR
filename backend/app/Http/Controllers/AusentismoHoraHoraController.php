<?php

namespace App\Http\Controllers;

use App\Models\AusentismoHoraHora;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AusentismoHoraHoraController extends Controller {
    public function getUsersAusentismoHoraHora(Request $request) {

        $usuarios = UserService::getUsersAusentismoHoraHora($request->fecha, $request->turno);
        return $this->setResponse($usuarios);
    }

    public function store(Request $request) {
        $fecha = $request->fecha;
        $turno = $request->turno;
        $datos = $request->data;
        $usersLineas = $request->userLineas;

        foreach ($datos as $user) {
            AusentismoHoraHora::updateOrCreate(
                [
                    'user_id'       => intval($user['id']),
                    'turno'         => $turno,
                    'fecha'         => $fecha
                ],
                [
                    'user_id'       => intval($user['id']),
                    'turno'         => $turno,
                    'fecha'         => $fecha,
                    'linea_id'      => intval($user['linea_id']),
                    'estado'        => $user['estado'],
                    'comentario'    => $user['comentario'],
                ]
            );
        }

        foreach ($usersLineas as $user) {
            AusentismoHoraHora::updateOrCreate(
                [
                    'user_id'       => intval($user['id']),
                    'turno'         => $turno,
                    'fecha'         => $fecha
                ],
                [
                    'user_id'       => intval($user['id']),
                    'turno'         => $turno,
                    'fecha'         => $fecha,
                    'linea_id'      => intval($user['linea_id']),
                    'estado'        => $user['estado'],
                    'comentario'    => $user['comentario'],
                ]
            );
        }

        return $this->setResponse([]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\AusentismoHoraHora  $ausentismoHoraHora
     * @return \Illuminate\Http\Response
     */
    public function show(AusentismoHoraHora $ausentismoHoraHora) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\AusentismoHoraHora  $ausentismoHoraHora
     * @return \Illuminate\Http\Response
     */
    public function edit(AusentismoHoraHora $ausentismoHoraHora) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AusentismoHoraHora  $ausentismoHoraHora
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AusentismoHoraHora $ausentismoHoraHora) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AusentismoHoraHora  $ausentismoHoraHora
     * @return \Illuminate\Http\Response
     */
    public function destroy(AusentismoHoraHora $ausentismoHoraHora) {
        //
    }
}
