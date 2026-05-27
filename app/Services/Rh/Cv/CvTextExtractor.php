<?php

namespace App\Services\Rh\Cv;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class CvTextExtractor
{
    public function extract(string $pdfAbsolutePath): string
    {
        if (! file_exists($pdfAbsolutePath)) {
            throw new RuntimeException("Archivo CV no existe: {$pdfAbsolutePath}");
        }
        if (! is_readable($pdfAbsolutePath)) {
            throw new RuntimeException("Archivo CV no es legible: {$pdfAbsolutePath}");
        }

        $directo = $this->extraerDirecto($pdfAbsolutePath);
        if ($directo !== null && $this->esBuenaCalidad($directo)) {
            return $directo;
        }

        return $this->extraerConOcr($pdfAbsolutePath);
    }

    private function extraerDirecto(string $pdfPath): ?string
    {
        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($pdfPath);

            return $this->limpiarUtf8(trim($pdf->getText()));
        } catch (\Throwable $e) {
            Log::info('Extracción PDF directa falló, se intentará OCR', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function esBuenaCalidad(string $texto): bool
    {
        if (strlen($texto) < 100) {
            return false;
        }
        if (str_word_count($texto, 0, 'áéíóúñÁÉÍÓÚÑ') < 20) {
            return false;
        }
        $validos = preg_match_all('/[a-zA-Z0-9áéíóúñÁÉÍÓÚÑ\s]/u', $texto);

        return ($validos / strlen($texto)) >= 0.7;
    }

    private function extraerConOcr(string $pdfPath): string
    {
        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $outputPrefix = $tempDir.DIRECTORY_SEPARATOR.uniqid('cv_', true);
        $cmd = sprintf('pdftoppm -jpeg -r 300 -jpegopt quality=95 %s %s 2>&1', escapeshellarg($pdfPath), escapeshellarg($outputPrefix));
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException('No se pudo convertir PDF a imágenes (¿poppler instalado?): '.implode(' ', $output));
        }

        $images = glob($outputPrefix.'-*.jpg') ?: [];
        if ($images === []) {
            throw new RuntimeException('No se generaron imágenes del PDF');
        }

        $textoCompleto = '';
        try {
            foreach ($images as $image) {
                try {
                    $pageText = (new TesseractOCR($image))->lang('spa', 'eng')->psm(3)->oem(3)->run();
                    $pageText = trim($pageText);
                    if ($pageText !== '') {
                        $textoCompleto .= $pageText."\n\n";
                    }
                } catch (\Throwable $e) {
                    Log::warning('OCR falló en página', ['image' => $image, 'error' => $e->getMessage()]);
                }
            }
        } finally {
            foreach ($images as $image) {
                @unlink($image);
            }
        }

        $textoCompleto = $this->limpiarUtf8(trim($textoCompleto));
        if (strlen($textoCompleto) < 50) {
            throw new RuntimeException('OCR devolvió texto demasiado corto ('.strlen($textoCompleto).' chars). CV probablemente ilegible.');
        }

        return $textoCompleto;
    }

    private function limpiarUtf8(string $texto): string
    {
        $encoding = mb_detect_encoding($texto, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $texto = (string) mb_convert_encoding($texto, 'UTF-8', $encoding);
        }
        $texto = (string) mb_convert_encoding($texto, 'UTF-8', 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $texto = (string) \Normalizer::normalize($texto, \Normalizer::FORM_C);
        }
        $texto = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto);
        $texto = (string) preg_replace('/\s+/u', ' ', $texto);

        return trim($texto);
    }
}
