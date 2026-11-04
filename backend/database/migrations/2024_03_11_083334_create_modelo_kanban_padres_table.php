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
        Schema::create('modelo_kanban_padres', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->foreign('modelo_id')->references('id')->on('modelos');

            $table->float('consumo')->default(1)->nullable();
            $table->integer('corte')->default(1)->nullable();
            $table->string("dado")->nullable();

            $table->unsignedBigInteger('material_id')->nullable();
            $table->foreign('material_id')->references('id')->on('materiales_piezas');

            $table->dateTime('fecha_actualizacion')->nullable();
            $table->dateTime('fecha_actualizacion2')->nullable();

            $table->boolean('otro')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('modelo_kanban_padres');
    }
};
