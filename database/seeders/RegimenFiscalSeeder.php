<?php

namespace Database\Seeders;

use App\Models\RegimenFiscal;
use Illuminate\Database\Seeder;

class RegimenFiscalSeeder extends Seeder
{
    public function run(): void
    {
        // Catálogo de regímenes fiscales del SAT (c_RegimenFiscal). PF = persona
        // física, PM = persona moral. Determinan retenciones aplicables.
        $regimenes = [
            ['clave' => '601', 'descripcion' => 'General de Ley Personas Morales', 'pf' => false, 'pm' => true],
            ['clave' => '603', 'descripcion' => 'Personas Morales con Fines no Lucrativos', 'pf' => false, 'pm' => true],
            ['clave' => '605', 'descripcion' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios', 'pf' => true, 'pm' => false],
            ['clave' => '606', 'descripcion' => 'Arrendamiento', 'pf' => true, 'pm' => false],
            ['clave' => '607', 'descripcion' => 'Régimen de Enajenación o Adquisición de Bienes', 'pf' => true, 'pm' => false],
            ['clave' => '608', 'descripcion' => 'Demás ingresos', 'pf' => true, 'pm' => false],
            ['clave' => '610', 'descripcion' => 'Residentes en el Extranjero sin Establecimiento Permanente en México', 'pf' => true, 'pm' => true],
            ['clave' => '611', 'descripcion' => 'Ingresos por Dividendos (socios y accionistas)', 'pf' => true, 'pm' => false],
            ['clave' => '612', 'descripcion' => 'Personas Físicas con Actividades Empresariales y Profesionales', 'pf' => true, 'pm' => false],
            ['clave' => '614', 'descripcion' => 'Ingresos por intereses', 'pf' => true, 'pm' => false],
            ['clave' => '615', 'descripcion' => 'Régimen de los ingresos por obtención de premios', 'pf' => true, 'pm' => false],
            ['clave' => '616', 'descripcion' => 'Sin obligaciones fiscales', 'pf' => true, 'pm' => false],
            ['clave' => '620', 'descripcion' => 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos', 'pf' => false, 'pm' => true],
            ['clave' => '621', 'descripcion' => 'Incorporación Fiscal', 'pf' => true, 'pm' => false],
            ['clave' => '622', 'descripcion' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras', 'pf' => true, 'pm' => true],
            ['clave' => '623', 'descripcion' => 'Opcional para Grupos de Sociedades', 'pf' => false, 'pm' => true],
            ['clave' => '624', 'descripcion' => 'Coordinados', 'pf' => false, 'pm' => true],
            ['clave' => '625', 'descripcion' => 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas', 'pf' => true, 'pm' => false],
            ['clave' => '626', 'descripcion' => 'Régimen Simplificado de Confianza (RESICO)', 'pf' => true, 'pm' => true],
        ];

        foreach ($regimenes as $r) {
            RegimenFiscal::updateOrCreate(
                ['clave' => $r['clave']],
                [
                    'descripcion' => $r['descripcion'],
                    'aplica_persona_fisica' => $r['pf'],
                    'aplica_persona_moral' => $r['pm'],
                    'activo' => true,
                ],
            );
        }
    }
}
