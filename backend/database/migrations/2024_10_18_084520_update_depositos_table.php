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
        Schema::table('depositos', function (Blueprint $table) {
            $table->boolean('posiciones_piso')->nullable()->default(false);
            $table->boolean("visible")->nullable()->default(true);
        });
    }

    public function down() {
        //
    }
};
