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
        Schema::create('modelo_fallas', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->foreign('modelo_id')->references('id')->on('modelos');

            // $table->unsignedBigInteger('falla_id')->nullable();
            // $table->foreign('falla_id')->references('id')->on('codigo_fallas');

            $table->string('imagen')->nullable();

            $table->unsignedBigInteger('tipo_id')->nullable();
            $table->foreign('tipo_id')->references('id')->on('tipo_partes');

            $table->unsignedBigInteger('lado_id')->nullable();
            $table->foreign('lado_id')->references('id')->on('lado_partes');

            $table->string('nombre')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('modelo_fallas');
    }
};
