<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('ubicacion_contenidos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('movimiento_id')->nullable();
            $table->foreign('movimiento_id')->references('id')->on('ubicaciones_movimientos');

            $table->string('detalle')->nullable();
            $table->string('contenido')->nullable();


            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('ubicacion_contenidos');
    }
};
