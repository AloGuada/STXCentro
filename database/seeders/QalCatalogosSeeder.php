<?php

namespace Database\Seeders;

use App\Enums\Qal\AmbitoDefecto;
use App\Models\Qal\Defecto;
use App\Models\Qal\TipoPieza;
use Illuminate\Database\Seeder;

/**
 * Los catálogos que venían escritos en el código del hub de Steelex.
 *
 * No son datos de ejemplo: los prefijos son los del documento «descripción de
 * partes» de ingeniería, y los defectos son los que el inspector puede marcar
 * hoy. Estaban en `captura.html` como respaldo de lo que hubiera en la nube, así
 * que se traen tal cual — el resto de los catálogos (soldadores, obras,
 * laboratorios) sí son datos y llegarán por CSV.
 *
 * Es idempotente: `firstOrCreate` por prefijo o por nombre, así que correrlo dos
 * veces no duplica ni pisa lo que alguien haya corregido a mano.
 */
class QalCatalogosSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->tiposPieza() as $prefijo => $descripcion) {
            TipoPieza::firstOrCreate(
                ['prefijo' => $prefijo],
                ['descripcion' => $descripcion, 'activo' => true],
            );
        }

        foreach ($this->defectos() as $ambito => $nombres) {
            foreach ($nombres as $nombre) {
                Defecto::firstOrCreate(['ambito' => $ambito, 'nombre' => $nombre], ['activo' => true]);
            }
        }
    }

    /**
     * Los defectos por ámbito. Los accesorios que fallan por soldadura usan la
     * lista de soldadura, así que no tienen una propia.
     *
     * @return array<string, list<string>>
     */
    private function defectos(): array
    {
        return [
            AmbitoDefecto::Soldadura->value => $this->defectosSoldadura(),
            AmbitoDefecto::Pintura->value => $this->defectosPintura(),
            AmbitoDefecto::AccesorioDimensional->value => [
                'Deflexión',
                'Torsión',
                'Flecha',
                'Contraflecha',
                'Hi-Low',
                'Alabeo en patín',
                'Pandeo de alma',
                'Longitud fuera de tolerancia',
                'Escuadre',
                'Otro',
            ],
            AmbitoDefecto::AccesorioBarrenos->value => [
                'Diámetro incorrecto',
                'Posición incorrecta',
                'Barreno faltante',
                'Barreno sin habilitar',
                'Otro',
            ],
            AmbitoDefecto::AccesorioLimpieza->value => ['Falta de limpieza'],
        ];
    }

    /**
     * Prefijos oficiales de ingeniería. Con ellos la captura deduce sola el tipo
     * a partir de la marca, así que calidad e ingeniería nombran igual la pieza.
     *
     * @return array<string, string>
     */
    private function tiposPieza(): array
    {
        return [
            // Estructura principal
            'TP' => 'Trabe principal',
            'TS' => 'Trabe secundaria',
            'TA' => 'Trabe de amarre',
            'TG' => 'Trabe grúa',
            'VR' => 'Viga riel',
            'AR' => 'Armadura',
            'PT' => 'Puntal',
            'CM' => 'Columna metálica',
            'CME' => 'Columna OR',
            'CMV' => 'Columna de viento',
            // Largueros y cubierta
            'LC' => 'Larguero de cubierta / polín',
            'LCJ' => 'Larguero de cubierta encajonado',
            'LM' => 'Larguero de muro',
            'LMJ' => 'Larguero de muro encajonado',
            'FR' => 'Frontera para losacero',
            'FAL' => 'Faldón',
            'LVR' => 'Louver',
            // Arriostramiento
            'CFC' => 'Contraflambeo de cubierta',
            'CFM' => 'Contraflambeo de muro',
            'CVC' => 'Contraviento de cubierta',
            'CVM' => 'Contraviento de muro',
            'RCV' => 'Roldana de contraviento',
            'R' => 'Riostra',
            'BR' => 'Bracer',
            'TEN' => 'Tensor',
            // Anclaje y conexiones
            'AN' => 'Ancla',
            'CAST' => 'Castillo de anclaje',
            'PBE' => 'Placa base embebida',
            'CXE' => 'Conexión embebida',
            'CXM' => 'Conexión a muro',
            'CXST' => 'Conexión suelta',
            'ANGT' => 'Ángulo terminal',
            // Escaleras y barandales
            'ESC' => 'Escalera',
            'ALF' => 'Alfarda de escalera',
            'DESC' => 'Descanso de escalera',
            'BAR' => 'Barandal',
            // Accesorios y soportes
            'BA' => 'Bastidor',
            'RL' => 'Riel',
            'SCAN' => 'Soporte de canalón',
            'TIC' => 'Tirante de canalón',
            'SE' => 'Soporte de extractor',
            'SLAM' => 'Soporte de lámina',
            'OTRO' => 'Otro',
        ];
    }

    /**
     * @return list<string>
     */
    private function defectosSoldadura(): array
    {
        return [
            'Grieta',
            'Falta de fusión',
            'Falta de penetración',
            'Traslape',
            'Cráter sin llenar',
            'Perfil inaceptable',
            'Soldadura convexa',
            'Soldadura cóncava',
            'Tamaño bajo lo nominal',
            'Pierna baja',
            'Garganta baja',
            'Piernas desiguales',
            'Socavación',
            'Golpe de arco',
            'Porosidad / poros',
            'Falta de soldadura',
            'Falta de relleno',
            'Falta de remate',
            'Puntos de soldadura sin retirar',
            'Daño de material',
            'Otro',
        ];
    }

    /**
     * @return list<string>
     */
    private function defectosPintura(): array
    {
        return [
            'Falta pintura (FP)',
            'Espesor bajo (EB)',
            'Falta adherencia (FA)',
            'Falta limpieza (LIM)',
            'Otro',
        ];
    }
}
