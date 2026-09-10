<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Le da a Almacén su renglón propio por cada producto que ya guarda.
     *
     * El conjunto es **lo que Almacén de verdad toca**: la unión de los
     * `producto_id` que aparecen en sus siete tablas. No todo el catálogo, ni
     * todo lo que tiene `controla_inventario` — eso levantaría cientos de
     * artículos sin un solo movimiento. Un artículo se crea cuando hay material,
     * y para el que llegue después está la recepción, que lo crea ya ligado.
     *
     * Todos nacen con `producto_id` lleno, así que **esta migración no toma
     * ninguna decisión de emparejamiento**: el vínculo ya existe, sólo se está
     * escribiendo. Los artículos sueltos aparecen después, cuando una carga
     * inicial abra un almacén con material que Compras nunca ha comprado.
     *
     * `controla_inventario` no se copia porque del otro lado no existe: tener
     * renglón aquí es llevar kardex.
     */
    public function up(): void
    {
        $productoIds = $this->productosQueAlmacenGuarda()->diff($this->productosYaMigrados());

        if ($productoIds->isEmpty()) {
            return;
        }

        $ahora = now();

        DB::table('costos_productos')
            ->whereIn('id', $productoIds)
            ->orderBy('id')
            ->chunk(200, function ($productos) use ($ahora): void {
                DB::table('alm_articulos')->insert(
                    collect($productos)->map(fn ($p): array => [
                        'producto_id' => $p->id,
                        // Se copia el código para que el par ligado se llame
                        // igual de los dos lados: quien busca ART-00031 en
                        // Compras y en la bodega tiene que encontrar lo mismo.
                        'codigo' => $p->codigo,
                        'codigo_barras' => $p->codigo_barras,
                        'descripcion' => $p->descripcion,
                        'unidad' => $p->unidad,
                        'idsteelex' => $p->idsteelex,
                        'area_id' => $p->area_id,
                        'tipo' => $p->tipo,
                        'se_controla_por_pieza' => $p->se_controla_por_pieza,
                        'requiere_verificacion' => $p->requiere_verificacion,
                        'stock_minimo' => $p->stock_minimo,
                        'clasificacion_abc' => $p->clasificacion_abc,
                        'imagen' => $p->imagen,
                        'activo' => $p->activo,
                        'creado_por' => $p->creado_por,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ])->all()
                );
            });
    }

    /**
     * Los productos con rastro en Almacén. Se pregunta a las siete tablas y no
     * sólo a existencias porque un producto puede haber salido del todo —saldo
     * en cero, existencia borrada— y seguir teniendo kardex que explicarlo.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function productosQueAlmacenGuarda(): \Illuminate\Support\Collection
    {
        $tablas = [
            'alm_existencias',
            'alm_movimientos',
            'alm_ajuste_detalle',
            'alm_pedido_detalle',
            'alm_salida_detalle',
            'alm_transferencia_detalle',
            'alm_activos',
        ];

        return collect($tablas)
            ->flatMap(fn (string $tabla): array => DB::table($tabla)
                ->whereNotNull('producto_id')
                ->distinct()
                ->pluck('producto_id')
                ->all())
            ->unique()
            ->values();
    }

    /**
     * Los que ya tienen artículo. El índice único los rechazaría de todos modos;
     * saltarlos deja la migración recorrible sin que truene.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function productosYaMigrados(): \Illuminate\Support\Collection
    {
        return collect(DB::table('alm_articulos')->whereNotNull('producto_id')->pluck('producto_id')->all());
    }

    /**
     * Nada de Almacén apunta todavía a estos artículos —`articulo_id` llega en
     * la migración siguiente—, así que borrarlos no deja nada colgando.
     */
    public function down(): void
    {
        DB::table('alm_articulos')->whereNotNull('producto_id')->delete();
    }
};
