<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('lectra_estados', function (Blueprint $table) {
            $table->unsignedBigInteger('pieza_id')->nullable()->after('dado_id');
            $table->foreign('pieza_id')->references('id')->on('piezas');
        });
    }

    public function down() {
        Schema::table('lectra_estados', function (Blueprint $table) {
            $table->dropForeign(['pieza_id']);
            $table->dropColumn('pieza_id');
        });
    }
};
