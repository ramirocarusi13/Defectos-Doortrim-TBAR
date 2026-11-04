<?php

namespace App\Http\Controllers;

use App\Models\DefectoCobot;
use App\Models\EpEtiqueta;
use App\Models\Partes;
use App\Services\EtiquetaService;
use App\Services\FallaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DefectoCobotController extends Controller {
    public function index() {
        $data = DefectoCobot::with(['falla', 'partes.tipo', 'partes.lado', 'partes.modelo'])
            ->orderBy('nro_cobot')
            ->orderBy('numero_bit')
            ->get();

        Partes::attachModeloFallas($data->pluck('partes')->flatten());

        return $this->setResponse($data->toArray());
    }

    public function store(Request $request) {
        $validated = $this->validateRequest($request);
        $parteIds = $this->resolveParteIds($validated['parte_ids'] ?? []);
        $partePuntos = $validated['parte_puntos'] ?? [];
        unset($validated['parte_ids']);
        unset($validated['parte_puntos']);

        $defecto = DefectoCobot::create($validated)->load('falla');
        $defecto->partes()->sync($this->buildParteSyncData($parteIds, $partePuntos));

        return $this->setResponse($this->freshDefectoCobotResponse($defecto)->toArray(), 'Defecto cobot creado', false, 201);
    }

    public function storeFallas(Request $request) {
        Log::alert($request->all());
        $validated = $request->validate([
            'timestamp' => ['nullable', 'string'],
            'words'     => ['required', 'array'],
            'bits'      => ['required', 'array'],
            'user'    => ['nullable', 'integer', 'exists:users,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'linea'     => ['nullable'],
            'nro_cobot'   => ['nullable'],
        ]);

        $qr = $this->resolveQrDesdeWords($request);
        if (!$qr) {
            return $this->setResponse([], 'No se encontro el QR en words D440-D469.', true);
        }

        $bits = $this->normalizeBits($request);
        if (empty($bits)) {
            return $this->setResponse([], 'Debe informar el estado de bits del cobot.', true);
        }

        $etiqueta = EtiquetaService::getEtiquetaPorQr($qr);
        if (!$etiqueta) {
            return $this->setResponse([], 'La etiqueta ingresada no existe.', true);
        }

        $parte = $this->resolveParteDesdeEtiqueta($etiqueta);
        if (!$parte) {
            return $this->setResponse([], 'No se encontro una parte para el codigo de la etiqueta.', true);
        }

        $defectos = DefectoCobot::with(['falla', 'partes.tipo', 'partes.lado', 'partes.modelo'])
            ->whereNotNull('falla_id')
            ->whereHas('partes', function ($query) use ($parte) {
                $query->where('partes.id', $parte->id);
            })
            ->orderBy('nro_cobot')
            ->orderBy('numero_bit')
            ->get();

        if ($defectos->isEmpty()) {
            return $this->setResponse([], 'La parte no tiene defectos cobot configurados.', true);
        }

        if (!$this->bitsCubrenDefectos($bits, $defectos)) {
            return $this->setResponse([], 'El estado recibido no contiene todos los bits configurados para la parte.', true);
        }

        Partes::attachModeloFallas($defectos->pluck('partes')->flatten());

        $circulos = [];
        $bitsAnalizados = [];
        foreach ($defectos as $defecto) {
            $bitKey = $this->bitKeyForDefecto($defecto);
            $bitValue = $this->bitValue($bits, $defecto);
            $resultado = $bitValue === '0' ? 'FALLA' : 'OK';
            $bitsAnalizados[] = [
                'defecto_cobot_id' => $defecto->id,
                'dispositivo_plc'  => $defecto->dispositivo_plc,
                'numero_bit'       => intval($defecto->numero_bit),
                'bit_key'          => $bitKey,
                'valor'            => $bitValue,
                'resultado'        => $resultado,
                'falla_id'         => $defecto->falla_id,
                'falla'            => $defecto->falla ? ($defecto->falla->codigo . ' - ' . $defecto->falla->nombre) : null,
            ];

            if ($bitValue !== '0') {
                continue;
            }

            $parteDefecto = $defecto->partes->firstWhere('id', $parte->id);
            if (!$parteDefecto) {
                continue;
            }

            $modeloFalla = $this->resolveModeloFallaParaDefecto($parteDefecto, $defecto);

            if (!$modeloFalla || is_null($parteDefecto->pivot?->x) || is_null($parteDefecto->pivot?->y)) {
                Log::error('DefectoCobotController::storeFallas : defecto cobot sin imagen o punto configurado. ID ' . $defecto->id);
                continue;
            }

            $circulos[] = $this->buildCirculoCobot($defecto, $parteDefecto, $modeloFalla, $request);
        }

        Log::info(
            'DefectoCobotController::storeFallas bits analizados',
            [
                'timestamp' => $request->timestamp,
                'qr' => $qr,
                'modelo' => $etiqueta->modelo,
                'codigo' => $etiqueta->codigo,
                'parte_id' => $parte->id,
                'parte_codigo' => $parte->codigo,
                'bits_analizados' => $bitsAnalizados,
                'fallas_detectadas' => count($circulos),
            ]
        );

        if (empty($circulos)) {
            return $this->setResponse([], 'No se detectaron fallas para la etiqueta informada.');
        }

        try {
            $fallaService = new FallaService();
            $creaFallas = $fallaService->crearFallaDesdeCirculos(
                $circulos,
                $validated['user'] ?? $validated['user_id'] ?? null,
                $qr,
                $request->linea ?? $etiqueta->linea_real ?? $etiqueta->linea
            );

            if (!$creaFallas) {
                return $this->setResponse([], 'Ocurrio un error al crear las fallas del cobot.', true);
            }

            return $this->setResponse([
                'parte' => $parte->toArray(),
                'fallas_cargadas' => count($circulos),
            ], 'Actualizado correctamente!');
        } catch (\Throwable $th) {
            Log::error('DefectoCobotController::storeFallas : ' . $th->getMessage());
            return $this->setResponse([], 'Ocurrio un error al crear las fallas del cobot.', true);
        }
    }

    public function update(Request $request, DefectoCobot $defectoCobot) {
        $validated = $this->validateRequest($request);
        $parteIds = $this->resolveParteIds($validated['parte_ids'] ?? []);
        $partePuntos = $validated['parte_puntos'] ?? [];
        unset($validated['parte_ids']);
        unset($validated['parte_puntos']);

        $defectoCobot->update($validated);
        $defectoCobot->partes()->sync($this->buildParteSyncData($parteIds, $partePuntos));

        return $this->setResponse($this->freshDefectoCobotResponse($defectoCobot)->toArray(), 'Defecto cobot actualizado');
    }

    public function import(Request $request) {
        $validated = $request->validate([
            'cobot.nro_cobot'            => ['required', 'integer', 'min:1'],
            'cobot.nombre'               => ['required', 'string', 'max:255'],
            'rows'                       => ['required', 'array', 'min:1'],
            'rows.*.numero_bit'          => ['required', 'integer', 'min:0'],
            'rows.*.dispositivo_plc'     => ['required', 'string', 'max:255'],
            'rows.*.descripcion'         => ['nullable', 'string'],
            'rows.*.nro_error'           => ['required', 'string', 'max:255'],
        ]);

        $cobot = $validated['cobot'];

        try {
            $created = DB::transaction(function () use ($validated, $cobot) {
                $created = collect();

                foreach ($validated['rows'] as $row) {
                    $created->push(DefectoCobot::create([
                        'nro_cobot'       => intval($cobot['nro_cobot']),
                        'nombre'          => $cobot['nombre'],
                        'numero_bit'      => intval($row['numero_bit']),
                        'dispositivo_plc' => $row['dispositivo_plc'],
                        'descripcion'     => $row['descripcion'] ?? null,
                        'nro_error'       => $row['nro_error'],
                        'cara_b'          => false,
                        'falla_id'        => null,
                    ]));
                }

                return $created;
            });

            $ids = $created->pluck('id')->values();
            $data = DefectoCobot::with(['falla', 'partes.tipo', 'partes.lado', 'partes.modelo'])
                ->whereIn('id', $ids)
                ->orderBy('numero_bit')
                ->get();

            Partes::attachModeloFallas($data->pluck('partes')->flatten());

            return $this->setResponse($data->toArray(), 'Defectos cobot importados correctamente', false, 201);
        } catch (\Throwable $th) {
            return $this->setResponse([], 'No se pudo importar el Excel: ' . $th->getMessage(), true);
        }
    }

    public function destroy(DefectoCobot $defectoCobot) {
        $defectoCobot->delete();

        return $this->setResponse([], 'Defecto cobot eliminado');
    }

    private function validateRequest(Request $request): array {
        return $request->validate([
            'nro_cobot'       => ['required', 'integer', 'min:1'],
            'nombre'          => ['required', 'string', 'max:255'],
            'numero_bit'      => ['required', 'integer', 'min:0'],
            'dispositivo_plc' => ['required', 'string', 'max:255'],
            'descripcion'     => ['nullable', 'string'],
            'nro_error'       => ['required', 'string', 'max:255'],
            'cara_b'          => ['nullable', 'boolean'],
            'falla_id'        => ['required', 'integer', 'exists:codigo_fallas,id'],
            'parte_ids'       => ['nullable', 'array'],
            'parte_ids.*'     => ['nullable'],
            'parte_puntos'    => ['nullable', 'array'],
            'parte_puntos.*.x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'parte_puntos.*.y' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function resolveParteIds(array $parteValues): array {
        if (empty($parteValues)) {
            return [];
        }

        $ids = [];
        $codigos = [];

        foreach ($parteValues as $value) {
            if (is_numeric($value)) {
                $ids[] = intval($value);
                continue;
            }

            if (!is_null($value) && trim(strval($value)) !== '') {
                $codigos[] = trim(strval($value));
            }
        }

        if (empty($ids) && empty($codigos)) {
            return [];
        }

        $partes = Partes::query()
            ->where(function ($query) use ($ids, $codigos) {
                if (!empty($ids)) {
                    $query->whereIn('id', $ids);
                }

                if (!empty($codigos)) {
                    $query->orWhereIn('codigo', $codigos);
                }
            })
            ->where('activo', 1)
            ->get();

        $resolvedIds = $partes->pluck('id')->unique()->values()->toArray();

        if (count($resolvedIds) === 0 && count($parteValues) > 0) {
            throw ValidationException::withMessages([
                'parte_ids' => ['Las partes seleccionadas no existen.'],
            ]);
        }

        return $resolvedIds;
    }

    private function buildParteSyncData(array $parteIds, array $partePuntos): array {
        $syncData = [];

        foreach ($parteIds as $parteId) {
            $key = strval($parteId);
            $punto = $partePuntos[$key] ?? $partePuntos[$parteId] ?? [];

            $syncData[$parteId] = [
                'x' => isset($punto['x']) ? floatval($punto['x']) : null,
                'y' => isset($punto['y']) ? floatval($punto['y']) : null,
            ];
        }

        return $syncData;
    }

    private function normalizeBits(Request $request): array {
        $raw = $request->bits ?? $request->estado ?? $request->sensores ?? $request->data ?? null;

        if (is_null($raw)) {
            return [];
        }

        if (is_array($raw)) {
            $bits = [];
            foreach ($raw as $key => $value) {
                if (is_array($value)) {
                    $key = $value['numero_bit'] ?? $value['bit'] ?? $value['posicion'] ?? $key;
                    $value = $value['valor'] ?? $value['value'] ?? $value['estado'] ?? $value['bit'] ?? null;
                }

                if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
                    $normalizedKey = $this->normalizeBitKey($key);
                    $bits[$normalizedKey] = strval($value);

                    if (is_numeric($key)) {
                        $bits[intval($key)] = strval($value);
                    }
                }
            }

            return $bits;
        }

        $raw = preg_replace('/[^01]/', '', strval($raw));
        if ($raw === '') {
            return [];
        }

        return str_split($raw);
    }

    private function resolveQrDesdeWords(Request $request): ?string {
        $words = $request->words ?? [];
        $qr = $words['D440-D469'] ?? $words['d440-d469'] ?? null;

        if (is_null($qr)) {
            return null;
        }

        $qr = trim(strval($qr));
        return $qr === '' ? null : $qr;
    }

    private function normalizeBitKey($key): string {
        return strtoupper(str_replace(' ', '', trim(strval($key))));
    }

    private function resolveParteDesdeEtiqueta(EpEtiqueta $etiqueta): ?Partes {
        $codigo = trim(strval($etiqueta->codigo));
        if ($codigo === '') {
            return null;
        }

        $parte = Partes::where('codigo', $codigo)->first();
        if ($parte || !str_ends_with($codigo, '-00')) {
            return $parte;
        }

        return Partes::where('codigo', substr($codigo, 0, -3))->first();
    }

    private function bitsCubrenDefectos(array $bits, $defectos): bool {
        foreach ($defectos as $defecto) {

            if ($this->bitValue($bits, $defecto) === null) {
                return false;
            }
        }

        return true;
    }

    private function bitValue(array $bits, DefectoCobot $defecto): ?string {
        $bitKey = $this->bitKeyForDefecto($defecto);
        if (array_key_exists($bitKey, $bits)) {
            return strval($bits[$bitKey]);
        }

        $numeroBit = intval($defecto->numero_bit);
        return array_key_exists($numeroBit, $bits) ? strval($bits[$numeroBit]) : null;
    }

    private function bitKeyForDefecto(DefectoCobot $defecto): string {
        $dispositivo = $this->normalizeBitKey($defecto->dispositivo_plc);

        if (preg_match('/\.B\d+$/', $dispositivo)) {
            return $dispositivo;
        }

        return $dispositivo . '.B' . intval($defecto->numero_bit);
    }

    private function resolveModeloFallaParaDefecto(Partes $parte, DefectoCobot $defecto): ?object {
        $fallas = collect($parte->modeloFallas ?? []);

        if ($fallas->isEmpty() && $parte->modeloFalla) {
            $fallas = collect([$parte->modeloFalla]);
        }

        if ($fallas->isEmpty()) {
            return null;
        }

        $matchesCara = boolval($defecto->cara_b)
            ? fn($falla) => $this->esImagenCaraB($falla)
            : fn($falla) => $this->esImagenCaraA($falla);

        return $fallas->first($matchesCara) ?? $fallas->first();
    }

    private function esImagenCaraB($modeloFalla): bool {
        $text = $this->normalizeImageText(
            ($modeloFalla->nombre ?? '') . ' ' .
                ($modeloFalla->imagen ?? '') . ' ' .
                ($modeloFalla->orientacion ?? '')
        );

        return str_contains($text, 'facing')
            || str_contains($text, 'trasera')
            || str_contains($text, 'trasero')
            || str_contains($text, 'posterior')
            || str_contains($text, 'rear')
            || str_contains($text, 'back');
    }

    private function esImagenCaraA($modeloFalla): bool {
        $text = $this->normalizeImageText(
            ($modeloFalla->nombre ?? '') . ' ' .
                ($modeloFalla->imagen ?? '') . ' ' .
                ($modeloFalla->orientacion ?? '')
        );

        return str_contains($text, 'delantera')
            || str_contains($text, 'delantero')
            || str_contains($text, 'frontal')
            || str_contains($text, 'front')
            || (!$this->esImagenCaraB($modeloFalla));
    }

    private function normalizeImageText(string $value): string {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return strtolower($normalized ?: $value);
    }

    private function buildCirculoCobot(DefectoCobot $defecto, Partes $parte, object $modeloFalla, Request $request): array {
        $circulo = [
            'cantidad'   => 1,
            'falla'      => $defecto->falla->toArray(),
            'imageId'    => $modeloFalla->id,
            'type'       => $parte->tipo_id,
            'lado'       => $parte->lado_id,
            'x'          => floatval($parte->pivot->x),
            'y'          => floatval($parte->pivot->y),
            'color'      => $request->color ?? 'FF0000',
            'obs'        => trim($defecto->nro_error . ' - ' . ($defecto->descripcion ?? $defecto->nombre)),
            'esCritico'  => $request->esCritico ?? false,
            'puntoEsCentro' => true,
            'origen'     => 'COBOT #' . $defecto->nro_cobot,
        ];

        if ($request->has('screenX')) {
            $circulo['screenX'] = $request->screenX;
        }

        if ($request->has('screenY')) {
            $circulo['screenY'] = $request->screenY;
        }

        return $circulo;
    }

    private function freshDefectoCobotResponse(DefectoCobot $defecto): DefectoCobot {
        $defecto = $defecto->fresh(['falla', 'partes.tipo', 'partes.lado', 'partes.modelo']);
        Partes::attachModeloFallas($defecto->partes);

        return $defecto;
    }
}
