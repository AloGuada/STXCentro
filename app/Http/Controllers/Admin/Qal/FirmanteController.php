<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Enums\Qal\OrigenFirmante;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\FirmanteRequest;
use App\Models\Qal\Firmante;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * El orden de firma de los formatos de Calidad. Se consulta en la pestaña
 * Firmantes de Catálogos.
 *
 * A diferencia de los catálogos, aquí sí se borra: ningún registro cita a un
 * firmante, los formatos se generan al pedirlos y toman el arreglo de ese
 * momento.
 */
class FirmanteController extends Controller
{
    public function store(FirmanteRequest $request): RedirectResponse
    {
        Firmante::create($this->datos($request) + [
            'orden' => (int) Firmante::query()->max('orden') + 1,
        ]);

        return back()->with('success', 'Firmante añadido.');
    }

    public function update(FirmanteRequest $request, Firmante $firmante): RedirectResponse
    {
        $firmante->update($this->datos($request));

        return back()->with('success', 'Firmante actualizado.');
    }

    public function destroy(Firmante $firmante): RedirectResponse
    {
        $firmante->delete();
        $this->renumerar(Firmante::query()->orderBy('orden')->pluck('id')->all());

        return back()->with('success', 'Firmante quitado.');
    }

    /**
     * Recibe los ids en el orden nuevo. Tienen que venir todos: un orden que
     * deja fuera a alguno no dice dónde queda ese.
     */
    public function reordenar(Request $request): RedirectResponse
    {
        $validos = Firmante::query()->pluck('id')->all();

        $datos = $request->validate([
            'ids' => ['required', 'array', 'size:'.count($validos)],
            'ids.*' => ['integer', 'distinct', Rule::in($validos)],
        ], [
            'ids.size' => 'La lista cambió mientras la ordenabas; recarga la pantalla.',
        ]);

        $this->renumerar($datos['ids']);

        return back();
    }

    /**
     * @return array{etiqueta: string, cargo: string, origen: string, usuario_id: string|null}
     */
    private function datos(FirmanteRequest $request): array
    {
        $origen = OrigenFirmante::from($request->validated('origen'));

        return [
            'etiqueta' => $request->validated('etiqueta'),
            'cargo' => $request->validated('cargo'),
            'origen' => $origen->value,
            'usuario_id' => $origen === OrigenFirmante::Usuario ? $request->validated('usuario_id') : null,
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    private function renumerar(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $posicion => $id) {
                Firmante::query()->whereKey($id)->update(['orden' => $posicion + 1]);
            }
        });
    }
}
