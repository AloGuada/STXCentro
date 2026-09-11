<?php

namespace App\Services\Qal\Dosier;

/**
 * El motor que une los PDF del dosier en uno solo.
 *
 * Es una interfaz a propósito: el motor definitivo está por decidirse. El que
 * trae el proyecto (FPDI gratuito) no abre PDF 1.5+ con object streams, que es
 * como salen muchos escaneos y exportaciones; cuando se elija otro (pdf-lib,
 * qpdf, FPDI comercial) sólo cambia la implementación que se enlaza en
 * AppServiceProvider.
 */
interface UnidorDePdf
{
    /** Cuántas hojas tiene el PDF, o null si este motor no lo puede abrir. */
    public function paginas(string $ruta): ?int;

    /**
     * Une los PDF en ese orden y escribe el resultado en `$destino`.
     *
     * @param  list<string>  $rutas
     */
    public function unir(array $rutas, string $destino): void;
}
