<?php

namespace App\Http\Requests\Admin\Alm;

use App\Models\Alm\Pedido;
use App\Models\Alm\PedidoDetalle;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PrestamoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.prestamos.crear') ?? false;
    }

    /**
     * Cada renglón trae `activo_id` (una pieza con serie) o sólo `articulo_id`
     * con `cantidad` (un activo por cantidad). Cuál de los dos aplica lo dice
     * el catálogo, y lo comprueba el servicio al prestar.
     *
     * Responde un supervisor —quien tiene `alm.pedidos.supervisar`—. Con
     * pedido no se revisa aquí: responde el del pedido, y ése lo fija el
     * controlador.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'almacen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'pedido_id' => ['nullable', 'integer', 'exists:alm_pedidos,id'],
            'responsable_id' => [
                'required', 'uuid',
                Rule::when(
                    $this->filled('pedido_id'),
                    ['exists:usuarios,id'],
                    [Rule::exists('usuarios', 'id')->where(fn ($q) => $q->whereIn(
                        'id',
                        Usuario::query()->supervisoresDeAlmacen()->select('id'),
                    ))],
                ),
            ],
            'obra_id' => ['nullable', 'integer', 'exists:obras,id'],
            'grupo_trabajo_id' => ['nullable', 'integer', 'exists:prod_grupos_trabajo,id'],
            'fecha_salida' => ['required', 'date', 'before_or_equal:today'],
            'fecha_retorno_esperada' => ['nullable', 'date', 'after_or_equal:fecha_salida'],
            'autorizado_por' => ['nullable', 'uuid', 'exists:usuarios,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'renglones' => ['required', 'array', 'min:1'],
            'renglones.*.articulo_id' => ['required', 'integer', 'exists:alm_articulos,id'],
            'renglones.*.activo_id' => ['nullable', 'integer', 'exists:alm_activos,id'],
            'renglones.*.pedido_detalle_id' => ['nullable', 'integer', 'exists:alm_pedido_detalle,id'],
            'renglones.*.cantidad' => ['nullable', 'numeric', 'gt:0'],
            'renglones.*.condicion_salida' => ['nullable', 'string', 'max:255'],
            'renglones.*.observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * La misma pieza no se presta dos veces en el mismo vale, y el mismo
     * activo por cantidad va en un solo renglón: dos renglones del mismo se
     * sumarían contra la misma existencia y el segundo se validaría contra un
     * disponible ya descontado.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $renglones = (array) $this->input('renglones', []);

            $piezas = array_filter(array_column($renglones, 'activo_id'));
            $cantidades = array_column(array_filter($renglones, fn ($r) => empty($r['activo_id'])), 'articulo_id');

            if (count($piezas) !== count(array_unique($piezas))) {
                $validator->errors()->add('renglones', 'Hay una pieza repetida: cada una se presta una sola vez.');
            }

            if (count($cantidades) !== count(array_unique($cantidades))) {
                $validator->errors()->add('renglones', 'Un activo por cantidad va en un solo renglón: junta las cantidades.');
            }

            if ($this->filled('obra_id') && $this->filled('grupo_trabajo_id')) {
                $validator->errors()->add('obra_id', 'El destino es una obra o un grupo de trabajo, no los dos.');
            }

            $this->validarPedido($validator);
        });
    }

    /**
     * Si surte un pedido, el pedido tiene que ser de este almacén y seguir
     * surtible, y cada renglón amarrado tiene que ser de ese pedido, de ese
     * artículo, y no entregar más de lo que le falta. Prestar de más contra un
     * pedido lo dejaría «surtido» con herramienta que nadie pidió.
     */
    private function validarPedido(Validator $validator): void
    {
        if (! $this->filled('pedido_id')) {
            return;
        }

        $pedido = Pedido::with('detalles.articulo')->find($this->integer('pedido_id'));

        if ($pedido === null) {
            return;
        }

        if ((int) $pedido->almacen_id !== $this->integer('almacen_id')) {
            $validator->errors()->add('pedido_id', 'Ese pedido es de otro almacén.');
        }

        if (! $pedido->estatus->admiteSurtido()) {
            $validator->errors()->add('pedido_id', 'Ese pedido ya no se puede surtir: está '.$pedido->estatus->etiqueta().'.');
        }

        $porRenglon = [];

        foreach ((array) $this->input('renglones', []) as $i => $renglon) {
            $detalleId = (int) ($renglon['pedido_detalle_id'] ?? 0);

            if ($detalleId === 0) {
                continue;
            }

            /** @var PedidoDetalle|null $detalle */
            $detalle = $pedido->detalles->firstWhere('id', $detalleId);

            if ($detalle === null) {
                $validator->errors()->add("renglones.{$i}.pedido_detalle_id", 'Ese renglón no es de este pedido.');

                continue;
            }

            if ((int) $detalle->articulo_id !== (int) ($renglon['articulo_id'] ?? 0)) {
                $validator->errors()->add("renglones.{$i}.articulo_id", 'El artículo no coincide con el renglón del pedido.');
            }

            $cantidad = ! empty($renglon['activo_id']) ? 1.0 : (float) ($renglon['cantidad'] ?? 0);
            $porRenglon[$detalleId] = ($porRenglon[$detalleId] ?? 0.0) + $cantidad;

            if ($porRenglon[$detalleId] > $detalle->pendiente() + (float) config('costos.epsilon_cantidad')) {
                $validator->errors()->add(
                    "renglones.{$i}.cantidad",
                    "Al pedido sólo le faltan {$detalle->pendiente()} de este artículo.",
                );
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required' => 'Indica de qué almacén sale.',
            'responsable_id.required' => 'Indica quién responde por lo prestado.',
            'responsable_id.exists' => 'Responde por lo prestado un supervisor de almacén.',
            'fecha_salida.required' => 'Indica cuándo se lo llevó.',
            'fecha_salida.before_or_equal' => 'Nada se presta antes de que ocurra: la fecha no puede ser futura.',
            'fecha_retorno_esperada.after_or_equal' => 'No puede volver antes de salir.',
            'renglones.required' => 'Captura al menos una pieza o cantidad a prestar.',
            'renglones.*.cantidad.gt' => 'La cantidad tiene que ser mayor que cero.',
        ];
    }
}
