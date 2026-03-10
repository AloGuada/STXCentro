<?php

namespace App\Console\Commands;

use App\Models\Drive\Archivo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DriveLimpiarArchivos extends Command
{
    protected $signature = 'drive:limpiar-archivos';

    protected $description = 'Elimina archivos del Drive cuya fecha de auto-eliminación ha pasado';

    public function handle(): int
    {
        $archivos = Archivo::query()
            ->whereNotNull('auto_eliminar_en')
            ->where('auto_eliminar_en', '<=', now())
            ->get();

        $count = 0;

        foreach ($archivos as $archivo) {
            Storage::disk('local')->delete($archivo->path);
            $archivo->delete();
            $count++;
        }

        $this->info("Se eliminaron {$count} archivo(s) caducado(s).");

        return self::SUCCESS;
    }
}
