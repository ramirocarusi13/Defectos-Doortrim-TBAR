<?php

namespace App\Http\Controllers;

use App\Models\Dados;
use Illuminate\Http\Request;

class DadosController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $data = Dados::with(['modelo'])->get();

        return $this->setResponse($data->toArray());
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
     * @param  \App\Models\Dados  $dados
     * @return \Illuminate\Http\Response
     */
    public function show(Dados $dados) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Dados  $dados
     * @return \Illuminate\Http\Response
     */
    public function edit(Dados $dados) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Dados  $dados
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Dados $dados) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Dados  $dados
     * @return \Illuminate\Http\Response
     */
    public function destroy(Dados $dados) {
        //
    }
}
