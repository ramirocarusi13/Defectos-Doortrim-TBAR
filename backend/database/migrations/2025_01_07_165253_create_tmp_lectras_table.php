<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('tmp_lectras', function (Blueprint $table) {
            $table->id();
            $table->string('dado')->nullable();
            $table->integer('lectra')->nullable();
            $table->string('group', 5)->nullable();
            $table->string('modelo', 10)->nullable();
            $table->dateTime('inicio')->nullable();
            $table->string('horaInicio', 5)->nullable();
            $table->string('horaFin', 5)->nullable();
            $table->dateTime('fin')->nullable();
            $table->integer('demora')->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('tmp_lectras');
    }
};
