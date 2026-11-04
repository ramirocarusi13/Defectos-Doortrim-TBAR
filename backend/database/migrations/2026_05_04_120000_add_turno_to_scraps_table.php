<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        if (Schema::hasColumn('scraps', 'turno')) {
            return;
        }

        Schema::table('scraps', function (Blueprint $table) {
            $table->string('turno', 1)->nullable()->after('sector');
        });
    }

    public function down() {
        if (!Schema::hasColumn('scraps', 'turno')) {
            return;
        }

        Schema::table('scraps', function (Blueprint $table) {
            $table->dropColumn('turno');
        });
    }
};
