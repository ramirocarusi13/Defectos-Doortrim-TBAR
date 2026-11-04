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
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string("codigo");
            $table->string("decripcion")->nullable();

            $table->unsignedBigInteger('tipo_vehiculo_id')->nullable();
            $table->foreign('tipo_vehiculo_id')->references('id')->on('tipo_vehiculos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('vehiculos');
    }
};
