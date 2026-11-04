<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('defectos_cobot', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('nro_cobot');
            $table->string('nombre');
            $table->integer('numero_bit');
            $table->string('dispositivo_plc');
            $table->text('descripcion')->nullable();
            $table->string('nro_error');
            $table->unsignedBigInteger('falla_id');
            $table->timestamps();

            $table->foreign('falla_id')->references('id')->on('codigo_fallas');
        });
    }

    public function down() {
        Schema::dropIfExists('defectos_cobot');
    }
};
