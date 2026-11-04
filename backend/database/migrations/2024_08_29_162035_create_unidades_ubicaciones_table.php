<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('wms_unidades_ubicaciones', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ubicacion_id')->nullable();
            $table->foreign('ubicacion_id')->references('id')->on('ubicaciones');

            $table->unsignedBigInteger('unidad_id')->nullable();
            $table->foreign('unidad_id')->references('id')->on('wms_unidades');

            $table->integer('capacidad')->default(1);

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('wms_unidades_ubicaciones');
    }
};
