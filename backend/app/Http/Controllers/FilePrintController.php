<?php

namespace App\Http\Controllers;

use App\Models\FilePrint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FilePrintController extends Controller {

    public function getFilesToPrint(Request $request) {
        $files = FilePrint::where('printed', false)->get()->toArray();
        // Log::alert($files);
        return $this->setResponse($files);
    }

    public function markAsPrinted(Request $request) {
        // Log::alert($request);
        $fileIds = $request->id;

        FilePrint::where('id', $fileIds)
            ->update([
                'printed'   => true,
                'printed_at' => date('Y-m-d H:i:s'),
            ]);

        return $this->setResponse([]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
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
     * @param  \App\Models\FilePrint  $filePrint
     * @return \Illuminate\Http\Response
     */
    public function show(FilePrint $filePrint) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\FilePrint  $filePrint
     * @return \Illuminate\Http\Response
     */
    public function edit(FilePrint $filePrint) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\FilePrint  $filePrint
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, FilePrint $filePrint) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\FilePrint  $filePrint
     * @return \Illuminate\Http\Response
     */
    public function destroy(FilePrint $filePrint) {
        //
    }
}
