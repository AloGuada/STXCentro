<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * El catálogo maestro, y las dos caras que cuelgan de él.
     *
     * Hasta hoy `alm_articulos.producto_id` era anulable: un artículo podía
     * existir sin identidad de compra y emparejarse después, a mano. Ese
     * "después" es por donde se colaban los duplicados —la recepción no
     * encontraba el artículo suelto y creaba otro— y nunca obligaba a nadie a
     * ligar. Se acaba: cada producto y cada artículo apuntan a un `item`, la
     * llave es obligatoria y única en las dos tablas, y ligar deja de ser un
     * paso porque la identidad nace una sola vez.
     *
     * El relleno no decide nada: un item por cada producto (con su artículo, si
     * lo tiene) y uno más por cada artículo suelto. Cuando las dos caras
     * difieren en descripción manda la de Almacén, que fue la que se curó
     * layout por layout; Compras guarda su propio texto en cada partida y no
     * pierde nada.
     *
     * La unicidad de la descripción NO va aquí: va en la migración siguiente,
     * que se niega si todavía hay repetidos. Así esta puede correr siempre y la
     * otra dice exactamente qué falta por fusionar.
     */
    public function up(): void
    {
        $this->verificarPrecondiciones();

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable()->unique();
            $table->string('descripcion');
            $table->string('descripcion_normalizada');
            $table->string('unidad', 20);
            $table->boolean('activo')->default(true);
            $table->foreignUuid('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index('descripcion_normalizada');
        });

        Schema::table('costos_productos', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('id')->constrained('items')->restrictOnDelete();
        });

        Schema::table('alm_articulos', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('id')->constrained('items')->restrictOnDelete();
        });

        $this->poblarDesdeProductos();
        $this->poblarDesdeArticulosSueltos();
        $this->comprobar();

        Schema::table('costos_productos', function (Blueprint $table) {
            $table->unique('item_id');
            $table->foreignId('item_id')->nullable(false)->change();
        });

        Schema::table('alm_articulos', function (Blueprint $table) {
            $table->unique('item_id');
            $table->foreignId('item_id')->nullable(false)->change();
        });
    }

    /**
     * Lo único que puede tumbar el relleno, dicho con nombres antes de empezar.
     * Las dos son restricciones de `items` que las tablas de origen no tenían:
     * la unidad cabe en 20 (Compras aceptaba 30 y texto libre) y el código es
     * único entre las dos caras (cada tabla lo tenía único sólo para sí).
     * Ninguna se corrige aquí: se corrige el dato y se vuelve a migrar.
     */
    private function verificarPrecondiciones(): void
    {
        $unidadesLargas = DB::table('costos_productos')
            ->whereRaw('LENGTH(unidad) > 20')
            ->pluck('unidad', 'codigo');

        if ($unidadesLargas->isNotEmpty()) {
            throw new RuntimeException(
                'costos_productos: hay unidades de más de 20 caracteres y no caben en items.unidad. Acórtalas y vuelve a migrar: '
                .$unidadesLargas->map(fn ($u, $c): string => "{$c} => \"{$u}\"")->implode(', ')
            );
        }

        // Sólo entre activos: un sobrante que la fusión desactivó suelta su
        // producto y los dos se quedan con el mismo código a propósito. Ese
        // caso lo resuelve el relleno (ver `poblarDesdeArticulosSueltos`).
        $codigosChocan = DB::table('alm_articulos as a')
            ->join('costos_productos as p', 'p.codigo', '=', 'a.codigo')
            ->whereNotNull('a.codigo')
            ->where('a.activo', true)
            ->where('p.activo', true)
            ->where(fn ($q) => $q->whereNull('a.producto_id')->orWhereColumn('a.producto_id', '<>', 'p.id'))
            ->pluck('a.codigo');

        if ($codigosChocan->isNotEmpty()) {
            throw new RuntimeException(
                'Hay códigos que un artículo y un producto NO ligados entre sí comparten; en items el código es único. '
                .'Liga o recodifica y vuelve a migrar: '.$codigosChocan->implode(', ')
            );
        }
    }

    private function poblarDesdeProductos(): void
    {
        $ahora = now();

        $filas = DB::table('costos_productos as p')
            ->leftJoin('alm_articulos as a', 'a.producto_id', '=', 'p.id')
            ->whereNull('p.item_id')
            ->orderBy('p.id')
            ->get([
                'p.id as producto_id', 'p.codigo', 'p.descripcion', 'p.unidad', 'p.activo', 'p.creado_por',
                'a.id as articulo_id', 'a.descripcion as articulo_descripcion', 'a.activo as articulo_activo',
            ]);

        foreach ($filas as $fila) {
            $descripcion = $fila->articulo_descripcion ?? $fila->descripcion;
            // Un item vive mientras viva alguna de sus caras; los productos que
            // la fusión desactivó junto con su artículo nacen inactivos y no
            // estorban al unique de la migración siguiente.
            $activo = (bool) $fila->activo || ($fila->articulo_id !== null && (bool) $fila->articulo_activo);

            $itemId = DB::table('items')->insertGetId([
                'codigo' => $fila->codigo,
                'descripcion' => $descripcion,
                'descripcion_normalizada' => $this->normalizar($descripcion),
                'unidad' => $fila->unidad,
                'activo' => $activo,
                'creado_por' => $fila->creado_por,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('costos_productos')->where('id', $fila->producto_id)->update(['item_id' => $itemId]);

            if ($fila->articulo_id !== null) {
                DB::table('alm_articulos')->where('id', $fila->articulo_id)->update(['item_id' => $itemId]);
            }
        }
    }

    private function poblarDesdeArticulosSueltos(): void
    {
        $ahora = now();

        $articulos = DB::table('alm_articulos')->whereNull('item_id')->orderBy('id')->get();

        foreach ($articulos as $articulo) {
            // Un sobrante de fusión ya desactivado comparte código con el
            // producto que soltó; el código se queda en el item del producto y
            // este entra sin él. Entre activos no pasa: lo detiene la
            // verificación inicial.
            $codigoLibre = $articulo->codigo !== null
                && ! DB::table('items')->where('codigo', $articulo->codigo)->exists();

            $itemId = DB::table('items')->insertGetId([
                'codigo' => $codigoLibre ? $articulo->codigo : null,
                'descripcion' => $articulo->descripcion,
                'descripcion_normalizada' => $this->normalizar($articulo->descripcion),
                'unidad' => $articulo->unidad,
                'activo' => (bool) $articulo->activo,
                'creado_por' => $articulo->creado_por,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('alm_articulos')->where('id', $articulo->id)->update(['item_id' => $itemId]);
        }
    }

    /**
     * Ningún renglón sin item y ningún item compartido entre dos productos o dos
     * artículos. Es aritmética; si no da, la excepción revierte todo (el DDL es
     * transaccional en Postgres y en SQLite).
     */
    private function comprobar(): void
    {
        foreach (['costos_productos', 'alm_articulos'] as $tabla) {
            $sinItem = DB::table($tabla)->whereNull('item_id')->count();

            if ($sinItem > 0) {
                throw new RuntimeException("{$tabla}: {$sinItem} renglones se quedaron sin item.");
            }

            $renglones = DB::table($tabla)->count();
            $items = DB::table($tabla)->distinct()->count('item_id');

            if ($renglones !== $items) {
                throw new RuntimeException("{$tabla}: {$renglones} renglones cayeron en {$items} items; el vínculo dejó de ser uno a uno.");
            }
        }
    }

    /**
     * Copia de `CatalogoMaestro::normalizar()`: una migración no depende de
     * código que puede cambiar después de que corrió.
     */
    private function normalizar(string $descripcion): string
    {
        $plana = Str::ascii(mb_strtolower(trim($descripcion)));

        return trim((string) preg_replace('/\s+/', ' ', $plana));
    }

    public function down(): void
    {
        Schema::table('alm_articulos', function (Blueprint $table) {
            $table->dropUnique(['item_id']);
            $table->dropConstrainedForeignId('item_id');
        });

        Schema::table('costos_productos', function (Blueprint $table) {
            $table->dropUnique(['item_id']);
            $table->dropConstrainedForeignId('item_id');
        });

        Schema::dropIfExists('items');
    }
};
