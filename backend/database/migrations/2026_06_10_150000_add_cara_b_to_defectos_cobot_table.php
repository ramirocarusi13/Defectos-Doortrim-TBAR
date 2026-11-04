<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasColumn('defectos_cobot', 'cara_b')) {
            Schema::table('defectos_cobot', function (Blueprint $table) {
                $table->boolean('cara_b')->default(false)->after('dispositivo_plc');
            });
        }
    }

    public function down() {
        if (Schema::hasColumn('defectos_cobot', 'cara_b')) {
            Schema::table('defectos_cobot', function (Blueprint $table) {
                $table->dropColumn('cara_b');
            });
        }
    }
};
