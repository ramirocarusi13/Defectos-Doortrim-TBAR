<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('ep_etiquetas_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('kanban')->nullable();
            $table->string('qr_etiqueta')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->longText('evento')->nullable();
            $table->string("info_adi")->nullable();

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('ep_etiquetas_eventos');
    }
};
