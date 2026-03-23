<?php

namespace App\Http\Requests\Api\Cal;

use Illuminate\Foundation\Http\FormRequest;

class PiezaPlanoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return [
                'version' => ['required', 'integer', 'min:1'],
            ];
        }

        return [
            'pieza_id' => ['required', 'exists:cal_piezas,id'],
            'version' => ['required', 'integer', 'min:1'],
            'pdf_revision' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'plano_normal' => ['nullable', 'file', 'max:10240'],
            'dwg' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
