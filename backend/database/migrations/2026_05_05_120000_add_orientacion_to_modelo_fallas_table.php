<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasColumn('modelo_fallas', 'orientacion')) {
            Schema::table('modelo_fallas', function (Blueprint $table) {
                $table->string('orientacion')->nullable()->after('nombre');
            });
        }
    }

    public function down() {
        if (Schema::hasColumn('modelo_fallas', 'orientacion')) {
            Schema::table('modelo_fallas', function (Blueprint $table) {
                $table->dropColumn('orientacion');
            });
        }
    }
};
