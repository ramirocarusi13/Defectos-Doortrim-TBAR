<?php

namespace App\Http;

use App\Models\Modelos;
use App\Models\Piezas;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PdfKanban {
    private $names = [];
    public $modelo;
    public $pieza;
    public $kanban;
    private $capas = 1;
    private $file;
    private $publicPdfFiles = 'pdf/';
    private $reimpresion = false;

    public function __construct($piezaId, $modelo, $file, $capas = 1) {
        $original_file = $file;
        $file = 'kanban_reposicion/' . $modelo . '/' . $file;

        $this->file = $file;
        $model = Modelos::where('nombre', $modelo)->first();
        $pieza = Piezas::where('id', $piezaId)->first();

        // Log::alert($pieza);
        // $kanban = Kanban::create("R", ['modelo' => $model->id, 'pieza' => $pieza->id, 'capas' => $capas, 'mes' => null]);

        // if (!$kanban) {
        //     return;
        // }

        $this->modelo = $model;
        $this->pieza = $pieza;
        $this->capas = $capas;
    }

    public function setReimpresion($reimpresion) {
        $this->reimpresion = $reimpresion;
    }

    private function addTexts() {
        // $text = 'MODELO : ' . $this->modelo->nombre  . ' | CANTIDAD DE CAPAS : ' . $this->capas;
        $text2 = 'KANBAN REEMPLAZO : ' . $this->kanban->codigo;
        $text3 = 'MODELO : ' . $this->modelo->nombre . ' | ' . 'CAPAS : ' . $this->capas;

        // $this->addText($text);
        $this->addText($text2);
        $this->addText($text3);

        if ($this->reimpresion) {
            $this->addText("- REIMPRESION -");
        }
    }

    private function generateQr() {
        QrCode::format('png')->size(100)->generate($this->kanban->codigo, '../public/qr.png');
    }

    public function setKanban($kanban) {
        $this->kanban = $kanban;
    }

    public function addText($text) {
        // Text font settings 
        $name = uniqid();
        $font_size = 100;
        $opacity = 100;
        $ts = explode("\n", $text);
        $width = 0;

        foreach ($ts as $k => $string) {
            $width = max($width, strlen($string));
        }

        $width  = imagefontwidth($font_size) * $width;
        $height = imagefontheight($font_size) * count($ts);

        $el = imagefontheight($font_size);
        $em = imagefontwidth($font_size);
        $img = imagecreatetruecolor($width, $height);
        // $img = imagerotate($img, 90, 0);

        $bg = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);

        $color = imagecolorallocate($img, 0, 0, 0);
        // $color = imagecolorallocate($img, 0, 0, 0);
        foreach ($ts as $k => $string) {
            $len = strlen($string);
            $ypos = 0;
            for ($i = 0; $i < $len; $i++) {
                $xpos = $i * $em;
                $ypos = $k * $el;
                // imagechar($img, $font_size, $ypos, $xpos, $string, $color);
                imagechar($img, $font_size, $xpos, $ypos, $string, $color);
                $string = substr($string, 1);
            }
        }

        imagecolortransparent($img, $bg);
        $blank = imagecreatetruecolor($width, $height);
        $tbg = imagecolorallocate($blank, 255, 255, 255);
        // $tbg = imagecolorallocate($blank, 255, 0, 0);
        imagefilledrectangle($blank, 0, 0, $width, $height, $tbg);
        imagecolortransparent($blank, $tbg);
        $op = !empty($opacity) ? $opacity : 100;

        if (($op < 0) or ($op > 100)) {
            $op = 100;
        }

        // Create watermark image 
        imagecopymerge($blank, $img, 0, 0, 0, 0, $width, $height, $op);
        imagepng($blank, $name . ".png");

        array_push($this->names, $name);
    }

    public function generate($withOutput = false, $fileNameOutput = 'file.pdf') {
        // Set source PDF file 
        $imageQr = "qr.png";
        $this->addTexts();
        $this->generateQr();

        $pdf = new Fpdi();
        if (file_exists("./" . $this->file)) {
            $pagecount = $pdf->setSourceFile($this->file);
        } else {
            die('Source PDF not found!');
        }

        // Add watermark to PDF pages 
        for ($i = 1; $i <= $pagecount; $i++) {
            $tpl = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);

            try {
                $orientation = $size['orientation'];
            } catch (\Throwable $th) {
                $orientation = 'P';
            }
            // Log::alert($orientation);

            if ($orientation == 'L') {
                $pdf->addPage('P', null, 270);
                $pdf->useTemplate($tpl, 1, 1, 290, null, TRUE);
            } else {
                $pdf->addPage();
                $pdf->useTemplate($tpl, 1, 1, $size['width'], $size['height'], TRUE);
            }

            //Put the watermark 
            if ($orientation == 'P') {
                // $xxx_final = ($size['width'] / 2) - 30;
                // $yyy_final = (10);

                $xxx_final_qr = ($size['width'] / 2) - 50;
                $yyy_final_qr = ($size['height'] / 2);

                $xxx_final_text2 = $xxx_final_qr;
                $yyy_final_text2 = $yyy_final_qr - 7;

                $xxx_final_text3 = $xxx_final_qr + 30;
                $yyy_final_text3 = $yyy_final_qr + 1;

                $xxx_final_text4 = $xxx_final_text3;
                $yyy_final_text4 = $yyy_final_text3 + 7;
            } else {
                // $xxx_final = $size['width']  - 120;
                // $yyy_final = (10);

                $xxx_final_qr = ($size['width'] / 2) - 80;
                $yyy_final_qr = ($size['height'] / 2) - 50;

                $xxx_final_text2 = $xxx_final_qr - 25;
                $yyy_final_text2 = $yyy_final_qr - 12;

                $xxx_final_text3 = $xxx_final_text2;
                $yyy_final_text3 = $yyy_final_text2 + 5;

                $xxx_final_text4 = $xxx_final_text2;
                $yyy_final_text4 = $yyy_final_text2 - 7;
            }

            // $pdf->Image($this->names[0] . '.png', $xxx_final, $yyy_final, 0, 0, 'png');

            //QR Image
            $pdf->Image($imageQr, $xxx_final_qr, $yyy_final_qr, 0, 0, 'png');
            $pdf->Image($this->names[0] . '.png',  $xxx_final_text2, $yyy_final_text2, 0, 0, 'png');
            $pdf->Image($this->names[1] . '.png',  $xxx_final_text3, $yyy_final_text3, 0, 0, 'png');

            if ($this->reimpresion) {
                $pdf->Image($this->names[2] . '.png',  $xxx_final_text4, $yyy_final_text4, 0, 0, 'png');
            }
        }
        @unlink($this->names[0] . '.png');
        @unlink($this->names[1] . '.png');
        // @unlink($this->names[2] . '.png');
        @unlink($imageQr);

        if ($this->reimpresion) {
            @unlink($this->names[2] . '.png');
        }

        // Output PDF with watermark 
        if ($withOutput) {
            $pdf->Output('F', public_path($this->publicPdfFiles . $fileNameOutput));
        } else {
            $pdf->Output();
        }
    }

    static function merge_files(array $files, $unsetFiles = false) {
        // $files = array("a.pdf", "b.pdf", "c.pdf");
        $pageCount = 0;
        $rotate = false;
        // initiate FPDI
        $pdf = new FPDI();

        // iterate through the files
        foreach ($files as $file) {
            // get the page count
            $pageCount = $pdf->setSourceFile($file);
            // iterate through all pages
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // import a page
                $templateId = $pdf->importPage($pageNo);
                // get the size of the imported page
                $size = $pdf->getTemplateSize($templateId);

                // Log::alert($size);

                // create a page (landscape or portrait depending on the imported page size)
                if ($size['width'] > $size['height']) {
                    // $pdf->AddPage('P', array(419.989, 297), -90);
                    $pdf->AddPage('L', array($size['width'], $size['height'] > 209 ? 209 : $size['height']), 270);
                    // $rotate = true;
                } else {
                    // $rotate = false;
                    $pdf->AddPage('P', array($size['width'], $size['height']));
                }

                // use the imported page
                $pdf->useTemplate($templateId);
                // if ($rotate) {
                //     $pdf->Rotate(90);
                // }

                // $pdf->SetFont('Helvetica');
                // $pdf->SetXY(5, 5);
                // $pdf->Write(8, 'Generated by FPDI');

                if ($unsetFiles) {
                    @unlink($file);
                }
            }
        }

        $pdf->Output();
    }
}
