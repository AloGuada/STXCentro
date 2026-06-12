<?php

namespace App\Http\Requests\Admin\Cotiz;

use App\Enums\Cotiz\GrupoFlete;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ObraFleteViaticoStoreRequest extends FormRequest
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
            'grupo' => ['required', Rule::enum(GrupoFlete::class)],
            'concepto' => ['nullable', 'string', 'max:255'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
