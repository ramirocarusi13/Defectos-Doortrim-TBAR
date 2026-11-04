<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController {
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function setResponse(array $data = [],  string $message = '', $error = false, int $statusCode = 200) {
        return response([
            'data'      => $data,
            'error'     => $error,
            'message'   => $message
        ], $error ? 404 : $statusCode);
    }
}
