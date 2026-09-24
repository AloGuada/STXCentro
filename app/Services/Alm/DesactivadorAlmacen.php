<?php

namespace App\Services\Alm;

use App\Enums\Alm\TransferenciaEstatus;
use App\Models\Alm\Almacen;
use App\Models\Alm\Prestamo;
use App\Models\Alm\Transferencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Apaga y prende un almacén.
 *
 * Un almacén con historia no se borra —el kardex habla de él—, se desactiva:
 * deja de salir en los selectores y en el reporte de existencias, y el kardex
 * se conserva.
 *
 * Apagar se niega mientras el almacén todavía tenga algo a su cargo: saldo,
 * resguardos abiertos o transferencias en camino desde o hacia él. Un almacén
 * inactivo con material sería inventario que nadie ve ni puede mover; primero
 * se vacía, se recibe lo prestado y se confirma lo que viaja.
 */
class DesactivadorAlmacen
{
    /** Devuelve el mensaje para la pantalla. */
    public function alternar(Almacen $almacen): string
    {
        return $almacen->activo ? $this->desactivar($almacen) : $this->reactivar($almacen);
    }

    public function desactivar(Almacen $almacen): string
    {
        $bloqueos = $this->bloqueos($almacen);

        if ($bloqueos['articulos_con_saldo'] > 0) {
            throw ValidationException::withMessages([
                'activo' => "No se puede desactivar: todavía hay {$bloqueos['articulos_con_saldo']} artículo(s) con saldo. Transfiérelos o ajústalos primero.",
            ]);
        }

        if ($bloqueos['prestamos_abiertos'] !== []) {
            throw ValidationException::withMessages([
                'activo' => 'No se puede desactivar: hay resguardos abiertos ('.implode(', ', $bloqueos['prestamos_abiertos']).'). Recibe lo prestado primero.',
            ]);
        }

        if ($bloqueos['transferencias_en_transito'] !== []) {
            throw ValidationException::withMessages([
                'activo' => 'No se puede desactivar: hay transferencias en tránsito ('.implode(', ', $bloqueos['transferencias_en_transito']).'). Confírmalas primero.',
            ]);
        }

        $almacen->update(['activo' => false]);

        return "{$almacen->clave} desactivado. Su kardex se conserva, pero ya no aparece para operar.";
    }

    public function reactivar(Almacen $almacen): string
    {
        $almacen->update(['activo' => true]);

        return "{$almacen->clave} reactivado.";
    }

    /**
     * Lo que impide apagarlo, para que la pantalla lo explique antes del viaje.
     *
     * @return array{articulos_con_saldo: int, prestamos_abiertos: list<string>, transferencias_en_transito: list<string>}
     */
    public function bloqueos(Almacen $almacen): array
    {
        return [
            'articulos_con_saldo' => $almacen->existencias()->conSaldo()->count(),
            'prestamos_abiertos' => Prestamo::query()
                ->abiertos()
                ->where('almacen_id', $almacen->id)
                ->orderBy('id')
                ->get(['id', 'folio'])
                ->map(fn (Prestamo $p): string => $p->folio ?? "#{$p->id}")
                ->all(),
            'transferencias_en_transito' => Transferencia::query()
                ->where('estatus', TransferenciaEstatus::EnTransito)
                ->where(fn (Builder $q) => $q->where('almacen_origen_id', $almacen->id)->orWhere('almacen_destino_id', $almacen->id))
                ->orderBy('id')
                ->get(['id', 'folio'])
                ->map(fn (Transferencia $t): string => $t->folio ?? "#{$t->id}")
                ->all(),
        ];
    }
}
