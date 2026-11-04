<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        if (Schema::hasColumn('scraps', 'falla_id')) {
            return;
        }

        Schema::table('scraps', function (Blueprint $table) {
            $table->unsignedBigInteger('falla_id')->nullable()->after('defecto_id');
            $table->foreign('falla_id')->references('id')->on('codigo_fallas');
        });
    }

    public function down() {
        if (!Schema::hasColumn('scraps', 'falla_id')) {
            return;
        }

        Schema::table('scraps', function (Blueprint $table) {
            $table->dropForeign(['falla_id']);
            $table->dropColumn('falla_id');
        });
    }
};
