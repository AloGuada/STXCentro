<?php

namespace Database\Factories\Cob;

use App\Models\Cob\ConfiguracionDocumento;
use App\Models\Cob\DocumentoEstimacion;
use App\Models\Cob\Estimacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentoEstimacion> */
class DocumentoEstimacionFactory extends Factory
{
    protected $model = DocumentoEstimacion::class;

    public function definition(): array
    {
        return [
            'estimacion_id' => Estimacion::factory(),
            'configuracion_documento_id' => ConfiguracionDocumento::factory(),
            'ruta_archivo' => 'documentos/'.fake()->uuid().'.pdf',
            'fecha_subida' => now(),
            'subido_por' => User::factory(),
        ];
    }
}
