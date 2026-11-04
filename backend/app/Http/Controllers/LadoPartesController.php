<?php

namespace App\Http\Controllers;

use App\Models\LadoPartes;
use Illuminate\Http\Request;

class LadoPartesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = LadoPartes::get();

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
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\LadoPartes  $ladoPartes
     * @return \Illuminate\Http\Response
     */
    public function show(LadoPartes $ladoPartes)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\LadoPartes  $ladoPartes
     * @return \Illuminate\Http\Response
     */
    public function edit(LadoPartes $ladoPartes)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LadoPartes  $ladoPartes
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, LadoPartes $ladoPartes)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\LadoPartes  $ladoPartes
     * @return \Illuminate\Http\Response
     */
    public function destroy(LadoPartes $ladoPartes)
    {
        //
    }
}
