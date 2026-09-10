<?php

use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Costos\Producto;
use Illuminate\Support\Facades\DB;

describe('la puerta entre las dos fases', function () {
    test('pasa cuando cada renglón con producto tiene su artículo', function () {
        $producto = Producto::factory()->create();
        $articulo = Articulo::factory()->create(['producto_id' => $producto->id]);

        Existencia::factory()->create([
            'almacen_id' => Almacen::factory(),
            'producto_id' => $producto->id,
            'articulo_id' => $articulo->id,
        ]);

        $this->artisan('alm:verificar-articulos')
            ->expectsOutputToContain('Las siete cuadran')
            ->assertSuccessful();
    });

    test('falla si un renglón se quedó sin artículo', function () {
        // Es el escenario que se quiere cazar. Se escribe en crudo porque por
        // Eloquent ya no se puede llegar a él: el trait llena la columna sola.
        // Así llegaría de verdad —un insert masivo, una consulta a mano, un
        // flujo que no pasa por el modelo—, que es contra lo que sirve la
        // puerta: contra lo que el trait no alcanza a cubrir.
        $producto = Producto::factory()->create();

        DB::table('alm_existencias')->insert([
            'almacen_id' => Almacen::factory()->create()->id,
            'producto_id' => $producto->id,
            'articulo_id' => null,
            'cantidad' => 0,
            'costo_promedio' => 0,
            'valor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('alm:verificar-articulos')->assertFailed();
    });

    test('falla si dos productos cayeron en el mismo artículo', function () {
        // El vínculo dejó de ser uno a uno: dos insumos distintos comparten
        // existencia y el kardex deja de cuadrar contra Compras.
        $articulo = Articulo::factory()->create();
        $almacen = Almacen::factory()->create();

        foreach (Producto::factory()->count(2)->create() as $producto) {
            // Se escribe en crudo a propósito: el unique de la tabla lo
            // impediría por almacén, así que se usan dos almacenes distintos
            // para llegar al estado que la comprobación tiene que cazar.
            DB::table('alm_existencias')->insert([
                'almacen_id' => Almacen::factory()->create()->id,
                'producto_id' => $producto->id,
                'articulo_id' => $articulo->id,
                'cantidad' => 0,
                'costo_promedio' => 0,
                'valor' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->artisan('alm:verificar-articulos')->assertFailed();
    });

    test('un almacén recién abierto pasa, aunque casi todo esté suelto', function () {
        // El escenario real tras cargar los layouts: cientos de renglones sin
        // producto y unos pocos con el suyo. Comparar productos contra
        // artículos sobre TODOS los renglones daba siempre desigual y marcaba
        // como rota una base que estaba perfecta.
        $almacen = Almacen::factory()->create();

        foreach (Articulo::factory()->sinLigar()->count(20)->create() as $articulo) {
            Existencia::factory()->create([
                'almacen_id' => $almacen->id,
                'producto_id' => null,
                'articulo_id' => $articulo->id,
            ]);
        }

        // Y uno que sí viene de Compras, como los que ya existían.
        $producto = Producto::factory()->create();
        Existencia::factory()->create([
            'almacen_id' => Almacen::factory()->create()->id,
            'producto_id' => $producto->id,
            'articulo_id' => Articulo::factory()->create(['producto_id' => $producto->id])->id,
        ]);

        $this->artisan('alm:verificar-articulos')
            ->expectsOutputToContain('Las siete cuadran')
            ->assertSuccessful();
    });

    test('un artículo sin ligar no es un problema, sólo se informa', function () {
        // Material real que todavía no tiene identidad de compra: es el estado
        // normal de lo que abre un almacén.
        Articulo::factory()->sinLigar()->create();

        $this->artisan('alm:verificar-articulos')
            ->expectsOutputToContain('esperando emparejarse')
            ->assertSuccessful();
    });
});
