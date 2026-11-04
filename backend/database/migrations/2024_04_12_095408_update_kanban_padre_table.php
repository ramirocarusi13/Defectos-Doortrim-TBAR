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
        Schema::table('modelo_kanban_padres', function (Blueprint $table) {
            $table->string("t_lectra1")->nullable();
            $table->string("t_lectra2")->nullable();
            $table->string("t_lectra3")->nullable();
            $table->string("t_lectra4")->nullable();
            $table->string("t_posicionamiento")->nullable();
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
