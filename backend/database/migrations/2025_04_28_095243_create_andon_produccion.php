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
        Schema::create('andon_produccion', function (Blueprint $table) {
            $table->id();
            $table->string('turno', 1)->nullable();
            $table->date('fecha')->nullable();

            $table->integer('linea_id')->nullable();
            $table->integer('cantidad')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('andon_produccion');
    }
};
