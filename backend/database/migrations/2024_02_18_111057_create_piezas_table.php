<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('piezas', function (Blueprint $table) {
            $table->id();

            $table->string("codigo");

            $table->unsignedBigInteger('parte_id')->nullable();
            $table->foreign('parte_id')->references('id')->on('partes');

            $table->unsignedBigInteger('material_id')->nullable();
            $table->foreign('material_id')->references('id')->on('materiales');

            $table->unsignedBigInteger('material_pieza_id')->nullable();
            $table->foreign('material_pieza_id')->references('id')->on('materiales_piezas');

            $table->unsignedBigInteger('color_id')->nullable();
            $table->foreign('color_id')->references('id')->on('colores');

            $table->string("dado")->nullable();

            $table->integer("minimo")->nullable();
            $table->integer("maximo")->nullable();
            $table->integer("pto_optimo")->nullable();

            $table->string("kanban_reposicion")->nullable();

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('piezas');
    }
};
