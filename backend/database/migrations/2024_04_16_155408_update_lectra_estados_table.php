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
        Schema::table('lectra_estados', function (Blueprint $table) {
            $table->boolean("esA")->nullable()->default(false);
            $table->boolean("esB")->nullable()->default(false);
            $table->boolean("esC")->nullable()->default(false);
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
