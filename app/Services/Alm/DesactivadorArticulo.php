<?php

namespace App\Services\Alm;

use App\Enums\Alm\ActivoEstatus;
use App\Models\Alm\Articulo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Apaga y prende un artículo del catálogo.
 *
 * Un artículo es una cara del item del maestro y el producto de Compras es la
 * otra: se apagan y se prenden juntas, porque un producto que se sigue
 * cotizando para un artículo que ya no existe sería material que se compra y
 * no se puede recibir.
 *
 * Apagar se niega mientras haya saldo en algún almacén o piezas afuera en
 * resguardo: un artículo con existencia no desaparece, primero se ajusta o se
 * retira. Nunca se borra nada: el kardex y los documentos donde aparece se
 * conservan; lo inactivo sólo deja de salir en los buscadores, en el sorteo de
 * conteos y en lo prestable.
 */
class DesactivadorArticulo
{
    /** Devuelve el mensaje para la pantalla. */
    public function alternar(Articulo $articulo): string
    {
        return $articulo->activo ? $this->desactivar($articulo) : $this->reactivar($articulo);
    }

    public function desactivar(Articulo $articulo): string
    {
        $saldo = (float) $articulo->existencias()->sum('cantidad');

        if (abs($saldo) > (float) config('costos.epsilon_cantidad')) {
            throw ValidationException::withMessages([
                'activo' => "No se puede desactivar: todavía hay {$saldo} {$articulo->unidad} en existencia. Ajusta o retira el saldo primero.",
            ]);
        }

        $afuera = $articulo->piezas()->where('estatus', ActivoEstatus::Prestado)->count();

        if ($afuera > 0) {
            throw ValidationException::withMessages([
                'activo' => "No se puede desactivar: hay {$afuera} pieza(s) afuera en resguardo. Recíbelas primero.",
            ]);
        }

        DB::transaction(function () use ($articulo): void {
            $articulo->update(['activo' => false]);
            $articulo->producto?->update(['activo' => false]);
        });

        return "{$articulo->codigo} desactivado. Sigue en el kardex, pero ya no se compra, se cuenta ni se presta.";
    }

    public function reactivar(Articulo $articulo): string
    {
        DB::transaction(function () use ($articulo): void {
            $articulo->update(['activo' => true]);
            $articulo->producto?->update(['activo' => true]);
        });

        return "{$articulo->codigo} reactivado.";
    }
}
