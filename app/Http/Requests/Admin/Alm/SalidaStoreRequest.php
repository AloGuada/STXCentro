<?php

namespace App\Http\Requests\Admin\Alm;

use App\Models\Alm\Almacen;
use App\Models\Alm\Pedido;
use App\Models\Alm\PedidoDetalle;
use App\Services\Alm\AlmacenLedger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalidaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.salidas.crear') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'almacen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'pedido_id' => ['nullable', 'integer', 'exists:alm_pedidos,id'],
            'departamento_id' => ['nullable', 'integer', 'exists:departamentos,id'],
            'obra_destino_id' => ['nullable', 'integer', 'exists:obras,id'],
            'grupo_trabajo_id' => ['nullable', 'integer', 'exists:prod_grupos_trabajo,id'],
            'solicitante_id' => ['nullable', 'uuid', 'exists:usuarios,id'],
            'recibe_nombre' => ['required', 'string', 'max:255'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.articulo_id' => [
                'required', 'integer',
                // Del catalogo de Almacen. Ya no se pregunta si lleva kardex:
                // tener renglon ahi es llevarlo.
                Rule::exists('alm_articulos', 'id'),
            ],
            'detalles.*.pedido_detalle_id' => ['nullable', 'integer', 'exists:alm_pedido_detalle,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Aquí sí es error sacar de más: a diferencia del pedido, este material sale
     * de verdad.
     *
     * Se valida antes de abrir la transacción para poder devolver **todos** los
     * renglones cortos de un golpe, en vez de rebotar el formulario de uno en
     * uno; el ledger vuelve a comprobarlo con la fila bloqueada, que es lo que
     * de verdad protege el saldo contra dos salidas simultáneas.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarExistencia($validator);
            $this->validarPedido($validator);
            $this->validarDepartamento($validator);
        });
    }

    /**
     * En planta el material no se va a otro domicilio: se consume aqui mismo.
     * Si ademas no hay pedido que diga quien lo pidio, el departamento es lo
     * unico que dice a quien cargarle el consumo, y sin el la salida se pierde
     * en "almacen central".
     *
     * Se resuelve contra el almacen guardado y no contra lo que mande la
     * pantalla: quien decide si es de planta es el catalogo.
     */
    private function validarDepartamento(Validator $validator): void
    {
        if ($this->filled('departamento_id') || $this->filled('pedido_id')) {
            return;
        }

        $almacen = Almacen::find($this->integer('almacen_id'));

        if ($almacen === null || ! $almacen->esCentral()) {
            return;
        }

        $validator->errors()->add(
            'departamento_id',
            'Una salida directa de planta tiene que decir a qué departamento se le carga.',
        );
    }

    private function validarExistencia(Validator $validator): void
    {
        $ledger = app(AlmacenLedger::class);
        $almacenId = $this->integer('almacen_id');
        $porArticulo = [];

        // Se acumula por artículo: dos renglones del mismo tornillo se llevan
        // del mismo saldo, y validarlos por separado dejaría pasar el doble.
        foreach ((array) $this->input('detalles', []) as $i => $detalle) {
            $articuloId = (int) ($detalle['articulo_id'] ?? 0);
            $porArticulo[$articuloId]['cantidad'] = ($porArticulo[$articuloId]['cantidad'] ?? 0)
                + (float) ($detalle['cantidad'] ?? 0);
            $porArticulo[$articuloId]['renglones'][] = $i;
        }

        $epsilon = (float) config('costos.epsilon_cantidad');

        foreach ($porArticulo as $articuloId => $datos) {
            $disponible = $ledger->disponible($almacenId, $articuloId);

            if ($datos['cantidad'] <= $disponible + $epsilon) {
                continue;
            }

            foreach ($datos['renglones'] as $i) {
                $validator->errors()->add(
                    "detalles.{$i}.cantidad",
                    "No hay existencia suficiente: hay {$disponible} y se están sacando {$datos['cantidad']}.",
                );
            }
        }
    }

    /**
     * Si la salida surte un pedido, tiene que ser uno que el almacén todavía
     * deba, del mismo almacén, y ningún renglón puede entregar más de lo que le
     * falta — surtir de más dejaría un pedido «sobre-surtido» que ya nadie sabe
     * cerrar.
     */
    private function validarPedido(Validator $validator): void
    {
        if (! $this->filled('pedido_id')) {
            return;
        }

        $pedido = Pedido::find($this->integer('pedido_id'));

        if ($pedido === null) {
            return;
        }

        if ((int) $pedido->almacen_id !== $this->integer('almacen_id')) {
            $validator->errors()->add('pedido_id', 'Ese pedido es de otro almacén.');

            return;
        }

        if (! $pedido->estatus->admiteSurtido()) {
            $validator->errors()->add('pedido_id', 'Ese pedido ya no admite surtido.');

            return;
        }

        // Un pedido de obra se surte con transferencia: el material va a otro
        // domicilio y la obra tiene que confirmar que llegó.
        if ($pedido->seSurteConTransferencia()) {
            $validator->errors()->add(
                'pedido_id',
                'Ese pedido va a una obra: se surte con una transferencia, no con una salida.',
            );

            return;
        }

        $epsilon = (float) config('costos.epsilon_cantidad');

        foreach ((array) $this->input('detalles', []) as $i => $detalle) {
            if (empty($detalle['pedido_detalle_id'])) {
                continue;
            }

            $renglon = PedidoDetalle::find($detalle['pedido_detalle_id']);

            if ($renglon === null || (int) $renglon->pedido_id !== (int) $pedido->id) {
                $validator->errors()->add("detalles.{$i}.pedido_detalle_id", 'Ese renglón no es de este pedido.');

                continue;
            }

            if ((float) ($detalle['cantidad'] ?? 0) > $renglon->pendiente() + $epsilon) {
                $validator->errors()->add(
                    "detalles.{$i}.cantidad",
                    "A ese renglón del pedido sólo le faltan {$renglon->pendiente()}.",
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
            'almacen_id.required' => 'Indica de qué almacén sale el material.',
            'fecha.before_or_equal' => 'La salida no puede ser de un día que no ha llegado: el material sale hoy o ya salió.',
            'recibe_nombre.required' => 'Escribe quién recibe: es quien firma el vale.',
            'detalles.required' => 'Captura al menos un artículo.',
            'detalles.*.cantidad.gt' => 'Entregar cero no es entregar.',
            'detalles.*.articulo_id.exists' => 'Ese artículo no está en el catálogo del almacén.',
        ];
    }
}
