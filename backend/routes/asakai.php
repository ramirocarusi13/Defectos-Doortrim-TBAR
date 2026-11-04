<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::prefix('asakai')->group(function () {

    Route::get('test', function () {
        Log::alert("PAS");
        return "OK";
    });

    Route::post('fallas/fmds', [App\Http\Controllers\asakai\AsakaiController::class, 'getFmds']);

    // Route::get('print-jobs', [\App\Http\Controllers\FilePrintController::class, 'getFilesToPrint']);
    // Route::post('print-jobs/mark-printed', [\App\Http\Controllers\FilePrintController::class, 'markAsPrinted']);
});
