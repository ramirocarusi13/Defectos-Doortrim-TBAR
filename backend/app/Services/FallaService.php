<?php

namespace App\Services;

use App\Http\EstadoFalla;
use App\Models\CodigoFalla;
use App\Models\EpEtiqueta;
use App\Models\FallasInformadas;
use App\Models\LineaOperaciones;
use App\Models\ModeloFalla;
use App\Models\Partes;
use App\Models\Piezas;
use App\Models\ProduccionParada;
use App\Models\Scrap;
use App\Models\User;
use App\Models\VTDefectosInternos;
use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FallaService {

    private function grabaFalla(array $data) {

        try {
            // Log::alert($data);
            $crea = FallasInformadas::create($data);
            return $crea;
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("FallaService::grabaFalla : " . $th->getMessage());
            return false;
        }
    }

    public function creaFallaSinEtiqueta(int $userId, $ladoId, $tipoId, $linea, $modelo) {
        DB::beginTransaction();

        $userCarga = User::where('id', $userId)->first();
        if ($userCarga) {
            if (is_null($userCarga->turno)) {
                $turno = null;
            } else {
                $turno = $userCarga->turno;
            }
        } else {
            $turno = $this->obtenerTurnoPorHorario();
        }

        $falla = CodigoFalla::where('codigo', '41')->first();

        // $ladoId = null;
        // $tipoId = null;

        $data = [
            'qr'                => null,
            'cantidad'          => 1,
            'falla_id'          => $falla->id,
            'image_id'          => null,
            'tipo_id'           => $tipoId,
            'x'                 => null,
            'y'                 => null,
            'color'             => null,
            'lado_id'           => $ladoId,
            'estado'            => EstadoFalla::PENDIENTE,
            'operacion'         => null,
            'turno'             => $turno,
            'user_id'           => $userId,
            'user_autorizante'  => $userId,
            'user_operacion_id' => null,
            'linea'             => intval($linea),
            'tipo_linea'        => 'MAIN',
            'tipo_falla'        => 'E',
            'observaciones'     => $modelo,
            'es_critico'        => false
            // 'modelo'            => $modelo,
        ];

        $creo = $this->grabaFalla($data);

        DB::commit();
        return true;
    }

    public function creaFallaDesdePiezas(array $piezas, int $fallaId, int $operacionId, int $memberId, int $linea, string $tipoLinea, int $userId, bool|null $conScrap = false) {
        DB::beginTransaction();
        $scrapService = new ScrapService;

        $userCarga = User::where('id', $userId)->first();
        if ($userCarga) {
            if (is_null($userCarga->turno)) {
                $turno = null;
            } else {
                $turno = $userCarga->turno;
            }
        } else {
            $turno = $this->obtenerTurnoPorHorario();
        }

        $ladoId = null;
        $tipoId = null;
        $modelo = null;

        $tPieza = Piezas::with(['parte.tipo', 'parte.lado', 'modelo'])->where('id', $piezas[0]['id'])->first();
        if ($tPieza) {
            $ladoId = $tPieza?->parte?->lado_id;
            $tipoId = $tPieza?->parte?->tipo_id;
            $modelo = $tPieza?->modelo?->nombre;
        }

        $data = [
            'qr'                => null,
            'cantidad'          => 1,
            'falla_id'          => $fallaId,
            'image_id'          => null,
            'tipo_id'           => $tipoId,
            'x'                 => null,
            'y'                 => null,
            'color'             => null,
            'lado_id'           => $ladoId,
            'estado'            => ($conScrap || $conScrap == "1" || $conScrap == 1) ? EstadoFalla::SCRAP : EstadoFalla::RETRABAJADO,
            'operacion'         => $operacionId,
            'turno'             => $turno,
            'user_id'           => $userId,
            'user_autorizante'  => $userId,
            'user_operacion_id' => $memberId,
            'linea'             => $linea,
            'tipo_linea'        => $tipoLinea == 'S' ? 'SUB' : 'MAIN',
            'tipo_falla'        => 'I',
            'observaciones'     => $modelo
        ];

        $creo = $this->grabaFalla($data);



        if ($creo && ($conScrap || $conScrap == "1" || $conScrap == 1)) {
            //GENERO EL PEDIDO A TIENDA
            // $tiendaService = new TiendaService();
            // $tiendaService->crearPedido($userId, $fallaId);

            foreach ($piezas as $pieza) {

                if (!$creo) {
                    DB::rollBack();
                    return false;
                } else {
                    try {
                        $scrapService->registraScrapPieza(
                            $userId,
                            $pieza['material_pieza_id'],
                            '',
                            $linea,
                            $tipoLinea,
                            '',
                            $pieza['id'],
                            $creo->id
                        );

                        // $tiendaService->agregaPieza($pieza['id']);
                    } catch (\Throwable $th) {
                        //throw $th;
                        Log::error("FallaService::creaFallaDesdePiezas : " . $th->getMessage());
                        return false;
                    }
                }
            }
        }
        DB::commit();
        return true;
    }

    private function resuelveTurno($userId) {
        $turno = getTurnoActual();

        // $userInspector = User::where('id', $userId)->first();
        // if ($userInspector) {
        //     if (is_null($userInspector->turno)) {
        //         //OBTENGO EL LA ULTIMA FALLA DE ACUERDO AL TURNO ACTUAL
        //         $dataTurno = obtenerTurnoActual();
        //         $anterior = FallasInformadas::whereBetween('created_at', [$dataTurno['INICIO']->format('Y-m-d H:i:s'), $dataTurno['FIN']->format('Y-m-d H:i:s')])
        //             ->where('turno', '!=', null)->first();

        //         if ($anterior) {
        //             $turno = $anterior->turno;
        //         }
        //     } else {
        //         $turno = $userInspector?->turno;
        //     }
        // }



        return $turno;
    }

    public function crearFallaDesdeCirculos(array $circulos, ?int $userId, string $qr, $linea = null) {

        DB::beginTransaction();

        $etiqueta = EtiquetaService::getEtiquetaPorQr($qr);

        $turno = $this->resuelveTurno($userId);

        foreach ($circulos as $circulo) {
            $circulo['estado'] = EstadoFalla::PENDIENTE;
            $circulo['user_id'] = $userId;
            $circulo['turno'] = $turno;
            $circulo['qr'] = $qr;
            $circulo['linea'] = $linea ? $linea : $etiqueta->linea;
            $circulo['falla_id'] = $circulo['falla']['id'];
            $circulo['image_id'] = $circulo['imageId'];
            $circulo['tipo_id'] = $circulo['type'];
            $circulo['lado_id'] = $circulo['lado'];
            $circulo['observaciones'] = $circulo['obs'];
            $circulo['tipo_falla'] = 'E';
            $circulo['es_critico'] = $circulo['esCritico'] ?? false;
            $circulo['imagen_marcada'] = $this->guardaImagenMarcadaEol($circulo);

            // if ($tipoLinea != '') {
            //     $circulo['tipo_linea'] = $tipoLinea;
            // }

            $creo = $this->grabaFalla($circulo);

            if (!$creo) {
                DB::rollBack();
                return false;
            }

            $this->eliminaControlCaraBEtiqueta($etiqueta);
        }

        DB::commit();
        return $creo;
    }

    private function guardaImagenMarcadaEol(array $circulo): ?string {
        if (!function_exists('imagecreatefromstring')) {
            Log::error("FallaService::guardaImagenMarcadaEol : GD no se encuentra disponible.");
            return null;
        }

        $imageId = $circulo['image_id'] ?? $circulo['imageId'] ?? null;
        $modeloFalla = $imageId ? ModeloFalla::where('id', $imageId)->first() : null;
        $imageName = $modeloFalla?->imagen ?? ($circulo['imageName'] ?? null);

        if (!$imageName) {
            return null;
        }

        $sourcePath = public_path('uploads' . DIRECTORY_SEPARATOR . basename($imageName));

        if (!file_exists($sourcePath)) {
            Log::error("FallaService::guardaImagenMarcadaEol : No existe la imagen base " . $sourcePath);
            return null;
        }

        try {
            $source = imagecreatefromstring(file_get_contents($sourcePath));

            if (!$source) {
                return null;
            }

            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $crop = $this->detectaRecorteNoBlanco($source, $sourceWidth, $sourceHeight);
            $canvasWidth = max(1, intval(round($circulo['screenX'] ?? $sourceWidth)));
            $canvasHeight = max(1, intval(round($circulo['screenY'] ?? $sourceHeight)));

            $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, $white);

            $scale = min($canvasWidth / $crop['width'], $canvasHeight / $crop['height']);
            $destWidth = intval(round($crop['width'] * $scale));
            $destHeight = intval(round($crop['height'] * $scale));
            $destX = intval(round(($canvasWidth - $destWidth) / 2));
            $destY = intval(round(($canvasHeight - $destHeight) / 2));

            imagecopyresampled($canvas, $source, $destX, $destY, $crop['x'], $crop['y'], $destWidth, $destHeight, $crop['width'], $crop['height']);

            $this->dibujaPuntoFalla($canvas, $circulo, $canvasWidth, $canvasHeight, [
                'sourceWidth'  => $sourceWidth,
                'sourceHeight' => $sourceHeight,
                'crop'         => $crop,
                'destX'        => $destX,
                'destY'        => $destY,
                'scale'        => $scale,
            ]);

            $directory = public_path('uploads' . DIRECTORY_SEPARATOR . 'eol_defectos');
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $qr = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($circulo['qr'] ?? 'sin_qr'));
            $fileName = 'eol_' . $qr . '_' . date('YmdHis') . '_' . uniqid() . '.png';
            imagepng($canvas, $directory . DIRECTORY_SEPARATOR . $fileName);

            imagedestroy($source);
            imagedestroy($canvas);

            return 'eol_defectos/' . $fileName;
        } catch (\Throwable $th) {
            Log::error("FallaService::guardaImagenMarcadaEol : " . $th->getMessage());
            return null;
        }
    }

    private function detectaRecorteNoBlanco($image, int $width, int $height): array {
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;
        $whiteTolerance = 245;
        $padding = 8;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (!$this->esPixelBlanco($image, $x, $y, $whiteTolerance)) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        if ($maxX < 0 || $maxY < 0) {
            return ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $height];
        }

        $minX = max(0, $minX - $padding);
        $minY = max(0, $minY - $padding);
        $maxX = min($width - 1, $maxX + $padding);
        $maxY = min($height - 1, $maxY + $padding);

        return [
            'x'      => $minX,
            'y'      => $minY,
            'width'  => max(1, $maxX - $minX + 1),
            'height' => max(1, $maxY - $minY + 1),
        ];
    }

    private function esPixelBlanco($image, int $x, int $y, int $whiteTolerance): bool {
        $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
        $alpha = $color['alpha'] ?? 0;

        if ($alpha >= 120) {
            return true;
        }

        return $color['red'] >= $whiteTolerance && $color['green'] >= $whiteTolerance && $color['blue'] >= $whiteTolerance;
    }

    private function dibujaPuntoFalla($canvas, array $circulo, int $canvasWidth, int $canvasHeight, ?array $transform = null): void {
        $markerSize = 60;
        [$centerX, $centerY] = $this->resuelveCentroPuntoFalla($circulo, $canvasWidth, $canvasHeight, $markerSize, $transform);

        [$red, $green, $blue] = $this->hexToRgb((string) ($circulo['color'] ?? 'FF0000'));
        $fill = imagecolorallocate($canvas, $red, $green, $blue);
        $black = imagecolorallocate($canvas, 0, 0, 0);

        imagefilledellipse($canvas, $centerX, $centerY, $markerSize, $markerSize, $fill);
        imageellipse($canvas, $centerX, $centerY, $markerSize, $markerSize, $black);

        if (intval($circulo['cantidad'] ?? 1) > 1) {
            imageellipse($canvas, $centerX, $centerY, $markerSize - 4, $markerSize - 4, $black);
        }

        $codigo = (string) ($circulo['falla']['codigo'] ?? '');
        if ($codigo !== '') {
            $this->dibujaTextoCentradoEscalado($canvas, $codigo, $centerX, $centerY, 1.5);
        }
    }

    private function dibujaTextoCentradoEscalado($canvas, string $text, int $centerX, int $centerY, float $scale): void {
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        $scaledWidth = max(1, intval(round($textWidth * $scale)));
        $scaledHeight = max(1, intval(round($textHeight * $scale)));

        $textImage = imagecreatetruecolor($textWidth, $textHeight);
        $background = imagecolorallocate($textImage, 255, 0, 255);
        imagecolortransparent($textImage, $background);
        imagefilledrectangle($textImage, 0, 0, $textWidth, $textHeight, $background);
        $textColor = imagecolorallocate($textImage, 0, 0, 0);
        imagestring($textImage, $font, 0, 0, $text, $textColor);

        $scaledImage = imagecreatetruecolor($scaledWidth, $scaledHeight);
        $scaledBackground = imagecolorallocate($scaledImage, 255, 0, 255);
        imagecolortransparent($scaledImage, $scaledBackground);
        imagefilledrectangle($scaledImage, 0, 0, $scaledWidth, $scaledHeight, $scaledBackground);
        imagecopyresampled($scaledImage, $textImage, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $textWidth, $textHeight);
        imagecopy($canvas, $scaledImage, $centerX - intval($scaledWidth / 2), $centerY - intval($scaledHeight / 2), 0, 0, $scaledWidth, $scaledHeight);

        imagedestroy($textImage);
        imagedestroy($scaledImage);
    }

    private function resuelveCentroPuntoFalla(array $circulo, int $canvasWidth, int $canvasHeight, int $markerSize, ?array $transform): array {
        $puntoEsCentro = $circulo['puntoEsCentro'] ?? false;
        $offset = $puntoEsCentro ? 0 : ($markerSize / 2);
        $centerX = (($canvasWidth * floatval($circulo['x'] ?? 0)) / 100) + $offset;
        $centerY = (($canvasHeight * floatval($circulo['y'] ?? 0)) / 100) + $offset;

        if (!$transform) {
            return [intval(round($centerX)), intval(round($centerY))];
        }

        $sourceWidth = $transform['sourceWidth'];
        $sourceHeight = $transform['sourceHeight'];
        $originalScale = min($canvasWidth / $sourceWidth, $canvasHeight / $sourceHeight);
        $originalDestX = ($canvasWidth - ($sourceWidth * $originalScale)) / 2;
        $originalDestY = ($canvasHeight - ($sourceHeight * $originalScale)) / 2;

        $sourceX = ($centerX - $originalDestX) / $originalScale;
        $sourceY = ($centerY - $originalDestY) / $originalScale;
        $crop = $transform['crop'];

        if ($sourceX < $crop['x'] || $sourceY < $crop['y'] || $sourceX > ($crop['x'] + $crop['width']) || $sourceY > ($crop['y'] + $crop['height'])) {
            return [intval(round($centerX)), intval(round($centerY))];
        }

        return [
            intval(round($transform['destX'] + (($sourceX - $crop['x']) * $transform['scale']))),
            intval(round($transform['destY'] + (($sourceY - $crop['y']) * $transform['scale']))),
        ];
    }

    private function hexToRgb(string $hex): array {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return [255, 0, 0];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function eliminaControlCaraBEtiqueta($etiqueta) {
        EpEtiqueta::where('id', $etiqueta->id)->update([
            'user_validacion_carab' => null,
            'hora_validacion_carab' => null,
            'kanban_carab'          => null,
        ]);
    }

    private function obtenerTurnoPorHorario() {
        $fecha = new DateTime();

        if ($fecha->format('H') <= 15) {
            return 'TM';
        } else {
            return 'TT';
        }
    }

    function whereInChunked($query, $column, $values, $chunkSize = 2000) {
        return $query->where(function ($q) use ($column, $values, $chunkSize) {
            foreach (array_chunk($values, $chunkSize) as $chunk) {
                $q->orWhereIn($column, $chunk);
            }
        });
    }

    public function filtrar(object $filtros) {
        $linea = $filtros->linea;
        $modelo = $filtros->modelo;
        $falla = $filtros->falla;
        $user = $filtros->member;
        $qr = $filtros->qr;
        $turno = $filtros->turno;
        $tipo = $filtros->tipo;

        // Log::alert($filtros);

        $qr = str_replace("'", "-", $qr);

        try {
            if (count($filtros->fecha) > 0) {
                $fFechaDesde = new DateTime($filtros->fecha[0]);
                $fFechaHasta = new DateTime($filtros->fecha[1]);

                $fFechaHasta->add(new DateInterval('P1D'));

                // $fFechaDesde = DateTime::createFromFormat('Y-m-d H:i:s.u', $filtros->fecha[0]);
            }
        } catch (\Throwable $th) {
            $fFechaDesde = null;
            $fFechaHasta = null;
        }

        // Log::alert($filtros);

        $data = VTDefectosInternos::with([
            'tipo',
            'lado',
            'falla',
            'imagen',
            'scrap'
        ])
            ->when(!empty($falla), function ($q) use ($falla) {
                if (count($falla) > 0) {
                    $q->whereIn('codigo_falla', $falla);
                }
            })
            ->when(!empty($modelo), function ($q) use ($modelo) {
                if (count($modelo) > 0) {
                    $q->whereIn('modelo_id', $modelo);
                }
            })
            ->when(!empty($linea), function ($q) use ($linea) {
                if (count($linea) > 0) {
                    $q->where(function ($k) use ($linea) {
                        $k->whereIn('linea',  $linea);
                        // $k->whereIn('linea_real',  $linea);
                        // $k->orWhere(function ($w) use ($linea) {
                        //     $w->where('linea_real', null);
                        //     $w->whereIn('linea', $linea);
                        // });
                    });
                }
            })
            ->when(!empty($turno), function ($q) use ($turno) {
                if (count($turno) > 0) {
                    $q->whereIn('turno',  $turno);
                }
            })
            ->when(!empty($tipo), function ($q) use ($tipo) {
                if (count($tipo) > 0) {
                    $q->whereIn('tipo_falla', $tipo);
                }
            })
            ->when(!empty($user), function ($q) use ($user) {
                if (count($user) > 0) {
                    $q->whereIn('user_operacion_id', $user);
                }
            })
            ->when(!empty($fFechaDesde), function ($w) use ($fFechaDesde,  $fFechaHasta) {
                $w->where(function ($q) use ($fFechaDesde, $fFechaHasta) {
                    $q->where('created_at', '>=', $fFechaDesde->format('Y-m-d') . ' 06:00:00');
                    $q->where('created_at', '<=', $fFechaHasta->format('Y-m-d') . ' 00:50:00');
                });
            })
            ->when(!empty($qr), function ($q) use ($qr) {
                $q->where('qr', $qr);
            })
            ->orderBy('estado', 'ASC')
            ->orderBy('id', 'ASC');



        $res = $data->get();

        $idsFallas = $res->pluck('id')->filter()->values();

        if ($idsFallas->count() > 0) {
            $imagenesMarcadas = FallasInformadas::whereIn('id', $idsFallas)
                ->pluck('imagen_marcada', 'id');

            $res->each(function ($falla) use ($imagenesMarcadas) {
                $falla->imagen_marcada = $imagenesMarcadas[$falla->id] ?? null;
            });
        }

        // Log::alert($data->toSql());
        // Log::alert(json_encode($res, JSON_PRETTY_PRINT));
        return $res ? $res->toArray() : [];
    }

    public function cancelarDatosRetrabajo(Request $request) {

        $id = $request->id;

        FallasInformadas::where('id', $id)->update([
            'estado'            => 'PENDIENTE',
            'user_retrabajo_id' => null,
            'fecha_retrabajo'   => null,
            'operacion'         => null,
            'user_autorizante'  => null,
            'user_operacion_id' => null
        ]);

        return [];
    }

    public function cambiaDatosRetrabajo(int $id, int $operarioId, string $tipoLinea, int $usuarioAsignaRetrabajo, int $operacion, int $linea = null) {

        try {
            if (is_null($tipoLinea) || $tipoLinea == '') {
                $tipoLinea = 'MAIN';
            }
        } catch (\Throwable $th) {
            $tipoLinea = 'MAIN';
        }

        $dataUpdate = [
            'tipo_linea'        => $tipoLinea,
            'user_operacion_id' => $operarioId,
            'user_autorizante'  => $usuarioAsignaRetrabajo,
            'operacion'         => $operacion,
            'linea'             => $linea ? $linea : null,
        ];

        // $esFallaSinEtiqueta = FallasInformadas::where('id',$id)
        $falla = FallasInformadas::where('id', $id)->first();

        $defectoSinEtiqueta = CodigoFalla::where('codigo', '41')->first();

        if ($defectoSinEtiqueta->id == $falla->falla_id) {
            $dataUpdate['estado'] = EstadoFalla::RETRABAJADO;
            $dataUpdate['user_retrabajo_id'] = $usuarioAsignaRetrabajo;
            $dataUpdate['fecha_retrabajo'] = date('Y-m-d H:i:s');
        }

        if ($dataUpdate['linea'] === null) {
            $dataUpdate['linea'] = $falla->linea;
        }

        FallasInformadas::where('id', $id)->update($dataUpdate);

        EpEtiqueta::where('qr', $falla->qr)->update([
            'user_validacion_carab' => null,
            'hora_validacion_carab' => null,
            'kanban_carab'          => null,
        ]);
    }

    public function cambiaEstadoRetrabajo(int $id, int $userId, string $estado) {
        try {
            // FallasInformadas::where('id', $id)->update([
            //     'estado'            => $estado,
            //     'user_retrabajo_id' => $userId,
            //     'fecha_retrabajo'   => date('Y-m-d H:i:s')
            // ]);

            $falla = FallasInformadas::where('id', $id)->first();

            if ($falla) {
                FallasInformadas::where('id', $id)->update([
                    'estado'            => $estado,
                    'user_retrabajo_id' => $userId,
                    'fecha_retrabajo'   => date('Y-m-d H:i:s'),
                ]);

                EpEtiqueta::where('qr', $falla->qr)->update([
                    'user_validacion_carab' => null,
                    'hora_validacion_carab' => null,
                    'kanban_carab'          => null,
                ]);
            }

            return null;
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("FallasInformadasController::cambiarEstadoRetrabajo : " . $th->getMessage());
            return 'Ocurrió un error al cambiar el estado del retrabajo';
        }
    }

    static function getFallasPendientesScrap() {
        $fallas = FallasInformadas::with(['user', 'operacion', 'operador', 'user_retrabajo', 'user_tl', 'tipo', 'lado', 'falla', 'imagen', 'etiqueta.modelod'])
            ->where('estado', EstadoFalla::SCRAP)->where('user_operacion_id', null)->get();

        return $fallas;
    }

    static function getFallasPendientesSinEtiqueta($linea = null) {

        $falla = CodigoFalla::where('codigo', '41')->first();

        $fallas = FallasInformadas::with(['user', 'operacion', 'operador', 'user_retrabajo', 'user_tl', 'tipo', 'lado', 'falla', 'imagen', 'etiqueta.modelod'])
            ->where('estado', EstadoFalla::PENDIENTE)->where('qr', null)->where('user_operacion_id', null)
            ->where('falla_id', $falla->id)
            ->when(!is_null($linea) && $linea !== '', function ($q) use ($linea) {
                $q->whereExists(function ($query) use ($linea) {
                    $query->select(DB::raw(1))
                        ->from('modelos')
                        ->join('modelo_lineas', 'modelo_lineas.modelo_id', '=', 'modelos.id')
                        ->whereColumn('modelos.nombre', 'fallas_informadas.observaciones')
                        ->where('modelo_lineas.linea_id', intval($linea));
                });
            })
            ->get();

        return $fallas;
    }

    public function getFallasParaAndon(Request $request) {

        // Log::alert($request);
        $fecha = new DateTime(); //'2025-04-01'; //date('Y-m-d');
        if (intval($fecha->format('H')) >= 0 && intval($fecha->format('H')) < 6) {
            //Le resto un dia a la fecha
            $fecha->sub(new DateInterval('P1D'));
            $fechaSiguiente = new DateTime();
        } else {
            $fechaSiguiente = new DateTime();
            $fechaSiguiente->add(new DateInterval('P1D'));
        }

        $turno = 'M';
        $linea = 1;

        if ($request->has('turno')) {
            $turno = $request->turno;
        }

        if ($request->has('linea')) {
            $linea = $request->linea;
        }

        //TOP 5 DE FALLAS
        $topFallas = FallasInformadas::selectRaw('COUNT(*) as cantidad,codigo_fallas.nombre as falla')
            ->leftJoin('codigo_fallas', 'codigo_fallas.id', 'fallas_informadas.falla_id')
            ->where('linea', $linea)->where(function ($q) use ($fecha, $fechaSiguiente, $turno) {
                if ($turno == "M") {
                    $q->where('fallas_informadas.created_at', '>=', $fecha->format('Y-m-d') . ' 06:00:00');
                    $q->where('fallas_informadas.created_at', '<=', $fecha->format('Y-m-d') . ' 15:10:00');
                } else {
                    $q->where('fallas_informadas.created_at', '>=', $fecha->format('Y-m-d') . ' 15:40:00');
                    $q->where('fallas_informadas.created_at', '<=', $fechaSiguiente->format('Y-m-d') . ' 00:50:00');
                }
            })
            ->groupBy('codigo_fallas.nombre')
            ->orderByRaw('COUNT(*) desc')
            ->take(5)
            ->get();

        //TOP 5 OPERACIONES
        $topOperaciones = FallasInformadas::selectRaw("COUNT(*) as cantidad,isnull(linea_operaciones.nombre,'SIN OP') as operacion")
            ->leftJoin('linea_operaciones', 'linea_operaciones.id', 'fallas_informadas.operacion')
            ->where('fallas_informadas.linea', $linea)
            ->where(function ($q) use ($fecha, $fechaSiguiente, $turno) {
                if ($turno == "M") {
                    $q->where('fallas_informadas.created_at', '>=', $fecha->format('Y-m-d') . ' 06:00:00');
                    $q->where('fallas_informadas.created_at', '<=', $fecha->format('Y-m-d') . ' 15:10:00');
                } else {
                    $q->where('fallas_informadas.created_at', '>=', $fecha->format('Y-m-d') . ' 15:40:00');
                    $q->where('fallas_informadas.created_at', '<=', $fechaSiguiente->format('Y-m-d') . ' 00:50:00');
                }
            })
            ->groupBy('linea_operaciones.nombre')
            ->orderByRaw('COUNT(*) desc')
            ->take(5)
            ->get();

        $totalFallas = FallasInformadas::selectRaw('COUNT(*) as cantidad')
            ->where('fallas_informadas.linea', $linea)
            ->where(function ($q) use ($fecha, $fechaSiguiente, $turno) {
                if ($turno == "M") {
                    $q->where('fallas_informadas.created_at', '>=', $fecha->format('Y-m-d') . ' 06:00:00');
                    $q->where('fallas_informadas.created_at', '<=', $fecha->format('Y-m-d') . ' 15:10:00');
                } else {
                    $q->where('fallas_informadas.created_at', '>=', $fecha->format('Y-m-d') . ' 15:40:00');
                    $q->where('fallas_informadas.created_at', '<=', $fechaSiguiente->format('Y-m-d') . ' 00:50:00');
                }
            })
            ->count();

        $response = [
            'topFallas'         => $topFallas ? $topFallas->toArray() : [],
            'topOperaciones'    => $topOperaciones ? $topOperaciones->toArray() : [],
            'totalFallas'       => $totalFallas,
        ];

        return $response;
    }

    public function fallasFMDS(Request $request) {

        $fechaFiltroDesde = null;
        $fechaFiltroHasta = null;
        $fecha = new DateTime();

        if ($request->has('fechaDesde')) {
            if ($request->fechaDesde) {
                $fechaFiltroDesde = DateTime::createFromFormat("d/m/Y", $request->fechaDesde);
            } else {
                $fechaFiltroDesde = (clone $fecha);
            }
        } else {
            $fechaFiltroDesde = (clone $fecha);
        }

        if ($request->has('fechaHasta')) {
            if ($request->fechaHasta) {
                $fechaFiltroHasta = DateTime::createFromFormat("d/m/Y", $request->fechaHasta);
                $fechaFiltroHasta->add(new DateInterval("P1D"));
            } else {
                $fechaFiltroHasta = (clone $fecha);
            }
        } else {
            $fechaFiltroHasta = (clone $fecha);
        }

        if ($fechaFiltroDesde == $fechaFiltroHasta) {
            //A Fecha hasta le sumo 1 dia
            $fechaFiltroHasta->add(new DateInterval("P1D"));
        }

        $fechaManana = new DateTime();
        $fechaManana->add(new DateInterval("P1D"));
        $turno = 'A';
        $linea = 1;

        $fechaInicioMes = (clone $fecha)->modify('first day of this month');
        $fechaFinMes = (clone $fecha)->modify('last day of this month');

        $fechaInicioMesPasado = (clone $fecha)->modify('first day of last month');
        $fechaFinMesPasado = (clone $fecha)->modify('last day of last month');

        if ($request->has('turno')) {
            $turno = $request->turno;
        } else {
            $turno = getTurnoActual();
        }

        if ($request->has('linea')) {
            $linea = $request->linea;
        }

        $membersResponse = [];
        $membersLinea = UserService::obtenerMembersPorLinea(intval($linea), $turno);
        $soloFallasC = function ($q) {
            $q->where('tipo', 'C');
        };

        foreach ($membersLinea as $member) {

            if ($member['departamento'] == 'PRODUCCION' && $member['turno'] == $turno) {
                //DEFECTOS MES ACTUAL
                $member['defectos_mes_actual'] = FallasInformadas::where('user_operacion_id', $member['id'])
                    ->whereHas('falla', $soloFallasC)
                    ->whereBetween('created_at', [$fechaInicioMes->format('Y-m-d H:i:s'), $fechaFinMes->format('Y-m-d H:i:s')])
                    ->get();

                //DEFECTOS MES PASADO
                $member['defectos_mes_pasado'] = FallasInformadas::where('user_operacion_id', $member['id'])
                    ->whereHas('falla', $soloFallasC)
                    ->whereBetween('created_at', [$fechaInicioMesPasado->format('Y-m-d H:i:s'), $fechaFinMesPasado->format('Y-m-d H:i:s')])
                    ->get();

                //TOP DEFECTOS
                $member['defectos_top'] = FallasInformadas::filtroBase($turno, $linea, $fechaFiltroDesde, $fechaFiltroHasta)
                    ->selectRaw("count(*) as cantidad, fallas_informadas.falla_id, codigo_fallas.nombre")
                    ->leftJoin('codigo_fallas', 'fallas_informadas.falla_id', 'codigo_fallas.id')
                    ->where('codigo_fallas.tipo', 'C')
                    ->groupBy('fallas_informadas.falla_id', 'codigo_fallas.nombre')
                    ->where('user_operacion_id', $member['id'])
                    ->orderByDesc('cantidad')
                    ->limit(5)
                    ->get();

                foreach ($member['defectos_top'] as $key => $defecto) {
                    $detalleFallas = FallasInformadas::filtroBase($turno, $linea, $fechaFiltroDesde, $fechaFiltroHasta)
                        ->with(['falla', 'etiqueta.modelod', 'imagen'])
                        ->whereHas('falla', $soloFallasC)
                        ->where('user_operacion_id', $member['id'])
                        ->where('falla_id', $defecto['falla_id'])
                        ->get();

                    $member['defectos_top'][$key]['detalle'] = $detalleFallas;
                }

                array_push($membersResponse, $member);
            }
        }

        $topFallasCritico = FallasInformadas::filtroBase($turno, $linea, $fechaFiltroDesde, $fechaFiltroHasta)
            ->selectRaw("count(*) as cantidad, fallas_informadas.falla_id, codigo_fallas.nombre")
            ->leftJoin('codigo_fallas', 'fallas_informadas.falla_id', 'codigo_fallas.id')
            ->where('codigo_fallas.tipo', 'C')
            ->whereRaw('fallas_informadas.es_critico=1')
            ->whereRaw("fallas_informadas.estado != 'RECHAZADO'")
            ->groupBy('fallas_informadas.falla_id', 'codigo_fallas.nombre')
            ->orderByDesc('cantidad')
            ->get();

        $topFallas = FallasInformadas::filtroBase($turno, $linea, $fechaFiltroDesde, $fechaFiltroHasta)
            ->selectRaw("count(*) as cantidad, fallas_informadas.falla_id, codigo_fallas.nombre")
            ->leftJoin('codigo_fallas', 'fallas_informadas.falla_id', 'codigo_fallas.id')
            ->where('codigo_fallas.tipo', 'C')
            ->whereRaw('fallas_informadas.es_critico=0')
            ->whereRaw("fallas_informadas.estado != 'RECHAZADO'")
            ->groupBy('fallas_informadas.falla_id', 'codigo_fallas.nombre')
            ->orderByDesc('cantidad')
            ->limit(7)
            ->get();

        $topLados = FallasInformadas::filtroBase($turno, $linea, $fechaFiltroDesde, $fechaFiltroHasta)
            ->with('lado')
            ->selectRaw("count(*) as cantidad, lado_partes.lado + ' - ' + tipo_partes.tipo as nombre")
            ->leftJoin('lado_partes', 'fallas_informadas.lado_id', 'lado_partes.id')
            ->leftJoin('tipo_partes', 'fallas_informadas.tipo_id', 'tipo_partes.id')
            ->whereHas('falla', $soloFallasC)
            ->whereRaw("fallas_informadas.estado != 'RECHAZADO'")
            ->groupBy('lado_partes.lado', 'tipo_partes.tipo')
            ->orderByDesc('cantidad')
            ->get();

        $topUsers = FallasInformadas::filtroBase($turno, $linea, $fechaFiltroDesde, $fechaFiltroHasta)
            ->selectRaw("count(*) as cantidad, fallas_informadas.user_operacion_id, users.email as nombre")
            ->whereHas('falla', $soloFallasC)
            ->whereNotNull('fallas_informadas.user_operacion_id')
            ->whereRaw("fallas_informadas.estado != 'RECHAZADO'")
            ->groupBy('fallas_informadas.user_operacion_id', 'users.email')
            ->orderByDesc('cantidad')
            ->limit(7)
            ->get();

        $topOperaciones = FallasInformadas::filtroBase($turno, $linea, $fechaFiltroDesde, $fechaFiltroHasta)
            ->selectRaw("count(*) as cantidad, fallas_informadas.user_operacion_id, linea_operaciones.nombre")
            ->leftJoin('linea_operaciones', 'fallas_informadas.operacion', 'linea_operaciones.id')
            ->whereHas('falla', $soloFallasC)
            ->whereRaw("fallas_informadas.estado != 'RECHAZADO'")
            ->groupBy('fallas_informadas.user_operacion_id', 'linea_operaciones.nombre')
            ->orderByDesc('cantidad')
            ->get();

        $operaciones = LineaOperaciones::where('linea', $linea)->get();
        $operacionesResponse = [];

        foreach ($operaciones as $operacion) {

            //DEFECTOS MES ACTUAL
            $operacion['defectos_mes_actual'] = FallasInformadas::where('operacion', $operacion['id'])
                ->whereHas('falla', $soloFallasC)
                ->whereBetween('created_at', [$fechaInicioMes->format('Y-m-d H:i:s'), $fechaFinMes->format('Y-m-d H:i:s')])
                ->whereRaw("estado != 'RECHAZADO'")
                ->get();

            //DEFECTOS MES PASADO
            $operacion['defectos_mes_pasado'] = FallasInformadas::where('operacion', $operacion['id'])
                ->whereHas('falla', $soloFallasC)
                ->whereBetween('created_at', [$fechaInicioMesPasado->format('Y-m-d H:i:s'), $fechaFinMesPasado->format('Y-m-d H:i:s')])
                ->whereRaw("fallas_informadas.estado != 'RECHAZADO'")
                ->get();

            //TOP DEFECTOS
            $operacion['defectos_top'] = FallasInformadas::filtroBase($turno, $linea, $fechaFiltroDesde, $fechaFiltroHasta)
                ->selectRaw("count(*) as cantidad, fallas_informadas.falla_id, codigo_fallas.nombre")
                ->leftJoin('codigo_fallas', 'fallas_informadas.falla_id', 'codigo_fallas.id')
                ->where('codigo_fallas.tipo', 'C')
                ->groupBy('fallas_informadas.falla_id', 'codigo_fallas.nombre')
                ->where('operacion', $operacion['id'])
                ->whereRaw("fallas_informadas.estado != 'RECHAZADO'")
                ->orderByDesc('cantidad')
                ->limit(5)
                ->get();

            array_push($operacionesResponse, $operacion);
        }

        $fallas = FallasInformadas::with(['operador', 'falla', 'lado', 'tipo', 'etiqueta', 'scrap.material'])
            ->whereHas('falla', $soloFallasC)
            ->where('turno', $turno)
            ->where('linea', $linea)
            ->whereRaw("estado != 'RECHAZADO'")
            ->where(function ($q) use ($fechaFiltroDesde, $fechaFiltroHasta) {
                $q->where('created_at', '>=', $fechaFiltroDesde->format('Y-m-d') . ' 06:00:00');
                $q->where('created_at', '<=', $fechaFiltroHasta->format('Y-m-d') . ' 01:00:00');
            })
            ->get();

        $historialMes = FallasInformadas::selectRaw('count(*) as cantidad, month(created_at) as mes, year(created_at) as anio')
            ->whereHas('falla', $soloFallasC)
            ->where('turno', $turno)
            ->where('linea', $linea)
            ->where('tipo_falla', 'E')
            ->whereRaw("estado != 'RECHAZADO'")
            ->groupByRaw('month(created_at)')
            ->groupByRaw('year(created_at)')
            ->orderByRaw('month(created_at)')
            ->get();

        $fechaInicioMesesAnteriores = clone $fechaInicioMes;
        $fechaInicioMesesAnteriores->sub(new DateInterval("P3M"));

        $historialMesDiario = [];

        $intervalo = new DateInterval('P1D'); // Periodo de 1 día
        $periodo   = new DatePeriod($fechaInicioMesesAnteriores, $intervalo, $fechaFinMes);

        foreach ($periodo as $fecha) {
            $diaBuscado = $fecha->format('d');
            $mesBuscado = $fecha->format('m');
            $fechaSiguiente = (clone $fecha)->modify('+1 day');
            $cantidadASumar = 1;

            $encontrado = false;

            $cantidadASumar = FallasInformadas::selectRaw('count(*) as cantidad')
                ->whereHas('falla', $soloFallasC)
                ->where('turno', $turno)
                ->where('linea', $linea)
                ->where('tipo_falla', 'E')
                ->whereRaw("estado != 'RECHAZADO'")
                ->whereBetween('created_at', [$fecha->format('Y-m-d') . ' 06:00:00', $fechaSiguiente->format('Y-m-d') . ' 00:50:00'])
                ->first();

            foreach ($historialMesDiario as &$item) {
                if ($item["dia"] === $diaBuscado && $item["mes"] === $mesBuscado) {
                    $item["cantidad"] += $cantidadASumar?->cantidad;
                    $encontrado = true;
                    break;
                }
            }
            unset($item);

            if (!$encontrado) {
                if ($cantidadASumar?->cantidad > 0) {
                    $historialMesDiario[] = [
                        "dia"       => $diaBuscado,
                        "mes"       => $mesBuscado,
                        "cantidad"  => $cantidadASumar?->cantidad,
                    ];
                }
            }
        }

        $historialMesComparacion = FallasInformadas::selectRaw('count(*) as cantidad')
            ->whereHas('falla', $soloFallasC)
            ->where('turno', $turno)
            ->where('linea', $linea)
            ->whereRaw("estado != 'RECHAZADO'")
            ->where(function ($q) use ($fecha) {
                $inicio = (clone $fecha)->modify('first day of last month');
                $fin = (clone $fecha)->modify('-1 month');

                $q->where('fallas_informadas.created_at', '>=', $inicio->format('Y-m-d') . ' 06:00:00');
                $q->where('fallas_informadas.created_at', '<=', $fin->format('Y-m-d') . ' 01:00:00');
            })
            ->get();

        $finalResponse = [];

        //PIEZAS SCRAP
        $scrap = Scrap::leftJoin('codigo_fallas', 'scraps.falla_id', 'codigo_fallas.id')
            ->where('scraps.linea_id', $linea)
            ->whereBetween('scraps.created_at', [$fechaFiltroDesde->format('Y-m-d') . ' 06:00:00', $fechaFiltroHasta->format('Y-m-d') . ' 01:00:00'])
            ->where('scraps.turno', $turno)
            ->where('codigo_fallas.tipo', 'C')
            ->count();

        array_push(
            $finalResponse,
            [
                'fallas'            => $fallas ? $fallas->toArray() : [],
                'topUsers'          => $topUsers,
                'topFallas'         => $topFallas,
                'topFallasCritico'  => $topFallasCritico,
                'topOperaciones'    => $topOperaciones,
                'historial'         => $historialMes,
                'historialDiario'   => $historialMesDiario,
                'scrap'             => $scrap,
                'mesAnterior'       => $historialMesComparacion ? $historialMesComparacion[0] : 0,
                // 'topModelos'        => $topModelos ? $topModelos->toArray() : [],
                'topLados'          => $topLados,
                'turno'             => $turno,
                'members'           => $membersResponse,
                'operaciones'       => $operacionesResponse,
                // 'historialMeses'    => $historialMesesAnteriores
            ]
        );

        return $finalResponse[0];
    }

    public function fallasFMDS2(Request $request) {

        $fecha = new DateTime();
        $fechaManana = new DateTime();
        $fechaManana->add(new DateInterval("P1D"));
        $turno = 'A';
        $linea = 1;

        if ($request->has('turno')) {
            $turno = $request->turno;
        }

        if ($request->has('linea')) {
            $linea = $request->linea;
        }

        $response = [
            'fallas'        => [],
            'operaciones'   => []
        ];

        $response = [];

        $lastId = 0;

        $fallas = CodigoFalla::orderBy('codigo')->get();

        $topFallas  = FallasInformadas::selectRaw("count(*) as cantidad, fallas_informadas.falla_id,codigo_fallas.nombre")
            ->leftJoin('codigo_fallas', 'fallas_informadas.falla_id', 'codigo_fallas.id')
            ->leftJoin('users', 'fallas_informadas.user_operacion_id', 'users.id')
            ->where('fallas_informadas.user_operacion_id', '!=', null)
            ->where('users.turno', $turno)
            ->where('linea', $linea)
            ->where(function ($q) use ($fecha, $fechaManana) {
                $q->where('fallas_informadas.created_at', '>=', $fecha->format('Y-m-d') . ' 06:00:00');
                $q->where('fallas_informadas.created_at', '<=', $fechaManana->format('Y-m-d') . ' 01:00:00');
            })
            ->groupBy('fallas_informadas.user_operacion_id', 'fallas_informadas.falla_id', 'codigo_fallas.nombre')
            ->orderByRaw('count(*) desc')
            ->get()->take(5);

        $topUsers = FallasInformadas::selectRaw("count(*) as cantidad, fallas_informadas.user_operacion_id,users.email as nombre")
            ->leftJoin('codigo_fallas', 'fallas_informadas.falla_id', 'codigo_fallas.id')
            ->leftJoin('users', 'fallas_informadas.user_operacion_id', 'users.id')
            ->where('fallas_informadas.user_operacion_id', '!=', null)
            ->where('users.turno', $turno)
            ->where('linea', $linea)
            ->where(function ($q) use ($fecha, $fechaManana) {
                $q->where('fallas_informadas.created_at', '>=', $fecha->format('Y-m-d') . ' 06:00:00');
                $q->where('fallas_informadas.created_at', '<=', $fechaManana->format('Y-m-d') . ' 01:00:00');
            })
            ->groupBy('fallas_informadas.user_operacion_id', 'users.email')
            ->orderByRaw('count(*) desc')
            ->get()->take(5);

        $topOperaciones = FallasInformadas::selectRaw("count(*) as cantidad, fallas_informadas.user_operacion_id,linea_operaciones.nombre")
            ->leftJoin('linea_operaciones', 'fallas_informadas.operacion', 'linea_operaciones.id')
            ->leftJoin('users', 'fallas_informadas.user_operacion_id', 'users.id')
            ->where('fallas_informadas.user_operacion_id', '!=', null)
            ->where('users.turno', $turno)
            ->where('fallas_informadas.linea', $linea)
            ->where(function ($q) use ($fecha, $fechaManana) {
                $q->where('fallas_informadas.created_at', '>=', $fecha->format('Y-m-d') . ' 06:00:00');
                $q->where('fallas_informadas.created_at', '<=', $fechaManana->format('Y-m-d') . ' 01:00:00');
            })
            ->groupBy('fallas_informadas.user_operacion_id', 'linea_operaciones.nombre')
            ->orderByRaw('count(*) desc')
            ->get()->take(5);

        foreach ($fallas as $falla) {
            $informe = FallasInformadas::selectRaw("count(*) as cantidad, fallas_informadas.falla_id, fallas_informadas.user_operacion_id")
                ->leftJoin('codigo_fallas', 'fallas_informadas.falla_id', 'codigo_fallas.id')
                ->leftJoin('users', 'fallas_informadas.user_operacion_id', 'users.id')
                ->where('fallas_informadas.user_operacion_id', '!=', null)
                ->where('falla_id', $falla->id)
                ->where('users.turno', $turno)
                ->where('linea', $linea)
                ->where(function ($q) use ($fecha, $fechaManana) {
                    $q->where('fallas_informadas.created_at', '>=', $fecha->format('Y-m-d') . ' 06:00:00');
                    $q->where('fallas_informadas.created_at', '<=', $fechaManana->format('Y-m-d') . ' 01:00:00');
                })
                ->groupBy('fallas_informadas.user_operacion_id', 'fallas_informadas.falla_id')
                ->get();

            if ($informe) {
                $temp = [];
                foreach ($informe as $inf) {
                    $temp['user_' . $inf->user_operacion_id] = $inf->cantidad;
                }

                $temp['orden'] = $falla->id;
                $temp['tipo'] = "U";
                $temp['falla_id'] = $falla->id;
                $temp['falla'] = $falla->codigo . ' - ' . $falla->nombre;

                array_push($response, $temp);

                if (intval($falla->id) > $lastId) {
                    $lastId = intval($falla->id);
                }
            }
        }

        array_push($response, [
            'orden'     => $lastId + 1,
            'falla_id'  => $lastId + 1,
            'falla'     => 'OPERACIONES'
        ]);

        $lastId++;
        //OPERACIONES

        $operaciones = LineaOperaciones::where('linea', $linea)->orderBy('id')->get();

        // Log::alert($operaciones);

        foreach ($operaciones as $operacion) {
            $informe = FallasInformadas::selectRaw("count(*) as cantidad, fallas_informadas.operacion, fallas_informadas.user_operacion_id, fallas_informadas.tipo_linea")
                ->leftJoin('users', 'fallas_informadas.user_operacion_id', 'users.id')
                ->where('fallas_informadas.user_operacion_id', '!=', null)
                ->where('operacion', $operacion->id)
                ->where('users.turno', $turno)
                ->where('linea', $linea)
                ->where(function ($q) use ($fecha, $fechaManana) {
                    $q->where('fallas_informadas.created_at', '>=', $fecha->format('Y-m-d') . ' 06:00:00');
                    $q->where('fallas_informadas.created_at', '<=', $fechaManana->format('Y-m-d') . ' 01:00:00');
                })
                ->groupBy('fallas_informadas.user_operacion_id', 'fallas_informadas.operacion', 'fallas_informadas.tipo_linea')
                ->get();


            if ($informe) {
                $temp = [];
                foreach ($informe as $inf) {
                    $temp['user_' . $inf->user_operacion_id] = $inf->cantidad;
                }

                $temp['tipo'] = "O";
                $temp['orden'] = $lastId + 1;
                $temp['falla_id'] = $lastId + 1; // $operacion->id;
                $temp['falla'] = ($operacion->sublinea == "1" ? "S" : "M") . $operacion->linea . " - " . $operacion->nombre;

                array_push($response, $temp);
            }
            $lastId++;
        }

        $finalResponse = [];
        array_push(
            $finalResponse,
            [
                'fallas'            => $response,
                'topUsers'          => $topUsers,
                'topFallas'         => $topFallas,
                'topOperaciones'    => $topOperaciones
            ]
        );

        return $finalResponse[0];
    }
}
