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
        Schema::table('modelos', function (Blueprint $table) {
            $table->integer("revision")->nullable();
            $table->integer("volumen")->nullable();
            $table->integer("minimo_buffer")->nullable();
            $table->integer("ptopedido_buffer")->nullable();
            $table->integer("consumo")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        //
    }
};
