<?php

use App\Models\Alm\Articulo;
use App\Models\Costos\Producto;
use App\Models\Item;
use Database\Seeders\Alm\AltaArticulosPendientesSeeder;

it('da de alta los 11 materiales con item, producto y artículo, y no duplica al repetirse', function (): void {
    Producto::factory()->create(['descripcion' => 'TRAPO', 'unidad' => 'KG']);

    $this->seed(AltaArticulosPendientesSeeder::class);

    expect(Item::query()->count())->toBe(11)
        ->and(Producto::query()->count())->toBe(11)
        ->and(Articulo::query()->count())->toBe(11)
        ->and(Item::query()->whereNull('codigo')->count())->toBe(0)
        ->and(Articulo::query()->where('descripcion', 'TRAPO')->sole()->producto_id)
        ->toBe(Producto::query()->where('descripcion', 'TRAPO')->sole()->id);

    $this->seed(AltaArticulosPendientesSeeder::class);

    expect(Item::query()->count())->toBe(11)
        ->and(Articulo::query()->count())->toBe(11);
});
