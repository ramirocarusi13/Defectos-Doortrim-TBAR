<?php

namespace App\Http\Controllers;

use App\Models\OpenIssues;
use App\Models\OpenIssuesImages;
use Illuminate\Http\Request;

class OpenIssuesController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $userId = auth()->guard('api')->user()->id;

        $data = OpenIssues::with('imagenes')->where('user_id', $userId)->get();

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


        OpenIssues::create([
            'titulo'        => $request->titulo,
            'user_id'       => auth()->guard('api')->user()->id,
            'descripcion'   => $request->descripcion,
            'abierto'       => true
        ]);


        return $this->setResponse([]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\OpenIssues  $openIssues
     * @return \Illuminate\Http\Response
     */
    public function show(OpenIssues $openIssues) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\OpenIssues  $openIssues
     * @return \Illuminate\Http\Response
     */
    public function edit(OpenIssues $openIssues) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\OpenIssues  $openIssues
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, OpenIssues $openIssues) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\OpenIssues  $openIssues
     * @return \Illuminate\Http\Response
     */
    public function destroy(OpenIssues $openIssues) {

        try {
            OpenIssuesImages::where('issue_id', $openIssues->id)->delete();
            $openIssues->delete();
        } catch (\Throwable $th) {
            //throw $th;
            return $this->setResponse([], 'Ocurrió un error al eliminar', true);
        }

        return $this->setResponse([]);
    }
}
