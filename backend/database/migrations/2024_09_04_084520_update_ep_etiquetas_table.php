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
        Schema::table('ep_etiquetas', function (Blueprint $table) {
            $table->string('kanban_impresion')->nullable();
            $table->integer('modelo_id')->nullable();
            $table->integer('linea')->nullable();
            $table->integer('linea_real')->nullable();
            $table->boolean('airbag')->default(false)->nullable();

            $table->dateTime('hora_validacion')->nullable();

            $table->unsignedBigInteger('user_validacion')->nullable();
            $table->foreign('user_validacion')->references('id')->on('users');
        });
    }

    public function down() {
        //
    }
};
