<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('modelos_compartidos', function (Blueprint $table) {
            $table->id();

            $table->string('name')->nullable();

            $table->unsignedBigInteger('modelo1_id')->nullable();
            $table->foreign('modelo1_id')->references('id')->on('modelos');

            $table->unsignedBigInteger('modelo2_id')->nullable();
            $table->foreign('modelo2_id')->references('id')->on('modelos');

            $table->unsignedBigInteger('modelo3_id')->nullable();
            $table->foreign('modelo3_id')->references('id')->on('modelos');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('modelos_compartidos');
    }
};
