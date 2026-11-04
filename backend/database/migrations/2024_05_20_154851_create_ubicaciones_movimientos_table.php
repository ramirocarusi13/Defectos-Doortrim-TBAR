<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('ubicaciones_movimientos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ubicacion_id')->nullable();
            $table->foreign('ubicacion_id')->references('id')->on('ubicaciones');

            $table->dateTime('ingreso')->nullable();
            $table->dateTime('egreso')->nullable();

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('ubicaciones_movimientos');
    }
};
