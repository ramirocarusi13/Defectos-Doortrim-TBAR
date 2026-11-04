<?php

namespace App\Http\Controllers;

use App\Models\UserPolivalencias;
use Illuminate\Http\Request;

class UserPolivalenciasController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }


    public function get($userId) {

        $data = UserPolivalencias::where('user_id', $userId)->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function store(Request $request) {

        $userId = $request->userId;
        $operacion = $request->operacion;
        $polivalencia = $request->polivalencia;

        $existe = UserPolivalencias::where('user_id', $userId)->where('operacion_id', $operacion)->first();

        if ($existe) {
            UserPolivalencias::where('id', $existe->id)->update(['polivalencia' => $polivalencia]);
        } else {
            UserPolivalencias::create([
                'user_id'       => $userId,
                'operacion_id'  => $operacion,
                'polivalencia'  => $polivalencia
            ]);
        }

        return $this->setResponse([]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\UserPolivalencias  $userPolivalencias
     * @return \Illuminate\Http\Response
     */
    public function show(UserPolivalencias $userPolivalencias) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\UserPolivalencias  $userPolivalencias
     * @return \Illuminate\Http\Response
     */
    public function edit(UserPolivalencias $userPolivalencias) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\UserPolivalencias  $userPolivalencias
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, UserPolivalencias $userPolivalencias) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\UserPolivalencias  $userPolivalencias
     * @return \Illuminate\Http\Response
     */
    public function destroy(UserPolivalencias $userPolivalencias) {
        //
    }
}
