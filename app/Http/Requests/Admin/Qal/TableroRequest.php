<?php

namespace App\Http\Requests\Admin\Qal;

use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Enums\Qal\SubtipoPrimera;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La barra de filtros del tablero, que viaja en la URL: un tablero acotado se
 * comparte con su enlace.
 */
class TableroRequest extends FormRequest
{
    /** Los filtros, en el orden de la barra. */
    public const FILTROS = ['fase', 'obra', 'subetapa', 'soldador', 'inspector', 'tipo', 'subtipo1', 'desde', 'hasta', 'semana'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['accesorios'])],
            'fase' => ['nullable', Rule::enum(FaseTransformacion::class)],
            'obra' => ['nullable', 'integer'],
            'subetapa' => ['nullable', Rule::enum(Subetapa::class)],
            'soldador' => ['nullable', 'integer'],
            'inspector' => ['nullable', 'integer'],
            'tipo' => ['nullable', 'integer'],
            'subtipo1' => ['nullable', Rule::enum(SubtipoPrimera::class)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'semana' => ['nullable', 'string', 'regex:/^\d{4}-S(0[1-9]|[1-4]\d|5[0-3])$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fase' => 'La transformación es 1ª, 2ª o 3ª.',
            'subetapa' => 'La sub-etapa es armado y vestido o soldado.',
            'subtipo1' => 'En 1ª la pieza es perfil o placa.',
            'desde.date_format' => 'La fecha va como 2026-09-10.',
            'hasta.date_format' => 'La fecha va como 2026-09-10.',
            'hasta.after_or_equal' => 'El periodo termina antes de empezar.',
            'semana.regex' => 'La semana va como 2026-S37.',
        ];
    }

    /**
     * Los filtros puestos, todos presentes y en texto; los vacíos, en nulo.
     *
     * @return array<string, string|null>
     */
    public function filtros(): array
    {
        return collect(self::FILTROS)
            ->mapWithKeys(fn (string $filtro): array => [$filtro => filled($this->validated($filtro)) ? (string) $this->validated($filtro) : null])
            ->all();
    }
}
