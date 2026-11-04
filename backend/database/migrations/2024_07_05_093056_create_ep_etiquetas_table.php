<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('ep_etiquetas', function (Blueprint $table) {
            $table->id();

            $table->string('kanban')->nullable();
            $table->integer('id_etiqueta')->nullable();
            $table->string('modelo')->nullable();
            $table->string('lado')->nullable();
            $table->string('secuencia')->nullable();
            $table->string('estado')->nullable();
            $table->string('qr')->nullable();
            $table->string('ubicacion')->nullable();
            $table->string('tipo')->nullable();
            $table->string('codigo')->nullable();
            $table->string('vehiculo')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->unsignedBigInteger('user_id_reimpresion')->nullable();
            $table->foreign('user_id_reimpresion')->references('id')->on('users');

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('ep_etiquetas');
    }
};
