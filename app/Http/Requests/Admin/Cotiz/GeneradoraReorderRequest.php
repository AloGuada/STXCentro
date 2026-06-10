<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class GeneradoraReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Acepta dos formas:
     * - `['orden' => [id1, id2, ...]]` (lista ordenada de ids)
     * - `['orden' => [['id' => 1, 'orden' => 0], ...]]` (pares id/orden)
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'orden' => ['required', 'array', 'min:1'],
            'orden.*' => ['required'],
        ];
    }

    /**
     * Normaliza la entrada a una lista de pares ['id' => int, 'orden' => int].
     *
     * @return array<int, array{id: int, orden: int}>
     */
    public function pares(): array
    {
        $pares = [];

        foreach ($this->validated('orden') as $index => $item) {
            if (is_array($item)) {
                $pares[] = [
                    'id' => (int) $item['id'],
                    'orden' => (int) ($item['orden'] ?? $index),
                ];
            } else {
                $pares[] = [
                    'id' => (int) $item,
                    'orden' => $index,
                ];
            }
        }

        return $pares;
    }
}
