<?php

namespace App\Services\Qal;

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
    public function __construct(private readonly ResolutorDeMarcas $marcas) {}

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
     * Vuelve a intentar el enlace de la marca del laboratorio con la marca de
     * Producción en las juntas que quedaron sueltas.
     *
     * El laboratorio puede entregar antes de que Producción cargue el catálogo
     * de la obra, así que un informe nace con `concepto_id` en nulo y se
     * engancha después. Sólo toca las que están sueltas: una junta ya enlazada
     * no se reasigna, porque el enlace pudo corregirse a mano.
     *
     * @return int cuántas juntas quedaron enlazadas en esta pasada
     */
    public function resolverMarcas(PndReporte $reporte): int
    {
        $conceptos = $this->conceptosDeLaObra($reporte);
        $enlazadas = 0;

        foreach ($reporte->juntas()->whereNull('concepto_id')->get() as $junta) {
            $conceptoId = $this->marcas->conceptoDe($junta->marca, $conceptos);

            if ($conceptoId !== null) {
                $junta->update(['concepto_id' => $conceptoId]);
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
     * marca de Producción enganchada cuando ya existe.
     *
     * @param  list<array<string, mixed>>  $juntas
     * @return list<array<string, mixed>>
     */
    private function prepararJuntas(PndReporte $reporte, array $juntas): array
    {
        $conceptos = $this->conceptosDeLaObra($reporte);

        return array_values(array_map(function (array $fila) use ($conceptos): array {
            $referencia = trim((string) ($fila['junta'] ?? ''));
            $partida = PndJunta::descomponerReferencia($referencia);

            $marca = mb_strtoupper(trim((string) ($fila['marca'] ?? '')));
            $spot = $fila['spot'] ?? null;

            return [
                'marca' => $marca,
                'concepto_id' => $this->marcas->conceptoDe($marca, $conceptos),
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
     * Las marcas del catálogo vigente de la obra del informe. Una marca
     * repetida en dos lotes no se adivina: la junta queda suelta.
     *
     * @return array<string, int>
     */
    private function conceptosDeLaObra(PndReporte $reporte): array
    {
        $obraId = $reporte->obra()->value('obra_id');

        return $obraId === null ? [] : $this->marcas->deLaObra((int) $obraId);
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
