<?php

use App\Models\Obra;
use App\Models\Proyecto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las obras creadas a mano como adicionales (nombradas `{no del padre} AD{n}`,
 * ej. `S2502-05 AD3`) reciben su propio proyecto en el backfill 1:1. Esta
 * migración las reacomoda como adicionales dentro del proyecto de su obra padre
 * y elimina el proyecto huérfano que se les había creado.
 *
 * El otro tipo de adicional (partidas `es_adicional`) ya lo acomoda
 * `convert_adicionales_to_subobras`, así que aquí solo se tratan las obras `AD#`.
 *
 * Idempotente: salta las que ya son `tipo='adicional'`. One-way: down() no-op.
 */
return new class extends Migration
{
    /** Sufijo de adicional al final del `no`: " AD3", "-AD3", "ad12"… */
    private const PATRON_ADICIONAL = '/\s*-?\s*AD\s*\d+$/i';

    public function up(): void
    {
        Obra::query()
            ->sinPlanta()
            ->where('tipo', '!=', 'adicional')
            ->where('no', 'like', '%AD%')
            ->orderBy('id')
            ->get(['id', 'no', 'proyecto_id'])
            ->each(function (Obra $adicional): void {
                if (! preg_match(self::PATRON_ADICIONAL, $adicional->no)) {
                    return;
                }

                $noPadre = trim(preg_replace(self::PATRON_ADICIONAL, '', $adicional->no));
                if ($noPadre === '' || $noPadre === $adicional->no) {
                    return;
                }

                $padre = Obra::query()
                    ->sinPlanta()
                    ->where('no', $noPadre)
                    ->whereKeyNot($adicional->id)
                    ->whereNotNull('proyecto_id')
                    ->first(['id', 'proyecto_id']);

                if ($padre === null) {
                    // Sin obra padre: se deja con su proyecto propio para revisión manual.
                    return;
                }

                DB::transaction(function () use ($adicional, $padre): void {
                    $proyectoHuerfano = $adicional->proyecto_id;

                    Obra::query()->whereKey($adicional->id)->update([
                        'proyecto_id' => $padre->proyecto_id,
                        'tipo' => 'adicional',
                    ]);

                    // Borra el proyecto que le había creado el backfill si quedó vacío.
                    if ($proyectoHuerfano !== null
                        && $proyectoHuerfano !== $padre->proyecto_id
                        && ! Obra::query()->where('proyecto_id', $proyectoHuerfano)->exists()) {
                        Proyecto::query()->whereKey($proyectoHuerfano)->delete();
                    }
                });
            });
    }

    public function down(): void
    {
        // Irreversible: no se reconstruyen los proyectos huérfanos eliminados.
    }
};
