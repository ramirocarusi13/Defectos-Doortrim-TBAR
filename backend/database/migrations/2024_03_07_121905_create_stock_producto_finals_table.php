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
        Schema::create('stock_producto_finals', function (Blueprint $table) {
            $table->id();

            $table->string("codigo_kanban")->nullable();
            $table->string("modelo")->nullable();
            $table->string("mes")->nullable();
            $table->date("fecha")->nullable();

            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->foreign('modelo_id')->references('id')->on('modelos');

            $table->unsignedBigInteger('kanban_id')->nullable();
            $table->foreign('kanban_id')->references('id')->on('kanbans');

            $table->boolean("egresado")->default(false);
            $table->boolean("cuarentena")->default(false);
            $table->date("fecha_egreso")->nullable();

            $table->integer("run")->nullable();
            $table->date("fecha_calidad")->nullable();
            $table->boolean("rechazado")->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('stock_producto_finals');
    }
};
