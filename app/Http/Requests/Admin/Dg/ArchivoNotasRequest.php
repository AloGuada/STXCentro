<?php

namespace App\Http\Requests\Admin\Dg;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class ArchivoNotasRequest extends FormRequest
{
    public function authorize(): bool
    {
        Log::info('DG.ArchivoNotasRequest authorize', [
            'url' => $this->url(),
            'method' => $this->method(),
            'content_type' => $this->header('Content-Type'),
            'content_length' => $this->header('Content-Length'),
        ]);

        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notas' => ['nullable', 'string', 'max:100000'],
        ];
    }

    protected function passedValidation(): void
    {
        Log::info('DG.ArchivoNotasRequest passed validation');
    }
}
