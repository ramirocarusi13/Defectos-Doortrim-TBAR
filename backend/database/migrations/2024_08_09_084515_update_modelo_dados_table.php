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
        Schema::table('modelo_dados', function (Blueprint $table) {
            $table->boolean('esA')->nullable()->default(false);
            $table->boolean('esB')->nullable()->default(false);
            $table->integer('ordenA')->nullable()->default(0);
            $table->integer('ordenB')->nullable()->default(0);
            $table->integer('ordenCompleto')->nullable()->default(0);
        });
    }

    public function down() {
        //
    }
};
