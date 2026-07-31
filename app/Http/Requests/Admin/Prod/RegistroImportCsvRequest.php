<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class RegistroImportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // El export de planta trae todos los eventos del periodo y ronda los
            // 5 MB, así que el tope va holgado.
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'fecha' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'csv_file.required' => 'El archivo CSV es obligatorio.',
            'csv_file.mimes' => 'El archivo debe ser de tipo CSV.',
            'csv_file.max' => 'El archivo no debe superar los 10MB.',
            'fecha.required' => 'La fecha es obligatoria.',
        ];
    }
}
