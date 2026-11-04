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
        Schema::create('rrhh_legajos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->nullable();
            $table->string('turno')->nullable();

            $table->unsignedBigInteger('area_id')->nullable();
            $table->foreign('area_id')->references('id')->on('rrhh_areas');

            $table->boolean('activo')->default(true)->nullable();
            $table->string('motivo_baja')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('rrhh_legajos');
    }
};
