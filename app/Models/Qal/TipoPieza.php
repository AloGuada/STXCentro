<?php

namespace App\Models\Qal;

use App\Models\Qal\Concerns\EsCatalogoDeCalidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de pieza, identificado por el prefijo que usa ingeniería en la marca.
 *
 * Con él la captura deduce sola el tipo: si la marca es `PIP-TP12-3`, el
 * prefijo `TP` la resuelve como trabe principal sin que el inspector elija
 * nada. Por eso el prefijo es único, y por eso sólo se cambia de acuerdo con
 * ingeniería.
 *
 * @use HasFactory<\Database\Factories\Qal\TipoPiezaFactory>
 */
class TipoPieza extends Model
{
    use EsCatalogoDeCalidad, HasFactory;

    protected $table = 'qal_tipos_pieza';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'prefijo',
        'descripcion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public static function campoOrden(): string
    {
        return 'prefijo';
    }

    /**
     * El tipo que sugiere una marca.
     *
     * Las marcas vienen como `OBRA-PREFIJO[n]-CONSECUTIVO` (`PJ-CFC-8`,
     * `TV-CAST2-2`, `PIP-CM1PIP-22`), así que el prefijo oficial abre el
     * **segundo** tramo. Buscarlo en toda la marca daría falsos positivos con el
     * código de la obra.
     *
     * Se prueban los prefijos de mayor a menor longitud: si no, `CMV` se
     * resolvería como `CM`, y `RL` o `RCV` como `R`.
     *
     * Es una **sugerencia**, no una clasificación: quien captura puede
     * cambiarla.
     */
    public static function paraMarca(string $marca): ?self
    {
        $tramos = explode('-', mb_strtoupper(trim($marca)));

        if (count($tramos) < 2) {
            return null;
        }

        $segmento = preg_replace('/[^A-Z0-9]/', '', $tramos[1]) ?? '';

        if ($segmento === '') {
            return null;
        }

        return static::query()
            ->activos()
            ->get()
            ->sortByDesc(fn (self $tipo) => mb_strlen((string) $tipo->prefijo))
            ->first(function (self $tipo) use ($segmento): bool {
                $prefijo = mb_strtoupper((string) $tipo->prefijo);

                return $prefijo !== '' && $prefijo !== 'OTRO' && str_starts_with($segmento, $prefijo);
            });
    }
}
