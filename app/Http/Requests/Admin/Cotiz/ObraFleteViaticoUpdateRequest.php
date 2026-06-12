<?php

namespace App\Http\Requests\Admin\Cotiz;

use App\Enums\Cotiz\GrupoFlete;
use App\Services\Cotiz\FormulaEvaluator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ObraFleteViaticoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'grupo' => ['sometimes', 'required', Rule::enum(GrupoFlete::class)],
            'orden' => ['sometimes', 'integer', 'min:0'],
            'concepto' => ['sometimes', 'required', 'string', 'max:255'],
            'unidad' => ['nullable', 'string', 'max:255'],
            'cantidad' => ['sometimes', 'numeric'],
            'p_unit' => ['sometimes', 'numeric'],
            'notas' => ['nullable', 'string', 'max:255'],
            'clave' => ['nullable', 'string', 'max:255'],
            'formula_cantidad' => ['nullable', 'string', $this->reglaFormula()],
            'formula_p_unit' => ['nullable', 'string', $this->reglaFormula()],
        ];
    }

    /**
     * Valida la sintaxis de una fórmula con el evaluador aislado del módulo.
     */
    private function reglaFormula(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }
            $error = app(FormulaEvaluator::class)->validar($value);
            if ($error !== null) {
                $fail("Fórmula inválida: {$error}");
            }
        };
    }
}
