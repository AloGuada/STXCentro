<?php

namespace App\Http\Requests\Admin\Alm;

use App\Enums\Alm\DocumentoAlm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FirmasDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.aprobaciones.configurar') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'documentos' => ['present', 'array'],
            'documentos.*.documento' => ['required', Rule::enum(DocumentoAlm::class)],
            'documentos.*.firmas' => ['present', 'array', 'max:6'],
            'documentos.*.firmas.*.rotulo' => ['required', 'string', 'max:60'],
            'documentos.*.firmas.*.fuente' => ['nullable', 'string', 'max:30'],
            'documentos.*.firmas.*.usuarios' => ['present', 'array', 'max:5'],
            'documentos.*.firmas.*.usuarios.*' => ['uuid', Rule::exists('usuarios', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documentos.*.firmas.max' => 'Un formato no lleva más de 6 firmas: no caben en la hoja.',
            'documentos.*.firmas.*.rotulo.required' => 'Cada raya necesita decir de qué es la firma.',
            'documentos.*.firmas.*.rotulo.max' => 'El rótulo de la firma no puede pasar de 60 caracteres.',
            'documentos.*.firmas.*.usuarios.max' => 'Máximo 5 usuarios por firma: más no caben sobre la raya.',
        ];
    }

    /**
     * La fuente tiene que ser una de las que ese documento sabe dar. Se valida
     * aquí y no con una regla suelta porque depende del documento del renglón.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            foreach ((array) $this->input('documentos', []) as $i => $documento) {
                $tipo = DocumentoAlm::tryFrom((string) ($documento['documento'] ?? ''));

                if ($tipo === null) {
                    continue;
                }

                foreach ((array) ($documento['firmas'] ?? []) as $j => $firma) {
                    $fuente = $firma['fuente'] ?? null;

                    if ($fuente !== null && ! array_key_exists($fuente, $tipo->fuentes())) {
                        $validator->errors()->add(
                            "documentos.{$i}.firmas.{$j}.fuente",
                            "El {$tipo->etiqueta()} no sabe quién es «{$fuente}».",
                        );
                    }
                }
            }
        });
    }
}
