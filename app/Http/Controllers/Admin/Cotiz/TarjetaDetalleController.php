<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Enums\Cotiz\TipoCorte;
use App\Enums\Cotiz\TipoPintura;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\TarjetaFactorVincularRequest;
use App\Http\Requests\Admin\Cotiz\TarjetaRegistroManualRequest;
use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\KilosRealesCategoria;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaCategoriaKilos;
use App\Models\Cotiz\TarjetaEstructura;
use App\Models\Cotiz\TarjetaFactor;
use App\Models\Cotiz\TarjetaInsumoPrecio;
use App\Models\Cotiz\TarjetaKilosReal;
use App\Models\Cotiz\TarjetaRegistro;
use App\Services\Cotiz\FormulaEvaluator;
use App\Services\Cotiz\Variables\Validar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * Borra todos los registros de un grupo (mismo insumo) a la vez.
     */
    public function registroGrupoDestroy(Request $request, Tarjeta $tarjeta): RedirectResponse
    {
        $datos = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $tarjeta->registros()->whereIn('id', $datos['ids'])->delete();

        return back();
    }

    /**
     * Edita una fila AGRUPADA (varios tarjeta_registros del mismo insumo a la vez):
     * - cantidad: se distribuye entre los manuales proporcional a su cantidad actual.
     * - tipo_pintura: se aplica a todos los ids del grupo.
     * - validado: manuales usan su propio flag; los de generadora marcan el registro origen.
     */
    public function registroGrupo(Request $request, Tarjeta $tarjeta): RedirectResponse
    {
        $datos = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'cantidad' => ['nullable', 'numeric'],
            'tipo_pintura' => ['nullable', Rule::enum(TipoPintura::class)],
            'validado' => ['nullable', 'boolean'],
        ]);

        $registros = $tarjeta->registros()->whereIn('id', $datos['ids'])->get();
        if ($registros->isEmpty()) {
            return back();
        }

        if ($request->has('tipo_pintura') && $datos['tipo_pintura'] !== null) {
            $tarjeta->registros()->whereIn('id', $registros->pluck('id'))
                ->update(['tipo_pintura' => $datos['tipo_pintura']]);
        }

        if ($request->has('validado') && $datos['validado'] !== null) {
            $manualIds = $registros->whereNull('generadora_registro_id')->pluck('id');
            $tarjeta->registros()->whereIn('id', $manualIds)->update(['validado' => $datos['validado']]);

            $genIds = $registros->whereNotNull('generadora_registro_id')->pluck('generadora_registro_id');
            GeneradoraRegistro::query()->whereIn('id', $genIds)->update(['validado' => $datos['validado']]);
        }

        if ($request->has('cantidad')) {
            $this->distribuirCantidad($registros->whereNull('generadora_registro_id'), $datos['cantidad']);
        }

        return back();
    }

    /**
     * Distribuye una cantidad total entre registros manuales, proporcional a su cantidad
     * actual; en partes iguales si todas son 0.
     *
     * @param  \Illuminate\Support\Collection<int, TarjetaRegistro>  $manuales
     */
    private function distribuirCantidad($manuales, ?string $cantidad): void
    {
        if ($manuales->isEmpty()) {
            return;
        }
        $total = $cantidad !== null ? (float) $cantidad : null;

        if ($total === null || $manuales->count() === 1) {
            foreach ($manuales as $registro) {
                $registro->update(['cantidad' => $total]);
            }

            return;
        }

        $suma = (float) $manuales->sum(fn (TarjetaRegistro $r) => (float) ($r->cantidad ?? 0));
        foreach ($manuales as $registro) {
            $porcion = $suma > 0
                ? (float) ($registro->cantidad ?? 0) / $suma
                : 1 / $manuales->count();
            $registro->update(['cantidad' => $total * $porcion]);
        }
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
            'categoria_id' => ['nullable', 'exists:cotiz_kilos_reales_categorias,id'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'tipo_corte' => ['nullable', Rule::enum(TipoCorte::class)],
            'porcentual' => ['nullable', 'numeric'],
            'orden' => ['nullable', 'integer'],
        ]);

        $categoriaId = $datos['categoria_id'] ?? null;

        // Sin id: crear/encontrar la categoría global por descripción (como prepsim).
        if ($categoriaId === null) {
            $descripcion = trim((string) ($datos['descripcion'] ?? ''));
            if ($descripcion === '') {
                return back();
            }
            $categoria = KilosRealesCategoria::query()->firstOrCreate(
                ['descripcion' => $descripcion],
                ['tipo_corte' => $datos['tipo_corte'] ?? TipoCorte::KG->value, 'orden' => (int) KilosRealesCategoria::query()->max('orden') + 1],
            );
            $categoriaId = $categoria->id;
        }

        TarjetaCategoriaKilos::query()->firstOrCreate(
            ['tarjeta_id' => $tarjeta->id, 'categoria_id' => $categoriaId],
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

        $porcentual = $datos['porcentual'] ?? null;
        $categoriaKilos->update([
            'porcentual' => $porcentual,
            'orden' => $datos['orden'] ?? $categoriaKilos->orden,
        ]);

        // Al pasar a fila porcentual sus celdas fijas ya no se editan (se calculan) → limpiar.
        if ($porcentual !== null) {
            TarjetaKilosReal::query()
                ->where('tarjeta_id', $categoriaKilos->tarjeta_id)
                ->where('categoria_id', $categoriaKilos->categoria_id)
                ->delete();
        }

        return back();
    }

    /**
     * Cambia el tipo de corte de una categoría (catálogo global). Afecta a todas las tarjetas
     * que la usen; igual que en prepsim, se edita desde el modal de kilos reales.
     */
    public function krCategoriaTipo(Request $request, KilosRealesCategoria $categoria): RedirectResponse
    {
        $datos = $request->validate(['tipo_corte' => ['required', Rule::enum(TipoCorte::class)]]);
        $categoria->update(['tipo_corte' => $datos['tipo_corte']]);

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

    // ===== Acciones masivas =====

    /**
     * Valida todos: manuales con su propio flag; los de generadora marcan el ✓ en el
     * registro origen (M045, afecta a otras tarjetas que usen esos items).
     */
    public function validarTodas(Tarjeta $tarjeta): RedirectResponse
    {
        $tarjeta->registros()->whereNull('generadora_registro_id')->update(['validado' => true]);

        $genRegistroIds = $tarjeta->registros()
            ->whereNotNull('generadora_registro_id')
            ->pluck('generadora_registro_id');
        GeneradoraRegistro::query()->whereIn('id', $genRegistroIds)->update(['validado' => true]);

        $tarjeta->factores()->update(['validado' => true]);

        return back();
    }

    /**
     * Aplica el importe sugerido (cantidad × P.U. calculado) a todos los factores, limpiando
     * cualquier override de importe persistido para que caiga al cálculo en vivo.
     */
    public function aplicarSugerido(Tarjeta $tarjeta): RedirectResponse
    {
        $tarjeta->factores()->update(['importe' => null]);

        return back();
    }

    /**
     * Resincroniza una generadora: importa sus registros nuevos (con material y no usados en
     * otra tarjeta) y quita los importados cuya línea perdió el material. Reporta el resultado.
     */
    public function resincronizarGeneradora(Tarjeta $tarjeta, Generadora $generadora): RedirectResponse
    {
        $resultado = DB::transaction(function () use ($tarjeta, $generadora) {
            // 1) Limpieza: registros de esta generadora cuya línea ya no tiene material.
            $sinMaterialIds = $generadora->registros()->whereNull('material_origen_id')->pluck('id');
            $quitados = $tarjeta->registros()
                ->whereIn('generadora_registro_id', $sinMaterialIds)
                ->delete();

            // 2) Importar líneas nuevas con material, no vinculadas a ninguna tarjeta.
            $yaImportados = TarjetaRegistro::query()
                ->whereNotNull('generadora_registro_id')
                ->pluck('generadora_registro_id');
            $nuevos = $generadora->registros()
                ->whereNotNull('material_origen_id')
                ->whereNotIn('id', $yaImportados)
                ->pluck('id');
            $tarjeta->registros()->createMany(
                $nuevos->map(fn (int $gid) => [
                    'generadora_registro_id' => $gid,
                    'validado' => false,
                    'tipo_pintura' => TipoPintura::Auto->value,
                ])->all(),
            );

            return ['importados' => $nuevos->count(), 'quitados' => $quitados];
        });

        return back()->with('flash', [
            'message' => "Resincronizada \"{$generadora->titulo}\": {$resultado['importados']} importado(s), {$resultado['quitados']} quitado(s).",
        ]);
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
