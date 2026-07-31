<?php

namespace Database\Seeders\Rh;

use App\Models\Rh\Persona;
use Illuminate\Database\Seeder;

/**
 * Carga el padrón de personas desde `data/personas.php`.
 *
 * Reglas acordadas:
 *  - La identidad es la CURP. Si ya existe una persona con esa CURP se
 *    actualiza; nunca se crea una segunda. Sin CURP se intenta por RFC y, en
 *    último caso, por el número de empleado de su periodo laboral.
 *  - Gana el archivo: lo que traiga dato pisa lo que haya en RH. Los campos
 *    vacíos del archivo nunca borran lo ya capturado.
 *  - No se inventan contratos: el número de empleado sólo se escribe si la
 *    persona ya tiene un periodo laboral vigente. Los números que no encuentran
 *    dónde vivir se reportan al final para que RH los capture.
 */
class PersonasSeeder extends Seeder
{
    /** @var list<string> Se copian tal cual del archivo a la persona. */
    private const CAMPOS = ['nombre', 'apellido', 'curp', 'rfc', 'imss'];

    /** @var array{creadas: int, actualizadas: int, numeros_aplicados: int} */
    private array $conteos = ['creadas' => 0, 'actualizadas' => 0, 'numeros_aplicados' => 0];

    /** @var list<string> */
    private array $numerosSinContrato = [];

    /**
     * Renglones a sembrar. Se sobrescribe en las pruebas para no depender del
     * archivo de datos.
     *
     * @return list<array<string, string|null>>
     */
    protected function filas(): array
    {
        return require __DIR__.'/data/personas.php';
    }

    public function run(): void
    {
        foreach ($this->filas() as $fila) {
            $this->sembrar($this->normalizar($fila));
        }

        $this->reportar();
    }

    /**
     * @param  array<string, string|null>  $fila
     * @return array<string, string|null>
     */
    private function normalizar(array $fila): array
    {
        $limpiar = function (?string $valor): ?string {
            $valor = trim((string) $valor);

            return $valor === '' ? null : $valor;
        };

        $mayusculas = fn (?string $valor): ?string => $valor === null ? null : mb_strtoupper($valor);

        return [
            'nombre' => $limpiar($fila['nombre'] ?? null),
            'apellido' => $limpiar($fila['apellido'] ?? null),
            'curp' => $mayusculas($limpiar($fila['curp'] ?? null)),
            'rfc' => $mayusculas($limpiar($fila['rfc'] ?? null)),
            'imss' => $limpiar($fila['nss'] ?? $fila['imss'] ?? null),
            'numero' => $limpiar($fila['numero'] ?? null),
        ];
    }

    /**
     * @param  array<string, string|null>  $fila
     */
    private function sembrar(array $fila): void
    {
        $persona = $this->buscar($fila);

        /** @var array<string, string> $cambios */
        $cambios = array_filter(
            array_intersect_key($fila, array_flip(self::CAMPOS)),
            fn (?string $valor) => $valor !== null,
        );

        if ($persona === null) {
            $persona = Persona::create($cambios);
            $this->conteos['creadas']++;
        } else {
            $persona->fill($cambios)->save();
            $this->conteos['actualizadas']++;
        }

        $this->aplicarNumero($persona, $fila['numero']);
    }

    /**
     * @param  array<string, string|null>  $fila
     */
    private function buscar(array $fila): ?Persona
    {
        if ($fila['curp'] !== null) {
            $porCurp = Persona::whereLike('curp', $fila['curp'])->first();

            if ($porCurp !== null) {
                return $porCurp;
            }
        }

        if ($fila['rfc'] !== null) {
            $porRfc = Persona::whereLike('rfc', $fila['rfc'])->first();

            if ($porRfc !== null) {
                return $porRfc;
            }
        }

        if ($fila['numero'] !== null) {
            $porNumero = Persona::whereHas(
                'periodosLaborales',
                fn ($q) => $q->whereLike('numero_empleado', $fila['numero']),
            )->first();

            if ($porNumero !== null) {
                return $porNumero;
            }
        }

        // Último recurso para los renglones sin ningún identificador oficial: el
        // nombre. Es endeble (dos homónimos se fusionarían), pero sin esto cada
        // corrida los volvería a dar de alta.
        if ($fila['curp'] === null && $fila['rfc'] === null) {
            return Persona::whereLike('nombre', $fila['nombre'])
                ->whereLike('apellido', $fila['apellido'])
                ->first();
        }

        return null;
    }

    /** El número vive en el contrato, así que sin contrato vigente no hay dónde ponerlo. */
    private function aplicarNumero(Persona $persona, ?string $numero): void
    {
        if ($numero === null) {
            return;
        }

        $periodo = $persona->periodoVigente()->first();

        if ($periodo === null) {
            $this->numerosSinContrato[] = "{$numero} ({$persona->nombre_completo})";

            return;
        }

        $periodo->update(['numero_empleado' => $numero]);
        $this->conteos['numeros_aplicados']++;
    }

    private function reportar(): void
    {
        $this->command?->info(sprintf(
            'Personas: %d creadas, %d actualizadas. Números aplicados: %d.',
            $this->conteos['creadas'],
            $this->conteos['actualizadas'],
            $this->conteos['numeros_aplicados'],
        ));

        if ($this->numerosSinContrato === []) {
            return;
        }

        $this->command?->warn(sprintf(
            '%d números sin periodo laboral vigente (RH debe capturar el contrato): %s',
            count($this->numerosSinContrato),
            implode(', ', $this->numerosSinContrato),
        ));
    }
}
