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
        Schema::create('fallas_informadas', function (Blueprint $table) {
            $table->id();

            // $table->unsignedBigInteger('kanban_id')->nullable();
            // $table->foreign('kanban_id')->references('id')->on('kanbans');

            $table->integer("cantidad")->nullable();

            $table->unsignedBigInteger('falla_id')->nullable();
            $table->foreign('falla_id')->references('id')->on('codigo_fallas');

            $table->unsignedBigInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('modelo_fallas');

            $table->unsignedBigInteger('tipo_id')->nullable();
            $table->foreign('tipo_id')->references('id')->on('tipo_partes');

            $table->unsignedBigInteger('lado_id')->nullable();
            $table->foreign('lado_id')->references('id')->on('lado_partes');

            $table->float("x")->nullable();
            $table->float("y")->nullable();

            $table->string("color")->nullable();
            $table->string("qr")->nullable();

            $table->string("estado")->nullable();
            $table->string("operacion")->nullable();
            $table->string("turno")->nullable();
            $table->string("linea")->nullable();
            $table->string("tipo_linea")->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->unsignedBigInteger('user_operacion_id')->nullable();
            $table->foreign('user_operacion_id')->references('id')->on('users');

            $table->unsignedBigInteger('user_autorizante')->nullable();
            $table->foreign('user_autorizante')->references('id')->on('users');

            $table->dateTime('fecha_retrabajo')->nullable();

            $table->unsignedBigInteger('user_retrabajo_id')->nullable();
            $table->foreign('user_retrabajo_id')->references('id')->on('users');

            $table->unsignedBigInteger('user_operacion_id')->nullable();
            $table->foreign('user_operacion_id')->references('id')->on('users');

            $table->string('observaciones')->nullable();
            $table->string('tipo_falla', 1)->nullable();

            $table->boolean('es_critico')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('fallas_informadas');
    }
};
