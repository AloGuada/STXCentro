<?php

namespace App\Services\Portal;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Estado del alta de factura en dos pasos: entre que el proveedor sube el CFDI y
 * lo confirma, los archivos viven en `tmp_facturas/` y los datos leídos del XML
 * en la sesión.
 *
 * Vive fuera del controlador de facturas porque el tablero también lo lee para
 * pintar el paso 2 como modal, y la limpieza de temporales debe ser la misma en
 * los dos caminos.
 */
class PreviewFacturaSesion
{
    private const KEY = 'portal.factura.preview';

    /** Pantalla desde la que se inició el alta, para saber a dónde volver. */
    public const ORIGEN_TABLERO = 'tablero';

    public const ORIGEN_CLASICO = 'clasico';

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardar(array $datos): void
    {
        session()->put(self::KEY, $datos);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtener(): ?array
    {
        return session(self::KEY);
    }

    public function origen(): string
    {
        return $this->obtener()['origen'] ?? self::ORIGEN_CLASICO;
    }

    public function vieneDelTablero(): bool
    {
        return $this->origen() === self::ORIGEN_TABLERO;
    }

    /**
     * Borra los temporales y el estado. Se llama tanto al confirmar como al
     * cancelar: si no, cada CFDI abandonado deja archivos en el disco.
     */
    public function limpiar(): void
    {
        $preview = $this->obtener();
        if (! $preview) {
            return;
        }

        $disk = Storage::disk('public');

        foreach (['xml_path', 'pdf_path'] as $key) {
            if (! empty($preview[$key]) && $disk->exists($preview[$key])) {
                $disk->delete($preview[$key]);
            }
        }

        if (! empty($preview['token']) && ($proveedor = Auth::guard('proveedor')->user())) {
            $dir = "tmp_facturas/{$proveedor->id}/{$preview['token']}";
            if ($disk->exists($dir) && empty($disk->files($dir))) {
                $disk->deleteDirectory($dir);
            }
        }

        session()->forget(self::KEY);
    }
}
