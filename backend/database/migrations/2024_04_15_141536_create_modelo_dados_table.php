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
        Schema::create('modelo_dados', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('dado_id')->nullable();
            $table->foreign('dado_id')->references('id')->on('modelo_kanban_padres');

            $table->string('tipo')->nullable();

            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->foreign('modelo_id')->references('id')->on('modelos');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('modelo_dados');
    }
};
