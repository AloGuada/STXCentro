<?php

namespace Database\Seeders;

use App\Models\Cob\DocumentoSeccion;
use Illuminate\Database\Seeder;

class CobDocumentoSeccionSeeder extends Seeder
{
    public function run(): void
    {
        $secciones = [
            'CONTRATO',
            'SUSTENTO DE OBRA',
            'PMO',
            'FIANZAS',
            'ESTADO DE CUENTA',
            'COMPARATIVA',
            'FACTURAS',
            'ESTIMACIONES',
            'DOC. IMSS',
            'ACTA DE ENTREGA',
            'CIERRE TECNICO',
            'OTROS DOCUMENTOS',
        ];

        foreach ($secciones as $orden => $nombre) {
            DocumentoSeccion::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['orden' => $orden]
            );
        }
    }
}
