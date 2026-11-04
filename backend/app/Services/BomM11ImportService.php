<?php

namespace App\Services;

use App\Models\MaterialesPiezas;
use App\Models\ModeloKanbanPadre;
use App\Models\Modelos;
use App\Models\Partes;
use App\Models\Piezas;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use XMLReader;
use ZipArchive;

class BomM11ImportService {
    private array $requiredHeaders = [
        'Cod SAR',
        'Descripcion de Funda',
        'PART # Funda',
        '# PPT',
        'DADO',
        'COD-MAT',
        'SAP (mm2)',
    ];

    public function preview($file): array {
        return $this->analyze($file);
    }

    public function confirm($file): array {
        $analysis = $this->analyze($file);

        if (!$analysis['can_import']) {
            return $analysis;
        }

        $created = [
            'partes' => 0,
            'piezas' => 0,
            'modelo_kanban_padres' => 0,
        ];
        $skipped = [
            'partes' => 0,
            'piezas' => 0,
            'modelo_kanban_padres' => 0,
        ];

        DB::transaction(function () use ($analysis, &$created, &$skipped) {
            $partCodes = array_column($analysis['operations']['partes'], 'codigo');
            $modelIds = array_column($analysis['operations']['partes'], 'modelo_id');

            $partesByKey = Partes::whereIn('codigo', $partCodes)
                ->whereIn('modelo_id', $modelIds)
                ->get()
                ->keyBy(fn($parte) => $this->parteKey($parte->modelo_id, $parte->codigo, $parte->tipo_id, $parte->lado_id));

            foreach ($analysis['operations']['partes'] as $parteData) {
                $codigo = $parteData['codigo'];
                $parteKey = $this->parteKey($parteData['modelo_id'], $codigo, $parteData['tipo_id'], $parteData['lado_id']);

                if ($partesByKey->has($parteKey)) {
                    $skipped['partes']++;
                    continue;
                }

                $parte = Partes::create([
                    'modelo_id' => $parteData['modelo_id'],
                    'codigo' => $codigo,
                    'activo' => true,
                    'tipo_id' => $parteData['tipo_id'],
                    'lado_id' => $parteData['lado_id'],
                ]);

                $partesByKey->put($parteKey, $parte);
                $created['partes']++;
            }

            foreach ($analysis['operations']['piezas'] as $piezaData) {
                $parte = $partesByKey->get($piezaData['parte_key']);

                if (!$parte) {
                    continue;
                }

                $exists = Piezas::where('parte_id', $parte->id)
                    ->where('codigo', $piezaData['codigo'])
                    ->exists();

                if ($exists) {
                    $skipped['piezas']++;
                    continue;
                }

                Piezas::create([
                    'codigo' => $piezaData['codigo'],
                    'parte_id' => $parte->id,
                    'dado' => $piezaData['dado'],
                    'material_pieza_id' => $piezaData['material_pieza_id'],
                ]);

                $created['piezas']++;
            }

            foreach ($analysis['operations']['modelo_kanban_padres'] as $padreData) {
                $exists = ModeloKanbanPadre::where('modelo_id', $padreData['modelo_id'])
                    ->where('material_id', $padreData['material_id'])
                    ->exists();

                if ($exists) {
                    $skipped['modelo_kanban_padres']++;
                    continue;
                }

                ModeloKanbanPadre::create([
                    'modelo_id' => $padreData['modelo_id'],
                    'consumo' => $padreData['consumo'],
                    'corte' => 1,
                    'dado' => $padreData['dado'],
                    'material_id' => $padreData['material_id'],
                ]);

                $created['modelo_kanban_padres']++;
            }
        });

        $analysis['imported'] = [
            'created' => $created,
            'skipped_existing' => $skipped,
        ];
        $analysis['message'] = 'Importacion finalizada';

        return $analysis;
    }

    private function analyze($file): array {
        $sheetRows = $this->readRows($file);

        $headers = $this->buildHeaders($sheetRows[0] ?? []);
        $missingHeaders = $this->missingHeaders($headers);

        if (count($missingHeaders) > 0) {
            return [
                'can_import' => false,
                'summary' => $this->emptySummary(),
                'errors' => array_map(fn($header) => [
                    'row' => 1,
                    'field' => $header,
                    'message' => 'No se encontro la columna requerida: ' . $header,
                ], $missingHeaders),
                'rows' => [],
                'operations' => $this->emptyOperations(),
            ];
        }

        $rawRows = [];
        $modelNames = [];
        $materialCodes = [];
        $partCodes = [];

        foreach (array_slice($sheetRows, 1) as $rowIndex => $rowValues) {
            $rowNumber = $rowIndex + 2;
            $row = $this->rowByHeader($headers, $rowValues);

            if ($this->isEmptyImportRow($row)) {
                continue;
            }

            $normalized = [
                'row' => $rowNumber,
                'cod_sar' => $this->cleanText($row['Cod SAR'] ?? null),
                'descripcion_funda' => $this->cleanText($row['Descripcion de Funda'] ?? null),
                'part_funda' => $this->cleanText($row['PART # Funda'] ?? null),
                'ppt' => $this->cleanText($row['# PPT'] ?? null),
                'dado' => $this->cleanText($row['DADO'] ?? null),
                'cod_mat' => $this->cleanText($row['COD-MAT'] ?? null),
                'sap_mm2' => $this->normalizeDecimal($row['SAP (mm2)'] ?? null),
            ];

            $rawRows[] = $normalized;
            $this->addUnique($modelNames, $normalized['cod_sar']);
            $this->addUnique($materialCodes, $normalized['cod_mat']);
            $this->addUnique($partCodes, $normalized['part_funda']);
        }

        $modelsByName = Modelos::whereIn('nombre', $modelNames)->get()
            ->keyBy(fn($modelo) => $this->cleanText($modelo->nombre));
        $materialsByCode = MaterialesPiezas::whereIn('codigo_interno', $materialCodes)->get()
            ->keyBy(fn($material) => $this->cleanText($material->codigo_interno));
        $partsByKey = Partes::whereIn('codigo', $partCodes)->get()
            ->keyBy(fn($parte) => $this->parteKey($parte->modelo_id, $parte->codigo, $parte->tipo_id, $parte->lado_id));

        $existingPieces = $this->existingPieces($partsByKey);
        $existingPadres = $this->existingPadres($modelsByName, $materialsByCode);

        $errors = [];
        $rows = [];
        $operations = $this->emptyOperations();
        $seenPartes = [];
        $seenPiezas = [];
        $seenPadres = [];

        foreach ($rawRows as $rawRow) {
            $rowErrors = $this->validateRow($rawRow, $modelsByName, $materialsByCode);
            $errors = array_merge($errors, $rowErrors);

            $modelo = $modelsByName->get($rawRow['cod_sar']);
            $material = $materialsByCode->get($rawRow['cod_mat']);
            $tipoId = $this->resolveTipoId($rawRow['descripcion_funda']);
            $ladoId = $this->resolveLadoId($rawRow['descripcion_funda']);
            $currentParteKey = $modelo ? $this->parteKey($modelo->id, $rawRow['part_funda'], $tipoId, $ladoId) : null;
            $parte = $currentParteKey ? $partsByKey->get($currentParteKey) : null;

            $parteStatus = $parte ? 'exists' : 'new';
            $piezaStatus = 'new';
            $padreStatus = empty($rawRow['dado']) ? 'omitted' : 'new';

            if ($parte) {
                $pieceKey = $parte->id . '|' . $rawRow['ppt'];
                $piezaStatus = isset($existingPieces[$pieceKey]) ? 'exists' : 'new';
            }

            if ($modelo && $material && !empty($rawRow['dado'])) {
                $padreKey = $modelo->id . '|' . $material->id;
                $padreStatus = isset($existingPadres[$padreKey]) ? 'exists' : 'new';
            }

            if (count($rowErrors) === 0) {
                if (!isset($seenPartes[$currentParteKey])) {
                    $seenPartes[$currentParteKey] = true;
                    $operations['partes'][] = [
                        'key' => $currentParteKey,
                        'codigo' => $rawRow['part_funda'],
                        'modelo_id' => $modelo->id,
                        'modelo' => $modelo->nombre,
                        'tipo_id' => $tipoId,
                        'lado_id' => $ladoId,
                        'exists' => $parteStatus === 'exists',
                    ];
                }

                $piezaKey = $currentParteKey . '|' . $rawRow['ppt'];
                if (!isset($seenPiezas[$piezaKey])) {
                    $seenPiezas[$piezaKey] = true;
                    $operations['piezas'][] = [
                        'codigo' => $rawRow['ppt'],
                        'parte_key' => $currentParteKey,
                        'parte_codigo' => $rawRow['part_funda'],
                        'dado' => $rawRow['dado'],
                        'material_pieza_id' => $material->id,
                        'material_codigo' => $material->codigo_interno,
                        'exists' => $piezaStatus === 'exists',
                    ];
                }

                if (!empty($rawRow['dado'])) {
                    $padreKey = $modelo->id . '|' . $material->id;
                    if (!isset($seenPadres[$padreKey])) {
                        $seenPadres[$padreKey] = true;
                        $operations['modelo_kanban_padres'][] = [
                            'modelo_id' => $modelo->id,
                            'modelo' => $modelo->nombre,
                            'consumo' => $rawRow['sap_mm2'],
                            'corte' => 1,
                            'dado' => $rawRow['dado'],
                            'material_id' => $material->id,
                            'material_codigo' => $material->codigo_interno,
                            'exists' => $padreStatus === 'exists',
                        ];
                    }
                }
            }

            $rows[] = [
                ...$rawRow,
                'modelo_id' => $modelo?->id,
                'material_pieza_id' => $material?->id,
                'tipo_id' => $tipoId,
                'lado_id' => $ladoId,
                'parte_status' => $parteStatus,
                'pieza_status' => count($rowErrors) > 0 ? 'error' : $piezaStatus,
                'kanban_padre_status' => count($rowErrors) > 0 ? 'error' : $padreStatus,
                'errors' => array_map(fn($error) => $error['message'], $rowErrors),
            ];
        }

        return [
            'can_import' => count($errors) === 0,
            'summary' => $this->summary($rows, $operations),
            'errors' => $errors,
            'rows' => $rows,
            'operations' => $operations,
        ];
    }

    private function buildHeaders(array $headerRow): array {
        $headers = [];

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader($header);
            if (!empty($normalized)) {
                $headers[$normalized] = $index;
            }
        }

        return $headers;
    }

    private function readRows($file): array {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->getExtension());

        if ($extension === 'xlsx') {
            return $this->readXlsxRows($file->getRealPath());
        }

        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());

        return $spreadsheet->getSheet(0)->toArray(null, true, false, false);
    }

    private function readXlsxRows(string $path): array {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $reader = new XMLReader();
        $reader->XML($sheetXml);
        $rows = [];

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'row') {
                continue;
            }

            $row = [];

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'row') {
                    break;
                }

                if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'c') {
                    $cellRef = $reader->getAttribute('r');
                    $cellType = $reader->getAttribute('t');
                    $columnIndex = $this->columnIndexFromCellRef($cellRef);
                    $row[$columnIndex] = $this->readCellValue($reader->readOuterXML(), $cellType, $sharedStrings);
                }
            }

            if (count($row) > 0) {
                ksort($row);
                $rows[] = $this->normalizeSparseRow($row);
            }
        }

        $reader->close();

        return $rows;
    }

    private function readSharedStrings(ZipArchive $zip): array {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $reader = new XMLReader();
        $reader->XML($xml);
        $strings = [];

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'si') {
                $si = simplexml_load_string($reader->readOuterXML());
                $si->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $text = '';

                foreach ($si->xpath('.//a:t') as $textNode) {
                    $text .= (string) $textNode;
                }

                $strings[] = $text;
            }
        }

        $reader->close();

        return $strings;
    }

    private function readCellValue(string $cellXml, ?string $cellType, array $sharedStrings) {
        $cell = simplexml_load_string($cellXml);
        $cell->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        if ($cellType === 's') {
            $values = $cell->xpath('.//a:v');
            $index = isset($values[0]) ? (int) $values[0] : null;

            return $index !== null ? ($sharedStrings[$index] ?? null) : null;
        }

        if ($cellType === 'inlineStr') {
            $text = '';

            foreach ($cell->xpath('.//a:t') as $textNode) {
                $text .= (string) $textNode;
            }

            return $text;
        }

        $values = $cell->xpath('.//a:v');

        return isset($values[0]) ? (string) $values[0] : null;
    }

    private function normalizeSparseRow(array $row): array {
        $max = max(array_keys($row));
        $normalized = [];

        for ($index = 0; $index <= $max; $index++) {
            $normalized[$index] = $row[$index] ?? null;
        }

        return $normalized;
    }

    private function columnIndexFromCellRef(?string $cellRef): int {
        preg_match('/^[A-Z]+/', $cellRef ?? 'A', $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function rowByHeader(array $headers, array $row): array {
        $mapped = [];

        foreach ($headers as $header => $index) {
            $mapped[$header] = $row[$index] ?? null;
        }

        return $mapped;
    }

    private function missingHeaders(array $headers): array {
        return array_values(array_filter($this->requiredHeaders, fn($header) => !array_key_exists($header, $headers)));
    }

    private function normalizeHeader($value): string {
        $value = $this->cleanText($value);
        $value = str_replace(['Descripcion'], ['Descripcion'], $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return match ($value) {
            'Descripcion de Funda', 'Descripción de Funda' => 'Descripcion de Funda',
            'SAP (mm2)', 'SAP mm2' => 'SAP (mm2)',
            default => $value,
        };
    }

    private function validateRow(array $row, $modelsByName, $materialsByCode): array {
        $errors = [];

        foreach ([
            'cod_sar' => 'Cod SAR',
            'part_funda' => 'PART # Funda',
            'ppt' => '# PPT',
            'cod_mat' => 'COD-MAT',
        ] as $field => $label) {
            if ($row[$field] === '') {
                $errors[] = [
                    'row' => $row['row'],
                    'field' => $label,
                    'message' => $label . ' no tiene valor',
                ];
            }
        }

        if ($row['cod_sar'] !== '' && !$modelsByName->has($row['cod_sar'])) {
            $errors[] = [
                'row' => $row['row'],
                'field' => 'Cod SAR',
                'message' => 'Modelo inexistente: ' . $row['cod_sar'],
            ];
        }

        if ($row['cod_mat'] !== '' && !$materialsByCode->has($row['cod_mat'])) {
            $errors[] = [
                'row' => $row['row'],
                'field' => 'COD-MAT',
                'message' => 'Material no encontrado: ' . $row['cod_mat'],
            ];
        }

        return $errors;
    }

    private function existingPieces($partsByKey): array {
        $partIds = $partsByKey->pluck('id')->all();

        if (count($partIds) === 0) {
            return [];
        }

        return Piezas::whereIn('parte_id', $partIds)->get()
            ->mapWithKeys(fn($pieza) => [$pieza->parte_id . '|' . $this->cleanText($pieza->codigo) => true])
            ->all();
    }

    private function existingPadres($modelsByName, $materialsByCode): array {
        $modelIds = $modelsByName->pluck('id')->all();
        $materialIds = $materialsByCode->pluck('id')->all();

        if (count($modelIds) === 0 || count($materialIds) === 0) {
            return [];
        }

        return ModeloKanbanPadre::whereIn('modelo_id', $modelIds)
            ->whereIn('material_id', $materialIds)
            ->get()
            ->mapWithKeys(fn($padre) => [$padre->modelo_id . '|' . $padre->material_id => true])
            ->all();
    }

    private function summary(array $rows, array $operations): array {
        return [
            'rows' => count($rows),
            'errors' => count(array_filter($rows, fn($row) => count($row['errors']) > 0)),
            'partes_new' => count(array_filter($operations['partes'], fn($item) => !$item['exists'])),
            'partes_existing' => count(array_filter($operations['partes'], fn($item) => $item['exists'])),
            'piezas_new' => count(array_filter($operations['piezas'], fn($item) => !$item['exists'])),
            'piezas_existing' => count(array_filter($operations['piezas'], fn($item) => $item['exists'])),
            'kanban_padres_new' => count(array_filter($operations['modelo_kanban_padres'], fn($item) => !$item['exists'])),
            'kanban_padres_existing' => count(array_filter($operations['modelo_kanban_padres'], fn($item) => $item['exists'])),
        ];
    }

    private function emptySummary(): array {
        return [
            'rows' => 0,
            'errors' => 0,
            'partes_new' => 0,
            'partes_existing' => 0,
            'piezas_new' => 0,
            'piezas_existing' => 0,
            'kanban_padres_new' => 0,
            'kanban_padres_existing' => 0,
        ];
    }

    private function emptyOperations(): array {
        return [
            'partes' => [],
            'piezas' => [],
            'modelo_kanban_padres' => [],
        ];
    }

    private function isEmptyImportRow(array $row): bool {
        if ($this->cleanText($row['DADO'] ?? null) === '') {
            return true;
        }

        foreach ($this->requiredHeaders as $header) {
            if ($this->cleanText($row[$header] ?? null) !== '') {
                return false;
            }
        }

        return true;
    }

    private function resolveTipoId(string $descripcion): ?int {
        $description = $this->normalizedDescription($descripcion);

        if (preg_match('/(^|\s)(BC|FB|F B|RB)(\s|$)/', $description)) {
            return 1;
        }

        if (preg_match('/(^|\s)(FC|F C|CS|RC|CH)(\s|$)/', $description)) {
            return 2;
        }

        if (preg_match('/(^|\s)(A R|AR)(\s|$)/', $description)) {
            return 3;
        }

        return null;
    }

    private function resolveLadoId(string $descripcion): int {
        $description = $this->normalizedDescription($descripcion);

        if (preg_match('/(^|\s)(LH|L H)(\s|$)/', $description)) {
            return 3;
        }

        if (preg_match('/(^|\s)(RH|R H)(\s|$)/', $description)) {
            return 2;
        }

        return 4;
    }

    private function normalizedDescription(string $value): string {
        $value = strtoupper($value);
        $value = str_replace(['/', '-', '_'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return ' ' . trim($value) . ' ';
    }

    private function parteKey($modeloId, $codigo, $tipoId, $ladoId): string {
        return implode('|', [
            $modeloId ?? '',
            $this->cleanText($codigo),
            $tipoId ?? '',
            $ladoId ?? '',
        ]);
    }

    private function normalizeDecimal($value): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = str_replace(',', '.', $this->cleanText($value));

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function cleanText($value): string {
        if ($value === null) {
            return '';
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        return trim((string) $value);
    }

    private function addUnique(array &$values, string $value): void {
        if ($value !== '' && !in_array($value, $values, true)) {
            $values[] = $value;
        }
    }
}
