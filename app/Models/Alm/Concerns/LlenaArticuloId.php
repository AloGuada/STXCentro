<?php

namespace App\Models\Alm\Concerns;

use App\Services\Alm\ResolvedorArticulo;
use Illuminate\Database\Eloquent\Model;

/**
 * Mientras `producto_id` y `articulo_id` convivan, llena la columna nueva sola.
 *
 * Va como hook del modelo y no parchando cada escritura a propósito. Hay siete
 * lugares que insertan en estas tablas hoy, pero el riesgo no son los siete que
 * se pueden encontrar: es el octavo, el que alguien escriba la semana entrante
 * sin saber que había que llenar dos columnas. Un renglón así sale con
 * `articulo_id` nulo, la fase B no puede cerrarse y nadie sabe por qué.
 *
 * Aquí sí cabe el hook, aunque {@see \App\Services\Alm\RegistradorEntradaAlmacen}
 * lo haya descartado para lo suyo: aquello era disparar un efecto —mover el
 * kardex— y esto es rellenar una columna espejo con lo que el propio renglón ya
 * trae. No decide nada, no puede correr de más, y es idempotente.
 *
 * **Se borra en la fase B**, junto con `producto_id`. Es andamio, no diseño.
 */
trait LlenaArticuloId
{
    protected static function bootLlenaArticuloId(): void
    {
        static::saving(function (Model $modelo): void {
            if ($modelo->producto_id === null || $modelo->articulo_id !== null) {
                return;
            }

            $modelo->articulo_id = app(ResolvedorArticulo::class)->paraProducto((int) $modelo->producto_id);
        });
    }
}
