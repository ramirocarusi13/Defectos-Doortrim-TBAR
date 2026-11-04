<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('lectra_estados', function (Blueprint $table) {
            $table->boolean('es_reposicion')->nullable()->default(false)->after('esC');
        });
    }

    public function down() {
        Schema::table('lectra_estados', function (Blueprint $table) {
            $table->dropColumn('es_reposicion');
        });
    }
};
