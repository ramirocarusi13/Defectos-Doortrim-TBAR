<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Un registro por swap fisico en la linea de door: se retira una pieza del
 * asiento (defectuosa o no) y se entrega una del buffer en su lugar. Guarda
 * snapshot del contexto (modelo/linea/lado) porque eso puede cambiar en el
 * maestro de la pieza con el tiempo y el door debe quedar fijo en el tiempo.
 *
 * client_uuid es opcional (idempotencia desde la app movil offline-first): se
 * necesita unico SOLO cuando viene informado. SQL Server no permite un UNIQUE
 * normal con multiples NULL admitidos de forma segura para este caso de uso,
 * por eso se crea un indice unico FILTRADO (WHERE client_uuid IS NOT NULL) a
 * mano en vez de usar ->unique() de Eloquent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eol_doors')) {
            Schema::create('eol_doors', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->string('qr', 120)->nullable()->index(); // QR del asiento/kanban

                // Snapshot al momento del cambio
                $table->string('modelo', 150)->nullable();
                $table->string('secuencia', 60)->nullable();
                $table->string('linea', 60)->nullable();
                $table->string('lado', 30)->nullable();
                $table->string('tipo_pieza', 30)->nullable();

                $table->unsignedBigInteger('pieza_retirada_id')->nullable()->index();
                $table->string('pieza_retirada_qr', 120)->nullable();

                $table->unsignedBigInteger('pieza_entregada_id')->nullable()->index();
                $table->string('pieza_entregada_qr', 120)->nullable();

                $table->string('buffer_id', 60)->nullable();
                $table->string('buffer_nombre', 120)->nullable();

                $table->integer('defectos_count')->default(0);

                $table->string('puesto_id', 60)->nullable();
                $table->string('puesto_nombre', 120)->nullable();

                $table->unsignedBigInteger('cambiado_por')->nullable();
                $table->string('cambiado_por_nombre', 120)->nullable();
                $table->string('turno', 10)->nullable();
                $table->dateTime('cambiado_at')->nullable()->index();

                $table->boolean('alta_automatica_retirada')->default(false);

                $table->string('client_uuid', 64)->nullable();

                $table->string('observaciones', 500)->nullable();

                $table->timestamps();
            });
        }

        // Indice unico filtrado: solo exige unicidad cuando client_uuid no es null.
        if (!$this->indexExists('eol_doors', 'eol_doors_client_uuid_unique_filtered')) {
            DB::statement(
                'CREATE UNIQUE INDEX eol_doors_client_uuid_unique_filtered ON eol_doors (client_uuid) WHERE client_uuid IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        if ($this->indexExists('eol_doors', 'eol_doors_client_uuid_unique_filtered')) {
            DB::statement('DROP INDEX eol_doors_client_uuid_unique_filtered ON eol_doors');
        }

        Schema::dropIfExists('eol_doors');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)',
            [$indexName, $table]
        );

        return $result && (int) $result->total > 0;
    }
};
