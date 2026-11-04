<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('kanbans_reemplazos', function (Blueprint $table) {
            $table->id();

            // $table->string("codigo");

            $table->unsignedBigInteger('kanban_id')->nullable();
            $table->foreign('kanban_id')->references('id')->on('kanbans');

            $table->unsignedBigInteger('pieza_id')->nullable();
            $table->foreign('pieza_id')->references('id')->on('piezas');

            // $table->boolean('finalizado')->default(false);

            $table->integer("capas")->default(1);

            $table->dateTime("fecha_impresion")->nullable();
            $table->string("impresora")->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('kanbans_reemplazos');
    }
};
