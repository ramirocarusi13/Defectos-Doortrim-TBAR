<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FileController extends Controller {

    public function uploadImage(Request $request) {
        Validator::make($request->all(), [
            'image' => ['required'],
        ])->validate();

        // $update = isset($request->update) ? $request->update : "0";

        $image_name = time() . '.' . $request->image->extension();
        $request->image->move(public_path('uploads'), $image_name);

        return $this->setResponse(['image' => $image_name]);
    }
}
