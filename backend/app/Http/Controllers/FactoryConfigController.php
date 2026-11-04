<?php

namespace App\Http\Controllers;

use App\Models\FactoryConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FactoryConfigController extends Controller
{
    private const CONFIG_KEY = 'active';

    public function show(): JsonResponse
    {
        $row = FactoryConfig::query()->where('key', self::CONFIG_KEY)->first();
        $data = $row?->data ?: $this->defaultConfig();

        // Bootstrap: si la config guardada es previa a los puestos, completalos con
        // el default para que la app ya tenga las 5 estaciones para elegir.
        if (empty($data['puestos'])) {
            $data['puestos'] = $this->defaultConfig()['puestos'];
        }

        return response()->json([
            'data' => $data,
            'error' => false,
        ]);
    }

    /**
     * Version liviana de la config (solo el timestamp de ultima modificacion).
     * Las tablets la consultan seguido (es chica) y solo re-bajan la config completa
     * cuando cambia -> los cambios del group leader en /config aparecen solos, sin
     * reinstalar el APK.
     */
    public function version(): JsonResponse
    {
        $row = FactoryConfig::query()->where('key', self::CONFIG_KEY)->first();

        return response()->json([
            'data' => ['version' => optional($row?->updated_at)->timestamp ?? 0],
            'error' => false,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $config = $this->normalizeConfig($request->all());

        FactoryConfig::updateOrCreate(
            ['key' => self::CONFIG_KEY],
            ['data' => $config],
        );

        return response()->json([
            'data' => $config,
            'message' => 'Configuracion guardada',
            'error' => false,
        ]);
    }

    private function normalizeConfig(array $config): array
    {
        $default = $this->defaultConfig();
        $normalized = array_replace($default, $config);

        $normalized['factory'] = array_replace($default['factory'], $config['factory'] ?? []);
        $normalized['settings'] = array_replace($default['settings'], $config['settings'] ?? []);

        foreach ([
            'companies',
            'sectors',
            'lines',
            'subLines',
            'defectCategories',
            'types',
            'sides',
            'defects',
            'models',
            'modelImages',
            'puestos',
            'buffers',
            'componentes',
            // Origen del defecto: puestos de trabajo de la linea (sector FRONT/
            // TUMBLE/SPACE-UP/TIP-UP) y dotacion de operarios por turno (A/B/N).
            'puestosTrabajo',
            'dotacion',
        ] as $collection) {
            if (array_key_exists($collection, $config) && is_array($config[$collection])) {
                $normalized[$collection] = array_values($config[$collection]);
            }
        }

        // Cada defecto del catalogo lleva su caracteristica A/B/C (grave/media/leve),
        // sellada al marcarlo. Valores fuera de A/B/C (o ausentes) caen a 'C'.
        $normalized['defects'] = array_map(function ($defect) {
            $caracteristica = strtoupper(trim((string) ($defect['caracteristica'] ?? 'C')));
            $defect['caracteristica'] = in_array($caracteristica, ['A', 'B', 'C'], true) ? $caracteristica : 'C';
            return $defect;
        }, is_array($normalized['defects'] ?? null) ? $normalized['defects'] : []);

        $normalized['updatedAt'] = now()->toISOString();

        return $normalized;
    }

    private function defaultConfig(): array
    {
        return [
            'schemaVersion' => 1,
            'factory' => [
                'id' => 'defectos-eol',
                'name' => 'Defectos Internos EOL',
                'company' => 'SAR',
                'companyId' => 'company-sar',
                'site' => '',
                'activeLineCode' => 'EOL-SAR',
            ],
            'settings' => [
                'preferLocalDefects' => false,
                'useLocalDefectsFallback' => true,
                'enableScrapModule' => true,
                // Linea de door (cambio de piezas DOOR / ARM_REST): pieza defectuosa se
                // cambia por una del mini buffer en vez de retrabajarse en linea.
                'door' => [
                    'requiereValidacionCalidad' => true,
                    'minutosSecado' => 240,
                    'permitirAltaAutomaticaRetirada' => true,
                    'maxCiclosRetrabajo' => 3,
                ],
                // Reporte diario de QC: target (cantidad maxima diaria) por linea para
                // dibujar la linea punteada roja en los graficos de evolucion. Mapa
                // linea (nombre) -> cantidad. Vacio por defecto; lo edita el front.
                'reporteQc' => [
                    'targetPorLinea' => [],
                ],
            ],
            'companies' => [
                [
                    'id' => 'company-sar',
                    'code' => 'SAR',
                    'name' => 'SAR',
                    'active' => true,
                ],
                [
                    'id' => 'company-tbar',
                    'code' => 'TBAR',
                    'name' => 'TBAR',
                    'active' => true,
                ],
            ],
            'sectors' => [
                [
                    'id' => 'sector-sar-eol',
                    'companyId' => 'company-sar',
                    'code' => 'EOL-SAR',
                    'name' => 'Fin de linea SAR',
                    'active' => true,
                ],
                [
                    'id' => 'sector-tbar-eol',
                    'companyId' => 'company-tbar',
                    'code' => 'EOL-TBAR',
                    'name' => 'Fin de linea TBAR',
                    'active' => true,
                ],
            ],
            'lines' => [
                [
                    'id' => 'line-sar-eol',
                    'sectorId' => 'sector-sar-eol',
                    'code' => 'EOL-SAR',
                    'name' => 'EOL SAR',
                    'enabled' => true,
                    'scrapEnabled' => true,
                ],
                [
                    'id' => 'line-tbar-eol',
                    'sectorId' => 'sector-tbar-eol',
                    'code' => 'EOL-TBAR',
                    'name' => 'EOL TBAR',
                    'enabled' => true,
                    'scrapEnabled' => false,
                ],
            ],
            'subLines' => [
                [
                    'id' => 'subline-sar-main',
                    'lineId' => 'line-sar-eol',
                    'code' => '0',
                    'name' => 'Principal',
                    'active' => true,
                ],
                [
                    'id' => 'subline-tbar-main',
                    'lineId' => 'line-tbar-eol',
                    'code' => '0',
                    'name' => 'Principal',
                    'active' => true,
                ],
            ],
            'defectCategories' => [
                [
                    'id' => 'category-apariencia',
                    'code' => 'APARIENCIA',
                    'name' => 'Apariencia',
                    'active' => true,
                    'color' => '3B82F6',
                ],
                [
                    'id' => 'category-funcionalidad',
                    'code' => 'FUNCIONALIDAD',
                    'name' => 'Funcionalidad',
                    'active' => true,
                    'color' => 'F97316',
                ],
            ],
            'types' => [
                [
                    'id' => 'type-back',
                    'code' => 'BACK',
                    'name' => 'Back',
                ],
                [
                    'id' => 'type-cushion',
                    'code' => 'CUSHION',
                    'name' => 'Cushion',
                ],
            ],
            'sides' => [
                [
                    'id' => 'side-100',
                    'code' => '100%',
                    'name' => '100%',
                ],
                [
                    'id' => 'side-left',
                    'code' => 'LH',
                    'name' => 'Izquierda',
                ],
                [
                    'id' => 'side-right',
                    'code' => 'RH',
                    'name' => 'Derecha',
                ],
            ],
            'defects' => [
                [
                    'id' => 'def-costura',
                    'code' => 'CST',
                    'name' => 'Costura abierta',
                    'categoryId' => 'category-apariencia',
                    'category' => 'Apariencia',
                    'severity' => 'Media',
                    'color' => 'F59E0B',
                    'active' => true,
                    // Caracteristica A/B/C: A = grave, exige autorizacion de un GL al
                    // validar el retrabajo. Default 'C' (leve) si no se define.
                    'caracteristica' => 'C',
                ],
                [
                    'id' => 'def-mancha',
                    'code' => 'MAN',
                    'name' => 'Mancha',
                    'categoryId' => 'category-apariencia',
                    'category' => 'Apariencia',
                    'severity' => 'Baja',
                    'color' => '84CC16',
                    'active' => true,
                    'caracteristica' => 'C',
                ],
                [
                    'id' => 'def-rotura',
                    'code' => 'ROT',
                    'name' => 'Rotura',
                    'categoryId' => 'category-funcionalidad',
                    'category' => 'Funcionalidad',
                    'severity' => 'Alta',
                    'color' => 'EF4444',
                    'active' => true,
                    'caracteristica' => 'A',
                ],
            ],
            'models' => [
                [
                    'id' => 'model-local',
                    'code' => 'LOCAL',
                    'name' => 'Modelo local',
                    'lineIds' => ['line-sar-eol', 'line-tbar-eol'],
                    'subLineIds' => ['subline-sar-main', 'subline-tbar-main'],
                    'active' => true,
                ],
            ],
            'modelImages' => [],
            // Puestos de control de calidad (5 estaciones). Cada tablet se fija a uno.
            // Cada defecto puede aparecer en varios puestos (defect.puestoIds).
            'puestos' => [
                ['id' => 'puesto-1', 'code' => 'P1', 'name' => 'Puesto 1', 'active' => true],
                ['id' => 'puesto-2', 'code' => 'P2', 'name' => 'Puesto 2', 'active' => true],
                ['id' => 'puesto-3', 'code' => 'P3', 'name' => 'Puesto 3', 'active' => true],
                ['id' => 'puesto-4', 'code' => 'P4', 'name' => 'Puesto 4', 'active' => true],
                ['id' => 'puesto-5', 'code' => 'P5', 'name' => 'Puesto 5', 'active' => true],
            ],
            // Mini buffers de la linea de door (cambio de piezas DOOR / ARM_REST). Vacio por
            // defecto: se cargan desde /config segun la planta. Forma esperada:
            // ['id' => 'buffer-1', 'code' => 'BUF1', 'name' => 'Buffer 1',
            //  'tiposPieza' => ['DOOR', 'ARM_REST'], 'capacidadObjetivo' => 20,
            //  'minimoAlerta' => 5, 'active' => true],
            'buffers' => [],
            // Catalogo de componentes que produccion puede declarar como consumidos al
            // retrabajar un defecto (funda, cierre, etc). Vacio por defecto: se carga
            // desde /config segun la planta. Forma esperada:
            // ['id' => 'comp-1', 'code' => 'FUNDA', 'name' => 'Funda', 'active' => true,
            //  'grupo' => 'SEAT'], // 'SEAT' | 'TIPUP' | 'AMBOS' (linea a la que aplica)
            'componentes' => [],
            'updatedAt' => null,
        ];
    }
}
