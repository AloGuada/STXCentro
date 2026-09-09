<?php

namespace App\Http\Requests\Admin\Alm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PrestamoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.prestamos.crear') ?? false;
    }

    /**
     * Cada renglón trae `activo_id` (una pieza con serie) o sólo `articulo_id`
     * con `cantidad` (un activo por cantidad). Cuál de los dos aplica lo dice
     * el catálogo, y lo comprueba el servicio al prestar.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'almacen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'responsable_id' => ['required', 'uuid', 'exists:usuarios,id'],
            'obra_id' => ['nullable', 'integer', 'exists:obras,id'],
            'grupo_trabajo_id' => ['nullable', 'integer', 'exists:prod_grupos_trabajo,id'],
            'fecha_salida' => ['required', 'date', 'before_or_equal:today'],
            'fecha_retorno_esperada' => ['nullable', 'date', 'after_or_equal:fecha_salida'],
            'autorizado_por' => ['nullable', 'uuid', 'exists:usuarios,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'renglones' => ['required', 'array', 'min:1'],
            'renglones.*.articulo_id' => ['required', 'integer', 'exists:alm_articulos,id'],
            'renglones.*.activo_id' => ['nullable', 'integer', 'exists:alm_activos,id'],
            'renglones.*.cantidad' => ['nullable', 'numeric', 'gt:0'],
            'renglones.*.condicion_salida' => ['nullable', 'string', 'max:255'],
            'renglones.*.observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * La misma pieza no se presta dos veces en el mismo vale, y el mismo
     * activo por cantidad va en un solo renglón: dos renglones del mismo se
     * sumarían contra la misma existencia y el segundo se validaría contra un
     * disponible ya descontado.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $renglones = (array) $this->input('renglones', []);

            $piezas = array_filter(array_column($renglones, 'activo_id'));
            $cantidades = array_column(array_filter($renglones, fn ($r) => empty($r['activo_id'])), 'articulo_id');

            if (count($piezas) !== count(array_unique($piezas))) {
                $validator->errors()->add('renglones', 'Hay una pieza repetida: cada una se presta una sola vez.');
            }

            if (count($cantidades) !== count(array_unique($cantidades))) {
                $validator->errors()->add('renglones', 'Un activo por cantidad va en un solo renglón: junta las cantidades.');
            }

            if ($this->filled('obra_id') && $this->filled('grupo_trabajo_id')) {
                $validator->errors()->add('obra_id', 'El destino es una obra o un grupo de trabajo, no los dos.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required' => 'Indica de qué almacén sale.',
            'responsable_id.required' => 'Indica quién responde por lo prestado.',
            'fecha_salida.required' => 'Indica cuándo se lo llevó.',
            'fecha_salida.before_or_equal' => 'Nada se presta antes de que ocurra: la fecha no puede ser futura.',
            'fecha_retorno_esperada.after_or_equal' => 'No puede volver antes de salir.',
            'renglones.required' => 'Captura al menos una pieza o cantidad a prestar.',
            'renglones.*.cantidad.gt' => 'La cantidad tiene que ser mayor que cero.',
        ];
    }
}
