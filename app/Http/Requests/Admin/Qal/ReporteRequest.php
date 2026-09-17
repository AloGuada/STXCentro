<?php

namespace App\Http\Requests\Admin\Qal;

use App\Services\Qal\Formatos\FiltrosDeReporte;
use App\Services\Qal\Formatos\Formato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Los filtros de un formato PDF, que llegan en la URL.
 *
 * La obra es obligatoria: un formato es de una obra. El día o la semana, sólo
 * cuando el periodo los pide; la pieza, sólo en el mapeo. Estatus y vista, si
 * no llegan, toman los del formato: los del dosier arrancan en su hoja final
 * con las liberadas y los internos en el histórico con todas.
 */
class ReporteRequest extends FormRequest
{
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
            'obra' => ['required', 'integer', 'exists:obras,id'],
            'periodo' => ['nullable', Rule::in(FiltrosDeReporte::PERIODOS)],
            'fecha' => ['nullable', 'required_if:periodo,dia', 'date_format:Y-m-d'],
            'semana' => ['nullable', 'required_if:periodo,semana', 'regex:/^\d{4}-S\d{2}$/'],
            'inspector' => ['nullable', 'integer'],
            'estatus' => ['nullable', Rule::in(FiltrosDeReporte::ESTATUS)],
            'vista' => ['nullable', Rule::in(FiltrosDeReporte::VISTAS)],
            'pieza' => [Rule::requiredIf(fn (): bool => $this->route('formato') === 'mapeo'), 'nullable', 'integer'],
            'descargar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'obra.required' => 'Elige la obra: cada formato es de una obra.',
            'fecha.required_if' => 'Elige el día.',
            'semana.required_if' => 'Elige la semana.',
            'semana.regex' => 'La semana va como 2026-S37.',
            'pieza.required' => 'El mapeo es de una pieza: elígela.',
        ];
    }

    public function filtros(Formato $formato): FiltrosDeReporte
    {
        return new FiltrosDeReporte(
            obraId: $this->integer('obra'),
            periodo: $formato->usaPeriodo() ? ($this->validated('periodo') ?? 'todo') : 'todo',
            fecha: $this->validated('fecha'),
            semana: $this->validated('semana'),
            inspectorId: $this->integer('inspector') ?: null,
            estatus: $this->validated('estatus') ?? $formato->estatusPorDefecto(),
            vista: $this->validated('vista') ?? $formato->vistaPorDefecto(),
            piezaId: $this->integer('pieza') ?: null,
        );
    }
}
