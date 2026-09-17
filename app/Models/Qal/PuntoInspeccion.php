<?php

namespace App\Models\Qal;

use App\Enums\Qal\AmbitoPunto;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\ResultadoPunto;
use App\Enums\Qal\Subetapa;
use App\Enums\Qal\SubtipoPrimera;
use App\Enums\Qal\TipoDatoPunto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un punto que revisa el inspector: lo que en la aplicación anterior era una
 * columna de `registros`.
 *
 * Los de selección traen sus respuestas con lo que significa cada una
 * (cumple, no cumple, no aplica), así que el servidor sabe si una respuesta es
 * defecto sin mantener una lista de sinónimos.
 *
 * @property list<array{valor: string, resultado: string|null}>|null $opciones
 *
 * @use HasFactory<\Database\Factories\Qal\PuntoInspeccionFactory>
 */
class PuntoInspeccion extends Model
{
    use HasFactory;

    protected $table = 'qal_puntos_inspeccion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clave',
        'ambito',
        'fase',
        'subetapa',
        'subtipo',
        'seccion',
        'etiqueta',
        'tipo_dato',
        'opciones',
        'unidad',
        'calculado',
        'obligatorio',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ambito' => AmbitoPunto::class,
            'fase' => FaseTransformacion::class,
            'subetapa' => Subetapa::class,
            'subtipo' => SubtipoPrimera::class,
            'tipo_dato' => TipoDatoPunto::class,
            'opciones' => 'array',
            'calculado' => 'boolean',
            'obligatorio' => 'boolean',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * Los puntos que pide un formulario: los de su fase, los de su sub-etapa o
     * subtipo si los tiene, y los comunes a toda la fase.
     *
     * @param  Builder<self>  $query
     */
    public function scopeParaFormulario(
        Builder $query,
        FaseTransformacion $fase,
        ?Subetapa $subetapa = null,
        ?SubtipoPrimera $subtipo = null,
        AmbitoPunto $ambito = AmbitoPunto::Pieza,
    ): void {
        $query->where('activo', true)
            ->where('fase', $fase->value)
            ->where('ambito', $ambito->value)
            ->where(fn (Builder $q) => $q->whereNull('subetapa')->when($subetapa, fn (Builder $q) => $q->orWhere('subetapa', $subetapa->value)))
            ->where(fn (Builder $q) => $q->whereNull('subtipo')->when($subtipo, fn (Builder $q) => $q->orWhere('subtipo', $subtipo->value)))
            ->orderBy('orden');
    }

    /** Si la respuesta es una de las que el punto admite. */
    public function admite(string $valor): bool
    {
        return match ($this->tipo_dato) {
            TipoDatoPunto::Seleccion => collect($this->opciones ?? [])->contains('valor', $valor),
            TipoDatoPunto::Numero => is_numeric($valor),
            TipoDatoPunto::Contador => ctype_digit($valor),
            TipoDatoPunto::Texto => mb_strlen($valor) <= 160,
        };
    }

    /**
     * Lo que significa la respuesta. Nulo cuando la respuesta sólo describe
     * (el tipo de desviación, su tamaño) o cuando el punto no es de selección.
     */
    public function resultadoDe(string $valor): ?ResultadoPunto
    {
        $opcion = collect($this->opciones ?? [])->firstWhere('valor', $valor);

        return isset($opcion['resultado']) ? ResultadoPunto::from($opcion['resultado']) : null;
    }
}
