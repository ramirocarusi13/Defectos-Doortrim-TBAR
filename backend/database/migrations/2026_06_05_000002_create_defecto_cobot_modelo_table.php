<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('defecto_cobot_modelo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('defecto_cobot_id');
            $table->unsignedBigInteger('modelo_id');
            $table->timestamps();

            $table->foreign('defecto_cobot_id')->references('id')->on('defectos_cobot')->onDelete('cascade');
            $table->foreign('modelo_id')->references('id')->on('modelos')->onDelete('cascade');
            $table->unique(['defecto_cobot_id', 'modelo_id']);
        });
    }

    public function down() {
        Schema::dropIfExists('defecto_cobot_modelo');
    }
};
