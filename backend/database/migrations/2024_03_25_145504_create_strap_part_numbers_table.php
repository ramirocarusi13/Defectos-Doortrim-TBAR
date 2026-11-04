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
        Schema::create('strap_part_numbers', function (Blueprint $table) {
            $table->id();

            $table->string("modelo")->nullable();
            $table->string("part_number")->nullable();
            $table->string("posicion")->nullable();
            $table->integer("nro_fifo")->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('strap_part_numbers');
    }
};
