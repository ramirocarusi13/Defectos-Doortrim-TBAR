<?php

namespace App\Http\Controllers\Concerns;

use App\Models\EolDefecto;
use App\Models\FactoryConfig;

/**
 * Adapta filas de eol_defectos a la estructura legacy que consumen las
 * pantallas de Fin de Linea / Retrabajo (y, desde el flujo de door, la app
 * de door via EolDoorController::contexto()): etiqueta.modelod, falla, tipo,
 * lado, imagen... Se comparte para que la app consuma el MISMO shape sin
 * importar si el defecto es flujo EOL o DOOR.
 */
trait MapeaDefectoLegacy
{
    /** Cache vista_id => remotePath (foto de la vista en factory-config). */
    private ?array $vistaImagenesCache = null;

    /**
     * Resuelve la foto de una vista (por su id en factory-config). Se usa como
     * fallback cuando el defecto no tiene snapshot (imagen_marcada), para que la
     * marca se vea sobre la MISMA foto que en la app/web.
     */
    private function vistaImagenPath(?string $vistaId): ?string
    {
        if (!$vistaId) {
            return null;
        }

        if ($this->vistaImagenesCache === null) {
            $this->vistaImagenesCache = [];
            $row = FactoryConfig::query()->where('key', 'active')->first();
            foreach (($row->data['modelImages'] ?? []) as $img) {
                if (!empty($img['id'])) {
                    $this->vistaImagenesCache[(string) $img['id']] = $img['imageDataUrl'] ?: ($img['remotePath'] ?? null);
                }
            }
        }

        return $this->vistaImagenesCache[$vistaId] ?? null;
    }

    /**
     * Adapta una fila de eol_defectos a la estructura que consume la UI (etiqueta.modelod, falla, tipo, lado...).
     */
    private function mapToLegacy(EolDefecto $d): array
    {
        $estado = $d->estado === 'REPARADO' ? 'RETRABAJADO' : ($d->estado ?: 'PENDIENTE');

        return [
            'id'                 => $d->id,
            'qr'                 => $d->qr,
            'funda'              => $d->qr,
            'modelo'             => $d->modelo,
            'secuencia'          => $d->secuencia,
            'linea'              => $d->linea,
            'estado'             => $estado,
            'created_at'         => optional($d->marcado_at)->format('Y-m-d H:i'),
            'x'                  => $d->pos_x,
            'y'                  => $d->pos_y,
            'screenX'            => $d->screen_x,
            'screenY'            => $d->screen_y,
            'color'              => $d->color,
            'cantidad'           => $d->cantidad,
            'observaciones'      => $d->observaciones,
            'esLeve'             => (bool) $d->es_leve,
            'es_leve'            => (bool) $d->es_leve,
            'es_critico'         => (bool) $d->es_critico,
            // A/B/C sellado al marcar. La tablet lo usa para saber, ANTES de llamar a
            // validar, si el defecto es tipo A y por lo tanto va a pedir GL.
            'caracteristica'     => $d->caracteristica,
            'tipo_id'            => null,
            'lado_id'            => null,
            // Si ya tiene operario asignado, deja de figurar "ASIGNAR OPERADOR" y aparece RETRABAJAR.
            'user_operacion_id'  => $d->operario_id,
            'falla'              => [
                'id'     => $d->falla_id,
                'codigo' => $d->falla_codigo,
                'nombre' => $d->falla_nombre,
            ],
            'tipo'               => ['tipo' => $d->tipo],
            'lado'               => ['lado' => $d->lado],
            // Vista/foto del modelo donde se marco (id de imagen en factory-config).
            'vista_id'           => $d->vista_id,
            'vista_nombre'       => $d->vista_nombre,
            // Foto para mostrar la marca: el snapshot capturado si existe; si no,
            // la foto de la vista (por vista_id) para que se vea la MISMA imagen.
            'imagen'             => ['id' => $d->vista_id, 'imagen' => $d->imagen_marcada ?: $this->vistaImagenPath($d->vista_id)],
            // Quien GENERO el defecto (asignado en retrabajo). null si todavia no se asigno.
            'operador'           => $d->operario_id ? ['id' => $d->operario_id, 'email' => $d->operario_nombre, 'name' => $d->operario_nombre] : null,
            'operacion'          => $d->operacion_id ? ['id' => $d->operacion_id, 'nombre' => $d->operacion_nombre] : null,
            'turno_generacion'   => $d->turno_generacion,
            'tipo_linea'         => $d->es_sublinea ? 'SUB' : 'MAIN',
            'puesto_id'          => $d->puesto_id,
            'puesto_nombre'      => $d->puesto_nombre,
            'marcado_por_nombre' => $d->marcado_por_nombre,
            'reparado_por'       => $d->reparado_por,
            'reparado_por_nombre' => $d->reparado_por_nombre,
            'reparado_at'        => optional($d->reparado_at)->format('Y-m-d H:i'),
            // Lo que declaro el retrabajador al cerrar el retrabajo (calidad lo ve al
            // validar): ORIGEN del defecto (puesto / turno / operario) y componentes.
            'origen'             => ($d->asignado_at || $d->operacion_nombre) ? [
                'puesto'   => $d->operacion_nombre,
                'sector'   => $d->sector_generacion ?? null,
                'turno'    => $d->turno_generacion,
                'legajo'   => $d->operario_id,
                'operario' => $d->operario_nombre,
            ] : null,
            'componentes'        => $d->reparado_at
                ? \App\Models\EolDefectoComponente::where('eol_defecto_id', $d->id)->orderBy('id')
                    ->get(['componente_codigo', 'componente_nombre', 'accion', 'cantidad'])
                    ->map(fn ($c) => [
                        'codigo'   => $c->componente_codigo,
                        'nombre'   => $c->componente_nombre,
                        'accion'   => $c->accion,
                        'cantidad' => (int) $c->cantidad,
                    ])->values()->all()
                : [],
            'etiqueta'           => [
                'linea'   => $d->linea,
                'modelod' => ['nombre' => $d->modelo],
            ],
        ];
    }
}
