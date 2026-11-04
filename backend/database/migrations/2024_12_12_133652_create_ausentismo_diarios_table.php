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
        Schema::create('ausentismo_diarios', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ausentismo_id')->nullable();
            $table->foreign('ausentismo_id')->references('id')->on('rrhh_ausentismos');

            $table->date('fecha')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('ausentismo_diarios');
    }
};
