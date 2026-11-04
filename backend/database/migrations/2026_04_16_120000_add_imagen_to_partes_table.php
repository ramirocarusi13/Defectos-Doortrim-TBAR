<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasColumn('partes', 'imagen')) {
            Schema::table('partes', function (Blueprint $table) {
                $table->string('imagen')->nullable()->after('lado_id');
            });
        }
    }

    public function down() {
        if (Schema::hasColumn('partes', 'imagen')) {
            Schema::table('partes', function (Blueprint $table) {
                $table->dropColumn('imagen');
            });
        }
    }
};
