<?php

namespace App\Http\Requests\Admin\Qal;

use App\Enums\Qal\MetodoPnd;
use App\Enums\Qal\ResultadoPnd;
use App\Models\Qal\PndReporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lo que se teclea del informe del laboratorio, alta y edición.
 *
 * Es uno solo para las dos porque el informe se corrige contra el mismo papel
 * del que se capturó: no hay campos que sólo se puedan poner al crear.
 */
class PndReporteRequest extends FormRequest
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
        /** @var PndReporte|null $reporte */
        $reporte = $this->route('pnd');

        return [
            // El folio es del laboratorio: se teclea tal cual viene en su hoja
            // y es único porque es la referencia que el cliente reclama.
            'reporte_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('qal_pnd_reportes', 'reporte_no')->ignore($reporte?->id),
            ],
            'metodo' => ['required', Rule::enum(MetodoPnd::class)],
            'laboratorio_id' => ['required', 'integer', 'exists:qal_laboratorios,id'],
            'qal_obra_id' => ['required', 'integer', 'exists:qal_obras,id'],
            'lugar' => ['nullable', 'string', 'max:255'],
            'fecha_prueba' => ['required', 'date'],
            'fecha_emision' => ['nullable', 'date', 'after_or_equal:fecha_prueba'],
            // La semana va con su año: sola es ambigua entre ejercicios y el
            // tablero suma por semana.
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'semana' => ['required', 'integer', 'between:1,53'],
            'porcentaje_inspeccion' => ['nullable', 'numeric', 'between:0,100'],
            'tecnico' => ['nullable', 'string', 'max:255'],
            'material' => ['nullable', 'string', 'max:255'],
            'norma' => ['nullable', 'string', 'max:255'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'fotos' => ['nullable', 'array'],
            'fotos.*' => ['file', 'image', 'max:10240'],

            'parametros' => ['nullable', 'array'],
            'parametros.*.clave' => ['nullable', 'string', 'max:255'],
            'parametros.*.valor' => ['nullable', 'string', 'max:255'],

            // Un informe sin rejilla no es un informe: es el encabezado de algo
            // que no se ensayó.
            'juntas' => ['required', 'array', 'min:1'],
            'juntas.*.marca' => ['required', 'string', 'max:255'],
            'juntas.*.junta' => ['required', 'string', 'max:100'],
            'juntas.*.spot' => ['nullable', 'integer', 'min:1', 'max:999'],
            'juntas.*.modulo' => ['nullable', 'string', 'max:100'],
            'juntas.*.resultado' => ['required', Rule::enum(ResultadoPnd::class)],
            'juntas.*.discontinuidad' => ['nullable', 'string', 'max:255'],
            'juntas.*.longitud_discontinuidad' => ['nullable', 'numeric', 'min:0'],
            'juntas.*.espesor' => ['nullable', 'numeric', 'min:0'],
            'juntas.*.soldador_id' => ['nullable', 'integer', 'exists:qal_soldadores,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reporte_no.required' => 'El número de reporte del laboratorio es obligatorio.',
            'reporte_no.unique' => 'Ya hay un informe capturado con ese número de reporte.',
            'metodo.required' => 'El método es obligatorio.',
            'laboratorio_id.required' => 'El laboratorio es obligatorio.',
            'qal_obra_id.required' => 'La obra es obligatoria.',
            'fecha_prueba.required' => 'La fecha de prueba es obligatoria.',
            'fecha_emision.after_or_equal' => 'El informe no puede emitirse antes de la prueba.',
            'semana.between' => 'La semana ISO va de 1 a 53.',
            'juntas.required' => 'El informe necesita al menos un punto examinado.',
            'juntas.min' => 'El informe necesita al menos un punto examinado.',
            'juntas.*.marca.required' => 'Cada renglón necesita la marca que reportó el laboratorio.',
            'juntas.*.junta.required' => 'Cada renglón necesita su referencia de junta.',
            'juntas.*.resultado.required' => 'Cada renglón necesita su resultado.',
            'pdf.mimes' => 'El informe original debe ser un PDF.',
            'pdf.max' => 'El PDF no puede pesar más de 10 MB.',
            'fotos.*.max' => 'Cada foto puede pesar hasta 10 MB.',
        ];
    }
}
