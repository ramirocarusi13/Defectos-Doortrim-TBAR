<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Guarda EN QUE VISTA/FOTO del modelo se marco el defecto, para que la marca
// aparezca exactamente en la misma foto en la web y en la app (cada modelo
// tiene varias vistas/angulos). vista_id = id de la imagen en factory-config.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('eol_defectos', function (Blueprint $table) {
            $table->string('vista_id', 80)->nullable()->after('imagen_marcada');
            $table->string('vista_nombre', 150)->nullable()->after('vista_id');
        });
    }

    public function down(): void
    {
        Schema::table('eol_defectos', function (Blueprint $table) {
            $table->dropColumn(['vista_id', 'vista_nombre']);
        });
    }
};
