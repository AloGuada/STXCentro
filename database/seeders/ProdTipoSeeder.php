<?php

namespace Database\Seeders;

use App\Models\Prod\TipoPagoExtra;
use Illuminate\Database\Seeder;

class ProdTipoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            ['descripcion' => 'Horas Extra', 'orden' => 1, 'desgloce' => true],
            ['descripcion' => 'Bono Productividad', 'orden' => 2, 'desgloce' => false],
            ['descripcion' => 'Transporte', 'orden' => 3, 'desgloce' => true],
            ['descripcion' => 'Alimentacion', 'orden' => 4, 'desgloce' => true],
            ['descripcion' => 'Otro', 'orden' => 5, 'desgloce' => false],
        ];

        foreach ($tipos as $tipo) {
            TipoPagoExtra::updateOrCreate(
                ['descripcion' => $tipo['descripcion']],
                $tipo
            );
        }
    }
}
