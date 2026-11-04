<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasColumn('fallas_informadas', 'imagen_marcada')) {
            Schema::table('fallas_informadas', function (Blueprint $table) {
                $table->string('imagen_marcada')->nullable()->after('image_id');
            });
        }
    }

    public function down() {
        if (Schema::hasColumn('fallas_informadas', 'imagen_marcada')) {
            Schema::table('fallas_informadas', function (Blueprint $table) {
                $table->dropColumn('imagen_marcada');
            });
        }
    }
};
