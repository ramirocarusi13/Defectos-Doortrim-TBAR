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
        Schema::create('lectra_estados', function (Blueprint $table) {
            $table->id();

            $table->string("lectra", 1)->nullable();

            $table->dateTime("fin")->nullable();
            $table->dateTime("inicio")->nullable();

            $table->string("modelo")->nullable();

            $table->uuid("operacion")->nullable();

            // $table->unsignedBigInteger('dado_id')->nullable();
            // $table->foreign('dado_id')->references('id')->on('modelo_kanban_padres');

            $table->string("dado_id")->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('lectra_estados');
    }
};
