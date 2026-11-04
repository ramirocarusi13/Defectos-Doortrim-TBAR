<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('materiales_aprobacion_calidads', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('material_id')->nullable();
            $table->foreign('material_id')->references('id')->on('materiales_piezas');

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('materiales_aprobacion_calidads');
    }
};
