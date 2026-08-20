<?php

namespace App\Services\Qal;

use App\Models\Qal\Pieza;
use App\Models\Qal\PndJunta;
use App\Models\Qal\PndReporte;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda el informe del laboratorio completo: encabezado, parámetros y rejilla.
 *
 * Va en una transacción porque el informe **es** las tres cosas. La aplicación
 * anterior guardaba la rejilla renglón por renglón con escrituras sueltas, y un
 * corte a media captura dejaba un informe con la mitad de sus juntas: el
 * porcentaje de rechazo salía calculado sobre un denominador que no existió
 * nunca.
 *
 * Parámetros y juntas se reemplazan en bloque al editar. No es pereza: el
 * informe se teclea de un documento firmado, así que la corrección es «esto no
 * era lo que dice el papel», y conciliar renglón por renglón contra ids que el
 * capturista no ve no aporta nada.
 */
class RegistradorInformePnd
{
    /**
     * @param  array<string, mixed>  $encabezado
     * @param  list<array<string, mixed>>  $juntas
     * @param  list<array{clave: string, valor: string}>  $parametros
     * @param  list<UploadedFile>  $fotos
     */
    public function guardar(
        PndReporte $reporte,
        array $encabezado,
        array $juntas,
        array $parametros,
        ?UploadedFile $pdf = null,
        array $fotos = [],
    ): PndReporte {
        return DB::transaction(function () use ($reporte, $encabezado, $juntas, $parametros, $pdf, $fotos) {
            if ($pdf instanceof UploadedFile) {
                $anterior = $reporte->archivo_pdf;
                $encabezado['archivo_pdf'] = $pdf->store('qal/pnd', 'public');

                if ($anterior) {
                    Storage::disk('public')->delete($anterior);
                }
            }

            $reporte->fill($encabezado)->save();

            $reporte->parametros()->delete();
            $reporte->parametros()->createMany($this->limpiarParametros($parametros));

            $reporte->juntas()->delete();
            $reporte->juntas()->createMany($this->prepararJuntas($reporte, $juntas));

            foreach ($fotos as $foto) {
                $reporte->fotos()->create([
                    'ruta' => $foto->store('qal/pnd/fotos', 'public'),
                    'nombre' => $foto->getClientOriginalName(),
                ]);
            }

            return $reporte;
        });
    }

    /**
     * Vuelve a intentar el enlace marca → pieza de las juntas que quedaron
     * sueltas.
     *
     * El laboratorio entrega antes de que Calidad dé de alta las piezas, así
     * que un informe nace con `qal_pieza_id` en nulo y se engancha semanas
     * después. Sólo toca las que están sueltas: una junta ya enlazada no se
     * reasigna, porque el enlace pudo corregirse a mano.
     *
     * @return int cuántas juntas quedaron enlazadas en esta pasada
     */
    public function resolverMarcas(PndReporte $reporte): int
    {
        $piezas = $this->piezasDeLaObra($reporte);
        $enlazadas = 0;

        foreach ($reporte->juntas()->whereNull('qal_pieza_id')->get() as $junta) {
            $piezaId = $piezas[$this->normalizar($junta->marca)] ?? null;

            if ($piezaId !== null) {
                $junta->update(['qal_pieza_id' => $piezaId]);
                $enlazadas++;
            }
        }

        return $enlazadas;
    }

    /**
     * @param  list<array{clave?: string|null, valor?: string|null}>  $parametros
     * @return list<array{clave: string, valor: string}>
     */
    private function limpiarParametros(array $parametros): array
    {
        $limpios = [];

        foreach ($parametros as $parametro) {
            $clave = trim((string) ($parametro['clave'] ?? ''));
            $valor = trim((string) ($parametro['valor'] ?? ''));

            // Una clave sin valor es un renglón que el capturista abrió y no
            // llenó; la unique de la tabla haría fallar el guardado entero por
            // dos de esos.
            if ($clave === '' || $valor === '' || isset($limpios[$this->normalizar($clave)])) {
                continue;
            }

            $limpios[$this->normalizar($clave)] = ['clave' => $clave, 'valor' => $valor];
        }

        return array_values($limpios);
    }

    /**
     * Deja cada renglón listo para insertarse: junta y spot separados, y la
     * pieza enganchada cuando la marca ya existe.
     *
     * @param  list<array<string, mixed>>  $juntas
     * @return list<array<string, mixed>>
     */
    private function prepararJuntas(PndReporte $reporte, array $juntas): array
    {
        $piezas = $this->piezasDeLaObra($reporte);

        return array_values(array_map(function (array $fila) use ($piezas): array {
            $referencia = trim((string) ($fila['junta'] ?? ''));
            $partida = PndJunta::descomponerReferencia($referencia);

            $marca = mb_strtoupper(trim((string) ($fila['marca'] ?? '')));
            $spot = $fila['spot'] ?? null;

            return [
                'marca' => $marca,
                'qal_pieza_id' => $piezas[$this->normalizar($marca)] ?? null,
                'junta' => $partida['junta'],
                // El spot tecleado gana sobre el deducido: la deducción es una
                // sugerencia y el capturista tiene el informe a la vista.
                'spot' => $spot !== null && $spot !== '' ? max((int) $spot, 1) : $partida['spot'],
                'modulo' => $this->oNulo($fila['modulo'] ?? null),
                'resultado' => $fila['resultado'],
                'discontinuidad' => $this->oNulo($fila['discontinuidad'] ?? null),
                'longitud_discontinuidad' => $this->oNuloNumero($fila['longitud_discontinuidad'] ?? null),
                'espesor' => $this->oNuloNumero($fila['espesor'] ?? null),
                'soldador_id' => $fila['soldador_id'] ?? null,
            ];
        }, $juntas));
    }

    /**
     * Marcas de la obra del informe, indexadas para comparar sin depender de
     * mayúsculas ni espacios.
     *
     * @return array<string, int>
     */
    private function piezasDeLaObra(PndReporte $reporte): array
    {
        return Pieza::query()
            ->whereHas('etapa', fn ($consulta) => $consulta->where('obra_id', $reporte->qal_obra_id))
            ->pluck('id', 'marca')
            ->mapWithKeys(fn (int $id, string $marca): array => [$this->normalizar($marca) => $id])
            ->all();
    }

    private function normalizar(string $texto): string
    {
        return mb_strtoupper(trim($texto));
    }

    private function oNulo(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private function oNuloNumero(mixed $valor): ?float
    {
        return $valor === null || $valor === '' ? null : (float) $valor;
    }
}
