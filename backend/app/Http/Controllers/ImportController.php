<?php

namespace App\Http\Controllers;

use App\Imports\ActualizacionDataLectra;
use App\Imports\ActualizacionKanbanPadreImportClass;
use App\Imports\ActualizacionPiezaMaterialClass;
use App\Imports\ActualizarPiezasImportClass;
use App\Imports\AusentismoImportClass;
use App\Imports\BomImportClass;
use App\Imports\CodigoFallaModeloImportClass;
use App\Imports\CodigoFallasImportClass;
use App\Imports\CuerosConCantidadImportClass;
use App\Imports\DadosABImportClass;
use App\Imports\DadosImportClass;
use App\Imports\DatosCorteImportClass;
use App\Imports\LegajosImportClass;
use App\Imports\MaterialesPiezasImportClass;
use App\Imports\MaterialesPiezasUpdateImportClass;
use App\Imports\ModeloSublineTiempoImportClass;
use App\Imports\PiezasImportClass;
use App\Imports\PiezasUpdateImportClass;
use App\Imports\ProductsImportClass;
use App\Imports\TiendaLayoutImportClass;
use App\Imports\UpdateModelsImportClass;
use App\Models\Colores;
use App\Models\Modelos;
use App\Models\ModeloSublineTiempo;
use App\Models\Partes;
use App\Services\BomM11ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;


class ImportController extends Controller {

    public function previewBomM11(Request $request, BomM11ImportService $service) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $data = $service->preview($request->file('file'));

        return $this->setResponse($data, 'Previsualizacion generada');
    }

    public function confirmBomM11(Request $request, BomM11ImportService $service) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $data = $service->confirm($request->file('file'));
        $hasErrors = !$data['can_import'];

        return $this->setResponse($data, $hasErrors ? 'Hay errores pendientes. No se actualizo la base.' : 'Importacion finalizada', $hasErrors, $hasErrors ? 422 : 200);
    }

    public function importPiezas(Request $request) {

        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new PiezasImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importCuerosConCantidad(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new CuerosConCantidadImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importMaterialesPiezas(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        set_time_limit(120);

        $file = $request->file('file');

        Excel::import(new MaterialesPiezasImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importDados(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new DadosImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importMatPiezasProv(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new MaterialesPiezasUpdateImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importPiezasUpdate(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new PiezasUpdateImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importMaterialesPiezasUpdate(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new ActualizacionPiezaMaterialClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importDatosCorte(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new DatosCorteImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importUpdateModels(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new UpdateModelsImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importCodigoFallas(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new CodigoFallasImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importCodigoFallaModelo(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new CodigoFallaModeloImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importTiendaLayout(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new TiendaLayoutImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importActualizacionKanbanPadre(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new ActualizacionKanbanPadreImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importActualizacionDataLectra(Request $request) {
        // Log::alert("PASO IMPORT");
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new ActualizacionDataLectra, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importDadosAB(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new DadosABImportClass, $file);

        return $this->setResponse([], 'Ok');
    }

    public function importModeloSublineTiempos(Request $request) {

        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new ModeloSublineTiempoImportClass(), $file);

        return $this->setResponse([], 'Ok');
    }

    public function importProductos(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new ProductsImportClass(), $file);

        return $this->setResponse([], 'Ok');
    }

    public function importLegajos(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new LegajosImportClass(), $file);

        return $this->setResponse([], 'Ok');
    }

    public function importAusentismo(Request $request) {

        // Log::alert("PASO");

        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        // Log::alert("PASO1");


        $file = $request->file('file');

        Excel::import(new AusentismoImportClass(), $file);

        return $this->setResponse([], 'Ok');
    }

    public function importUpdatePiezas(Request $request) {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        Excel::import(new ActualizarPiezasImportClass(), $file);

        return $this->setResponse([], 'Ok');
    }

    // public function importBom(Request $request) {
    //     $request->validate([
    //         'file' => 'required|mimes:xlsx,xls',
    //     ]);

    //     ini_set('memory_limit', '-1');
    //     set_time_limit(360);

    //     $file = $request->file('file');

    //     Modelos::where('activo', true)->update(['activo' => false]);
    //     Partes::where('activo', true)->update(['activo' => false]);

    //     $path = Storage::putFile('bom', $file);

    //     Excel::import(new BomImportClass, $path);

    //     return $this->setResponse([], 'Ok');
    // }
}
