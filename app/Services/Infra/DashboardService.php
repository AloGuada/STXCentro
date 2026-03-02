<?php

namespace App\Services\Infra;

use App\Models\Infra\Bomba;
use App\Models\Infra\Compresor;
use App\Models\Infra\Ptar;
use App\Models\Infra\Tanque;
use App\Models\Infra\Transformador;

class DashboardService
{
    /**
     * Evalúa el estado de cada sub-equipo de los 5 sistemas.
     *
     * @return array{
     *     compresores: list<array{label: string, estado: bool|null, tooltip: string}>,
     *     bombas: list<array{label: string, estado: bool|null, tooltip: string}>,
     *     tanques: list<array{label: string, estado: bool|null, tooltip: string}>,
     *     ptar: list<array{label: string, estado: bool|null, tooltip: string}>,
     *     transformadores: list<array{label: string, estado: bool|null, tooltip: string}>
     * }
     */
    public function evaluarEstados(
        ?Compresor $compresor,
        ?Bomba $bomba,
        ?Tanque $tanque,
        ?Ptar $ptar,
        ?Transformador $transformador,
    ): array {
        return [
            'compresores' => $this->evaluarCompresores($compresor),
            'bombas' => $this->evaluarBombas($bomba),
            'tanques' => $this->evaluarTanques($tanque),
            'ptar' => $this->evaluarPtar($ptar),
            'transformadores' => $this->evaluarTransformadores($transformador),
        ];
    }

    /**
     * @return list<array{label: string, estado: bool|null, tooltip: string}>
     */
    private function evaluarCompresores(?Compresor $c): array
    {
        $items = [];
        foreach ([1, 2, 3] as $i) {
            $label = "C{$i}";

            if ($c === null) {
                $items[] = ['label' => $label, 'estado' => null, 'tooltip' => 'Sin registro'];

                continue;
            }

            $status = $c->{"compresor_{$i}_status"};
            $presion = $c->{"compresor_{$i}_presion_aire"};

            if (! $status) {
                $items[] = ['label' => $label, 'estado' => false, 'tooltip' => 'Compresor apagado'];
            } elseif ($presion === null) {
                $items[] = ['label' => $label, 'estado' => false, 'tooltip' => 'Sin lectura de presión'];
            } elseif ($this->inRange((float) $presion, 116, 124)) {
                $items[] = ['label' => $label, 'estado' => true, 'tooltip' => "Presión: {$presion} PSI"];
            } else {
                $items[] = ['label' => $label, 'estado' => false, 'tooltip' => "Presión fuera de rango: {$presion} PSI (116-124)"];
            }
        }

        return $items;
    }

    /**
     * @return list<array{label: string, estado: bool|null, tooltip: string}>
     */
    private function evaluarBombas(?Bomba $b): array
    {
        if ($b === null) {
            return [
                ['label' => 'Pozos', 'estado' => null, 'tooltip' => 'Sin registro'],
                ['label' => 'Planta', 'estado' => null, 'tooltip' => 'Sin registro'],
                ['label' => 'Purificada', 'estado' => null, 'tooltip' => 'Sin registro'],
                ['label' => 'Potable', 'estado' => null, 'tooltip' => 'Sin registro'],
                ['label' => 'Incendios', 'estado' => null, 'tooltip' => 'Sin registro'],
            ];
        }

        $pozos = $b->bomba_posos_1 && $b->bomba_posos_2;
        $planta = $b->bomba_planta_1 && $b->bomba_planta_2 && $b->bomba_planta_3;

        $purificada = $this->inRange($b->nivel_salmuera, 20, 100)
            && $this->inRange($b->nivel_tinaco, 40, 100);

        $potable = $this->inRange($b->nivel_sisterna, 30, 100)
            && $this->inRange($b->presion_tuberia, 40, 50)
            && $this->inRange($b->nivel_hipoclorito, 10, 100);

        $incendios = (bool) $b->bomba_jockey;

        return [
            ['label' => 'Pozos', 'estado' => $pozos, 'tooltip' => $pozos ? 'Bombas de pozos OK' : 'Alguna bomba de pozos apagada'],
            ['label' => 'Planta', 'estado' => $planta, 'tooltip' => $planta ? 'Bombas de planta OK' : 'Alguna bomba de planta apagada'],
            ['label' => 'Purificada', 'estado' => $purificada, 'tooltip' => $purificada ? 'Niveles OK' : "Salmuera: {$b->nivel_salmuera}% (20-100), Tinaco: {$b->nivel_tinaco}% (40-100)"],
            ['label' => 'Potable', 'estado' => $potable, 'tooltip' => $potable ? 'Niveles OK' : "Sisterna: {$b->nivel_sisterna}% (30-100), Tubería: {$b->presion_tuberia} (40-50), Hipoclorito: {$b->nivel_hipoclorito}% (10-100)"],
            ['label' => 'Incendios', 'estado' => $incendios, 'tooltip' => $incendios ? 'Bomba jockey OK' : 'Bomba jockey apagada'],
        ];
    }

    /**
     * @return list<array{label: string, estado: bool|null, tooltip: string}>
     */
    private function evaluarTanques(?Tanque $t): array
    {
        if ($t === null) {
            return [
                ['label' => 'O₂', 'estado' => null, 'tooltip' => 'Sin registro'],
                ['label' => 'Ar', 'estado' => null, 'tooltip' => 'Sin registro'],
                ['label' => 'LP', 'estado' => null, 'tooltip' => 'Sin registro'],
                ['label' => 'CO₂', 'estado' => null, 'tooltip' => 'Sin registro'],
            ];
        }

        $o2 = $this->inRange($t->presion_sistema_oxigeno, 200, 290);
        $ar = $this->inRange($t->presion_sistema_argon, 200, 290);
        $lp = $this->inRange($t->nivel_tanque_lp, 25, 80);
        $co2 = $this->inRange($t->presion_sistema_co2, 200, 290);

        return [
            ['label' => 'O₂', 'estado' => $o2, 'tooltip' => $o2 ? "Presión sistema: {$t->presion_sistema_oxigeno} PSI" : "Presión fuera de rango: {$t->presion_sistema_oxigeno} PSI (200-290)"],
            ['label' => 'Ar', 'estado' => $ar, 'tooltip' => $ar ? "Presión sistema: {$t->presion_sistema_argon} PSI" : "Presión fuera de rango: {$t->presion_sistema_argon} PSI (200-290)"],
            ['label' => 'LP', 'estado' => $lp, 'tooltip' => $lp ? "Nivel: {$t->nivel_tanque_lp}%" : "Nivel fuera de rango: {$t->nivel_tanque_lp}% (25-80)"],
            ['label' => 'CO₂', 'estado' => $co2, 'tooltip' => $co2 ? "Presión sistema: {$t->presion_sistema_co2} PSI" : "Presión fuera de rango: {$t->presion_sistema_co2} PSI (200-290)"],
        ];
    }

    /**
     * @return list<array{label: string, estado: bool|null, tooltip: string}>
     */
    private function evaluarPtar(?Ptar $p): array
    {
        if ($p === null) {
            return [['label' => 'PTAR', 'estado' => null, 'tooltip' => 'Sin registro']];
        }

        $ok = $p->soplador_activa
            && $p->bomba_activa
            && $p->trampa_solida
            && $this->inRange($p->nivel_cloro, 30, 100);

        $detalles = [];
        if (! $p->soplador_activa) {
            $detalles[] = 'Soplador OFF';
        }
        if (! $p->bomba_activa) {
            $detalles[] = 'Bomba OFF';
        }
        if (! $p->trampa_solida) {
            $detalles[] = 'Trampa sucia';
        }
        if (! $this->inRange($p->nivel_cloro, 30, 100)) {
            $detalles[] = "Cloro: {$p->nivel_cloro}% (30-100)";
        }

        return [
            [
                'label' => 'PTAR',
                'estado' => $ok,
                'tooltip' => $ok ? 'Todos los sistemas OK' : implode(', ', $detalles),
            ],
        ];
    }

    /**
     * @return list<array{label: string, estado: bool|null, tooltip: string}>
     */
    private function evaluarTransformadores(?Transformador $t): array
    {
        if ($t === null) {
            return [
                ['label' => 'Línea A', 'estado' => null, 'tooltip' => 'Sin registro'],
                ['label' => 'Línea B', 'estado' => null, 'tooltip' => 'Sin registro'],
                ['label' => 'Línea C', 'estado' => null, 'tooltip' => 'Sin registro'],
            ];
        }

        $items = [];
        foreach (['A' => 'a', 'B' => 'b', 'C' => 'c'] as $label => $key) {
            $voltaje = $t->{"voltaje_{$key}"};
            $lectura = $t->{"linea_{$label}"};
            $max = $t->{"linea_{$label}_max"};

            if ($voltaje === null) {
                $estado = true;
                $tooltip = "Lectura: {$lectura} A, Máx: {$max} A";
            } elseif ($this->inRange((float) $voltaje, 120, 130)) {
                $estado = true;
                $tooltip = "Voltaje: {$voltaje} V, Lectura: {$lectura} A, Máx: {$max} A";
            } else {
                $estado = false;
                $tooltip = "Voltaje fuera de rango: {$voltaje} V (120-130), Lectura: {$lectura} A, Máx: {$max} A";
            }

            $items[] = ['label' => "Línea {$label}", 'estado' => $estado, 'tooltip' => $tooltip];
        }

        return $items;
    }

    private function inRange(?float $value, float $min, float $max): bool
    {
        if ($value === null) {
            return false;
        }

        return $value >= $min && $value <= $max;
    }
}
