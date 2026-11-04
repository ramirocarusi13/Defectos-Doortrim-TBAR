<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasColumn('fallas_informadas', 'origen')) {
            Schema::table('fallas_informadas', function (Blueprint $table) {
                $table->string('origen')->nullable()->after('tipo_falla');
            });
        }
    }

    public function down() {
        if (Schema::hasColumn('fallas_informadas', 'origen')) {
            Schema::table('fallas_informadas', function (Blueprint $table) {
                $table->dropColumn('origen');
            });
        }
    }
};
