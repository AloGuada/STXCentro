<?php

namespace Database\Seeders;

use App\Models\Obra;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Corrige obras importadas de billbook que tienen el ID viejo como 'no'
 * en lugar del nombre del proyecto.
 *
 * Uso: php artisan db:seed --class=CobFixObrasNoSeeder
 */
class CobFixObrasNoSeeder extends Seeder
{
    public function run(): void
    {
        $bb = DB::connection('billbook');
        $rows = $bb->table('proyectos')->get();

        $corregidas = 0;
        $noEncontradas = 0;

        foreach ($rows as $row) {
            $obra = Obra::where('no', (string) $row->id)->first();

            if (! $obra) {
                $noEncontradas++;

                continue;
            }

            $obra->update([
                'no' => $row->nombre,
                'descripcion' => $row->descripcion,
            ]);

            $this->command->line("  Obra #{$obra->id}: '{$row->id}' → '{$row->nombre}'");
            $corregidas++;
        }

        $this->command->info("Corregidas: {$corregidas}, No encontradas: {$noEncontradas}");
    }
}
