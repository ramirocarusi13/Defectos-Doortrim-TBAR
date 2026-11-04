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
        Schema::create('plan_produccions', function (Blueprint $table) {
            $table->id();

            $table->string('linea')->nullable();
            $table->string('modelo')->nullable();
            $table->integer('cantidad')->nullable();
            $table->date('fecha')->nullable();
            $table->integer('orden')->nullable();
            $table->dateTime('hora_esperada')->nullable();

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
        Schema::dropIfExists('plan_produccions');
    }
};
