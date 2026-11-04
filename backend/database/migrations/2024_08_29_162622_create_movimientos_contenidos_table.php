<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('wms_movimientos_contenidos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('movimiento_id')->nullable();
            $table->foreign('movimiento_id')->references('id')->on('wms_movimientos');

            $table->string('ref')->nullable();
            $table->integer('cantidad')->default(0);

            $table->unsignedBigInteger('unidad_id')->nullable();
            $table->foreign('unidad_id')->references('id')->on('wms_unidades');

            $table->unsignedBigInteger('ubicacion_id')->nullable();
            $table->foreign('ubicacion_id')->references('id')->on('ubicaciones');

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('wms_movimientos_contenidos');
    }
};
