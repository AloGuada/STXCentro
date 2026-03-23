<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ApiFormRequest extends FormRequest
{
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        throw new \Illuminate\Validation\ValidationException(
            $validator,
            response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
