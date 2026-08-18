<?php

namespace App\Http\Requests\Admin\Alm;

use App\Enums\Alm\ClasificacionAbc;
use App\Enums\Alm\ProductoTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticuloStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.articulos.crear') ?? false;
    }

    /**
     * El código no se valida porque no se recibe: lo pone
     * `GeneradorCodigoArticulo` al guardar. Aceptarlo del formulario abriría la
     * puerta a que dos altas simultáneas eligieran el mismo.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'max:255'],
            'unidad' => ['required', 'string', 'max:20'],
            'idsteelex' => ['nullable', 'string', 'max:150'],
            'area_id' => ['nullable', 'integer', 'exists:alm_areas,id'],
            'codigo_barras' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(ProductoTipo::valores())],
            'clasificacion_abc' => ['required', Rule::in(ClasificacionAbc::valores())],
            'controla_inventario' => ['boolean'],
            'se_controla_por_pieza' => ['boolean'],
            'requiere_verificacion' => ['boolean'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'imagen' => ['nullable', 'image', 'max:5120'],
        ];
    }

    /**
     * Un insumo se gasta: seguirlo pieza por pieza no significa nada. Y lo que
     * no lleva kardex no tiene existencia que vigilar, así que el stock mínimo
     * sobra.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $tipo = ProductoTipo::tryFrom((string) $this->input('tipo'));

            if ($this->boolean('se_controla_por_pieza') && $tipo !== null && ! $tipo->admiteControlPorPieza()) {
                $validator->errors()->add(
                    'se_controla_por_pieza',
                    'Un insumo se consume: no tiene sentido seguirlo pieza por pieza.',
                );
            }

            if ($this->boolean('se_controla_por_pieza') && ! $this->boolean('controla_inventario')) {
                $validator->errors()->add(
                    'se_controla_por_pieza',
                    'Para llevar las piezas una por una, el artículo tiene que llevar kardex.',
                );
            }

            if (! $this->boolean('controla_inventario') && $this->filled('stock_minimo')) {
                $validator->errors()->add(
                    'stock_minimo',
                    'Un artículo sin kardex no tiene existencia que vigilar.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'Escribe con qué se va a reconocer el artículo.',
            'unidad.required' => 'Indica en qué se mide.',
            'tipo.required' => 'Indica si se gasta o si sale y regresa.',
            'area_id.exists' => 'Esa área ya no existe en el catálogo.',
            'imagen.image' => 'La foto debe ser una imagen.',
            'imagen.max' => 'La foto no puede pesar más de 5 MB.',
        ];
    }
}
