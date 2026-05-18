<?php

namespace App\Console\Commands\Rh;

use App\Services\Rh\Cv\CvProcessor;
use Illuminate\Console\Command;

class ResolveCv extends Command
{
    protected $signature = 'rh:resolve-cv';

    protected $description = 'Procesa el siguiente paso pendiente de un CV (PDF → texto → datos → requerimientos → skills).';

    public function handle(CvProcessor $processor): int
    {
        $personaId = $processor->procesarSiguiente();

        if ($personaId === null) {
            $this->info('No hay CVs pendientes para procesar.');

            return self::SUCCESS;
        }

        $this->info("Persona #{$personaId}: paso ejecutado.");

        return self::SUCCESS;
    }
}
