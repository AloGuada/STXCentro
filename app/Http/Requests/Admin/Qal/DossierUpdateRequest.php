<?php

namespace App\Http\Requests\Admin\Qal;

use App\Enums\Qal\EstatusDossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El estatus del dosier y sus notas internas.
 */
class DossierUpdateRequest extends FormRequest
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
            'estatus' => ['required', Rule::enum(EstatusDossier::class)],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
