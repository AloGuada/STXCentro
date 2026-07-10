<?php

namespace Database\Seeders;

use App\Models\Banco;
use Illuminate\Database\Seeder;

class BancosSeeder extends Seeder
{
    /**
     * Bancos principales con los que se opera. Banorte es el banco pagador de la
     * empresa: sus cuentas se capturan por número de cuenta (10 dígitos); el
     * resto por CLABE. Idempotente por nombre.
     */
    public function run(): void
    {
        $bancos = [
            ['nombre' => 'Banorte', 'es_pagador' => true, 'digitos_cuenta' => 10],
            ['nombre' => 'BBVA', 'es_pagador' => false, 'digitos_cuenta' => null],
            ['nombre' => 'Santander', 'es_pagador' => false, 'digitos_cuenta' => null],
            ['nombre' => 'Banamex', 'es_pagador' => false, 'digitos_cuenta' => null],
            ['nombre' => 'HSBC', 'es_pagador' => false, 'digitos_cuenta' => null],
            ['nombre' => 'Scotiabank', 'es_pagador' => false, 'digitos_cuenta' => null],
            ['nombre' => 'Banco Azteca', 'es_pagador' => false, 'digitos_cuenta' => null],
            ['nombre' => 'Inbursa', 'es_pagador' => false, 'digitos_cuenta' => null],
            ['nombre' => 'Afirme', 'es_pagador' => false, 'digitos_cuenta' => null],
            ['nombre' => 'BanBajío', 'es_pagador' => false, 'digitos_cuenta' => null],
        ];

        foreach ($bancos as $banco) {
            Banco::updateOrCreate(
                ['nombre' => $banco['nombre']],
                ['es_pagador' => $banco['es_pagador'], 'digitos_cuenta' => $banco['digitos_cuenta'], 'activo' => true],
            );
        }
    }
}
