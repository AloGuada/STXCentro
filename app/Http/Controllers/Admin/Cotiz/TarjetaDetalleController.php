<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Enums\Cotiz\TipoPintura;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\TarjetaFactorVincularRequest;
use App\Http\Requests\Admin\Cotiz\TarjetaRegistroManualRequest;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaCategoriaKilos;
use App\Models\Cotiz\TarjetaEstructura;
use App\Models\Cotiz\TarjetaFactor;
use App\Models\Cotiz\TarjetaInsumoPrecio;
use App\Models\Cotiz\TarjetaRegistro;
use App\Services\Cotiz\FormulaEvaluator;
use App\Services\Cotiz\Variables\Validar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Subrecursos de la grilla de la tarjeta (Fase 3c-2): registros manuales, P.U. por tarjeta,
 * factores, estructuras y matriz de kilos reales. El recálculo es siempre autoritativo en
 * el backend (TarjetaCalculator), así que estos endpoints solo mutan los inputs.
 */
class TarjetaDetalleController extends Controller
{
    // ===== Registros =====

    public function registroStore(TarjetaRegistroManualRequest $request, Tarjeta $tarjeta): RedirectResponse
    {
        $tarjeta->registros()->create([
            'insumo_id' => $request->integer('insumo_id'),
            'cantidad' => $request->input('cantidad'),
            'validado' => false,
            'tipo_pintura' => TipoPintura::Auto->value,
        ]);

        return back();
    }

    public function registroUpdate(Request $request, TarjetaRegistro $tarjetaRegistro): RedirectResponse
    {
        $datos = $request->validate([
            'cantidad' => ['nullable', 'numeric'],
            'tipo_pintura' => ['nullable', Rule::enum(TipoPintura::class)],
            'validado' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('tipo_pintura', $datos) && $datos['tipo_pintura'] !== null) {
            $tarjetaRegistro->tipo_pintura = $datos['tipo_pintura'];
        }
        // La cantidad solo aplica a registros manuales (los de generadora la derivan).
        if ($tarjetaRegistro->esManual() && array_key_exists('cantidad', $datos)) {
            $tarjetaRegistro->cantidad = $datos['cantidad'];
        }

        if (array_key_exists('validado', $datos) && $datos['validado'] !== null) {
            // M045: el ✓ de un registro de generadora vive en el registro origen.
            if ($tarjetaRegistro->esManual()) {
                $tarjetaRegistro->validado = $datos['validado'];
            } else {
                $tarjetaRegistro->generadoraRegistro?->update(['validado' => $datos['validado']]);
            }
        }

        $tarjetaRegistro->save();

        return back();
    }

    public function registroDestroy(TarjetaRegistro $tarjetaRegistro): RedirectResponse
    {
        $tarjetaRegistro->delete();

        return back();
    }

    // ===== P.U. por tarjeta (M040) =====

    public function precioUpdate(Request $request, Tarjeta $tarjeta, Insumo $insumo): RedirectResponse
    {
        $datos = $request->validate([
            'precio_unitario' => ['nullable', 'numeric', 'min:0'],
        ]);

        $precio = $datos['precio_unitario'] ?? null;

        if ($precio === null || $precio === '') {
            // Limpiar el override → cae al P.U. de obra/global.
            TarjetaInsumoPrecio::query()
                ->where('tarjeta_id', $tarjeta->id)
                ->where('insumo_id', $insumo->id)
                ->delete();

            return back();
        }

        TarjetaInsumoPrecio::query()->updateOrCreate(
            ['tarjeta_id' => $tarjeta->id, 'insumo_id' => $insumo->id],
            ['precio_unitario' => $precio],
        );

        return back();
    }

    // ===== Factores =====

    public function factorStore(TarjetaFactorVincularRequest $request, Tarjeta $tarjeta): RedirectResponse
    {
        TarjetaFactor::query()->firstOrCreate([
            'tarjeta_id' => $tarjeta->id,
            'factor_id' => $request->integer('factor_id'),
        ], [
            'validado' => false,
        ]);

        return back();
    }

    public function factorUpdate(Request $request, TarjetaFactor $tarjetaFactor): RedirectResponse
    {
        $datos = $request->validate([
            'formula_override' => ['nullable', 'string', 'max:255'],
            'cantidad_manual' => ['nullable', 'numeric'],
            'importe' => ['nullable', 'numeric'],
            'validado' => ['nullable', 'boolean'],
        ]);

        $tarjetaFactor->fill([
            'formula_override' => ($datos['formula_override'] ?? '') === '' ? null : $datos['formula_override'],
            'cantidad_manual' => $datos['cantidad_manual'] ?? null,
            'importe' => $datos['importe'] ?? null,
            'validado' => $datos['validado'] ?? $tarjetaFactor->validado,
        ]);
        $tarjetaFactor->save();

        return back();
    }

    public function factorDestroy(TarjetaFactor $tarjetaFactor): RedirectResponse
    {
        $tarjetaFactor->delete();

        return back();
    }

    // ===== Estructuras =====

    public function estructuraStore(Request $request, Tarjeta $tarjeta): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'orden' => ['nullable', 'integer'],
        ]);

        $tarjeta->estructuras()->create($datos);

        return back();
    }

    public function estructuraUpdate(Request $request, TarjetaEstructura $tarjetaEstructura): RedirectResponse
    {
        $tarjetaEstructura->update($request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'orden' => ['nullable', 'integer'],
        ]));

        return back();
    }

    public function estructuraDestroy(TarjetaEstructura $tarjetaEstructura): RedirectResponse
    {
        $tarjetaEstructura->delete();

        return back();
    }

    // ===== Análisis de kilos reales =====

    public function krCategoriaStore(Request $request, Tarjeta $tarjeta): RedirectResponse
    {
        $datos = $request->validate([
            'categoria_id' => ['required', 'exists:cotiz_kilos_reales_categorias,id'],
            'porcentual' => ['nullable', 'numeric'],
            'orden' => ['nullable', 'integer'],
        ]);

        TarjetaCategoriaKilos::query()->firstOrCreate(
            ['tarjeta_id' => $tarjeta->id, 'categoria_id' => $datos['categoria_id']],
            ['porcentual' => $datos['porcentual'] ?? null, 'orden' => $datos['orden'] ?? 0],
        );

        return back();
    }

    public function krCategoriaUpdate(Request $request, TarjetaCategoriaKilos $categoriaKilos): RedirectResponse
    {
        $datos = $request->validate([
            'porcentual' => ['nullable', 'numeric'],
            'orden' => ['nullable', 'integer'],
        ]);

        $categoriaKilos->update([
            'porcentual' => $datos['porcentual'] ?? null,
            'orden' => $datos['orden'] ?? $categoriaKilos->orden,
        ]);

        return back();
    }

    public function krCategoriaDestroy(TarjetaCategoriaKilos $categoriaKilos): RedirectResponse
    {
        $categoriaKilos->delete();

        return back();
    }

    public function krCeldaUpsert(Request $request, Tarjeta $tarjeta): RedirectResponse
    {
        $datos = $request->validate([
            'categoria_id' => ['required', 'exists:cotiz_kilos_reales_categorias,id'],
            'estructura_id' => ['required', 'exists:cotiz_tarjeta_estructuras,id'],
            'kilos' => ['required', 'numeric'],
        ]);

        $tarjeta->kilosReales()->updateOrCreate(
            ['categoria_id' => $datos['categoria_id'], 'estructura_id' => $datos['estructura_id']],
            ['kilos' => $datos['kilos']],
        );

        return back();
    }

    // ===== Validación de fórmula (editor) =====

    public function validarFormula(Request $request, FormulaEvaluator $evaluator): JsonResponse
    {
        $formula = (string) $request->input('formula', '');

        return response()->json([
            'error' => Validar::formula($formula, $evaluator),
        ]);
    }
}
