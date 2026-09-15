<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Pieza;
use App\Models\Prod\Registro;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Prod\VersionadorCatalogo;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('catalogos index', function () {
    test('index lista solo los vigentes por defecto', function () {
        $obra = Obra::factory()->create();
        Catalogo::factory()->create(['obra_id' => $obra->id, 'version' => 1, 'vigente' => false]);
        Catalogo::factory()->create(['obra_id' => $obra->id, 'version' => 2, 'vigente' => true]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/catalogos/index')
            ->has('catalogos.data', 1)
        );
    });

    test('index incluye historicos con el filtro', function () {
        $obra = Obra::factory()->create();
        Catalogo::factory()->create(['obra_id' => $obra->id, 'version' => 1, 'vigente' => false]);
        Catalogo::factory()->create(['obra_id' => $obra->id, 'version' => 2, 'vigente' => true]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.index', ['historicos' => 1]));

        $response->assertInertia(fn ($page) => $page->has('catalogos.data', 2));
    });

    test('index solo ofrece obras sin catalogo para el alta', function () {
        $conCatalogo = Obra::factory()->create();
        Catalogo::factory()->create(['obra_id' => $conCatalogo->id]);
        Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.index'));

        $response->assertInertia(fn ($page) => $page->has('obrasDisponibles', 1));
    });

    test('index comparte proyectos activos de cobranza', function () {
        Proyecto::factory()->create(['activa' => true]);
        Proyecto::factory()->create(['activa' => false]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.index'));

        $response->assertInertia(fn ($page) => $page->has('proyectos', 1));
    });
});

describe('alta de catalogo', function () {
    test('se crea sobre una obra existente como version 1 vigente', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.store'), [
                'modo' => 'existente',
                'obra_id' => $obra->id,
                'nombre' => 'Estructura principal',
            ]);

        $catalogo = Catalogo::where('obra_id', $obra->id)->firstOrFail();

        $response->assertRedirect(route('admin.prod.catalogos.show', $catalogo));
        expect($catalogo->version)->toBe(1)
            ->and($catalogo->vigente)->toBeTrue()
            ->and($catalogo->nombre)->toBe('Estructura principal');
    });

    test('se puede dar de alta la obra en el momento sin ligar a cobranza', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.store'), [
                'modo' => 'nueva',
                'nombre' => 'Nave 1',
                'obra_no' => 'OBR-900',
                'obra_descripcion' => 'Nave industrial',
            ]);

        $obra = Obra::where('no', 'OBR-900')->firstOrFail();

        expect($obra->proyecto_id)->toBeNull()
            ->and($obra->estatus)->toBe('abierta')
            ->and($obra->es_planta)->toBeFalse()
            ->and($obra->catalogos()->count())->toBe(1);
    });

    test('la obra nueva puede quedar ligada a un proyecto de cobranza', function () {
        $proyecto = Proyecto::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.store'), [
                'modo' => 'nueva',
                'nombre' => 'Nave 2',
                'obra_no' => 'OBR-901',
                'obra_descripcion' => 'Ampliacion',
                'proyecto_id' => $proyecto->id,
            ]);

        expect(Obra::where('no', 'OBR-901')->first()->proyecto_id)->toBe($proyecto->id);
    });

    test('rechaza una obra que ya tiene catalogo', function () {
        $obra = Obra::factory()->create();
        Catalogo::factory()->create(['obra_id' => $obra->id]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.store'), [
                'modo' => 'existente',
                'obra_id' => $obra->id,
                'nombre' => 'Duplicado',
            ]);

        $response->assertSessionHasErrors('obra_id');
        expect(Catalogo::where('obra_id', $obra->id)->count())->toBe(1);
    });

    test('valida los campos segun el modo', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.store'), ['modo' => 'existente', 'nombre' => 'X'])
            ->assertSessionHasErrors('obra_id');

        $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.store'), ['modo' => 'nueva', 'nombre' => 'X'])
            ->assertSessionHasErrors(['obra_no', 'obra_descripcion']);
    });
});

describe('show del catalogo', function () {
    test('muestra sus piezas y todas las versiones de la obra', function () {
        $obra = Obra::factory()->create();
        $v1 = Catalogo::factory()->create(['obra_id' => $obra->id, 'version' => 1, 'vigente' => false]);
        $v2 = Catalogo::factory()->create(['obra_id' => $obra->id, 'version' => 2, 'vigente' => true]);

        Concepto::factory()->count(2)->create(['obra_id' => $obra->id, 'catalogo_id' => $v2->id]);
        Concepto::factory()->create(['obra_id' => $obra->id, 'catalogo_id' => $v1->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.show', $v2));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/catalogos/show')
            ->has('marcas.data', 2)
            ->has('versiones', 2)
        );
    });

    test('las marcas van paginadas y sin sus piezas', function () {
        $catalogo = Catalogo::factory()->create();

        foreach (range(1, 30) as $n) {
            marcaConPiezas(2, [
                'obra_id' => $catalogo->obra_id,
                'catalogo_id' => $catalogo->id,
                'marca' => sprintf('TG-%03d', $n),
            ]);
        }

        // Un catalogo de obra son decenas de miles de QR: la pantalla trae 25
        // marcas sin piezas y los QR se piden al desplegar la marca.
        $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.show', $catalogo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('marcas.data', 25)
                ->where('marcas.total', 30)
                ->missing('marcas.data.0.piezas')
                ->where('marcas.data.0.piezas_count', 2)
                // Los totales son del catalogo entero, no de la pagina.
                ->where('totales.marcas', 30)
                ->where('totales.piezas', 60)
            );
    });

    test('el buscador filtra en el servidor', function () {
        $catalogo = Catalogo::factory()->create();
        $buscada = marcaConPiezas(1, [
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
            'marca' => 'TG-BUSCADA',
        ]);
        marcaConPiezas(1, [
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
            'marca' => 'TG-OTRA',
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.show', ['catalogo' => $catalogo, 'search' => 'BUSCADA']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('marcas.data', 1)
                ->where('marcas.data.0.id', $buscada->id)
            );
    });

    test('el avance de la marca lo resume el servidor', function () {
        $catalogo = Catalogo::factory()->create();
        $marca = marcaConPiezas(2, ['obra_id' => $catalogo->obra_id, 'catalogo_id' => $catalogo->id]);
        $proceso = proceso();
        obraPagaProcesos($catalogo->obra_id, $proceso);

        capturarPiezas([$marca->piezas[0]], GrupoTrabajo::factory()->create(), '2026-02-04');

        $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.show', $catalogo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('avancePorMarca.'.$marca->id.'.'.$proceso->id, 1)
            );
    });
});

describe('nueva version del catalogo', function () {
    test('copia las piezas y congela la version anterior', function () {
        $catalogo = Catalogo::factory()->create(['version' => 1]);
        Concepto::factory()->count(3)->create([
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.nueva-version', $catalogo));

        $nueva = Catalogo::where('obra_id', $catalogo->obra_id)->where('version', 2)->firstOrFail();

        $response->assertRedirect(route('admin.prod.catalogos.show', $nueva));
        expect($nueva->vigente)->toBeTrue()
            ->and($nueva->catalogo_origen_id)->toBe($catalogo->id)
            ->and($nueva->conceptos()->count())->toBe(3)
            ->and($catalogo->fresh()->vigente)->toBeFalse()
            ->and($catalogo->conceptos()->count())->toBe(3);
    });

    test('arrastra las asignaciones de grupo de precio', function () {
        $catalogo = Catalogo::factory()->create();
        $concepto = Concepto::factory()->create([
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
        ]);
        $grupoPrecio = GrupoPrecio::factory()->create(['obra_id' => $catalogo->obra_id]);
        GrupoPrecioConcepto::create([
            'grupo_precio_id' => $grupoPrecio->id,
            'concepto_id' => $concepto->id,
        ]);

        $nueva = app(VersionadorCatalogo::class)->nuevaVersion($catalogo);
        $copia = $nueva->conceptos()->firstOrFail();

        expect($copia->id)->not->toBe($concepto->id)
            ->and($copia->marca)->toBe($concepto->marca)
            ->and(GrupoPrecioConcepto::where('concepto_id', $copia->id)->where('grupo_precio_id', $grupoPrecio->id)->exists())
            ->toBeTrue();
    });

    test('la produccion capturada sigue apuntando a la version vieja', function () {
        $catalogo = Catalogo::factory()->create();
        $concepto = Concepto::factory()->create([
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
        ]);
        $pieza = Pieza::factory()->create([
            'concepto_id' => $concepto->id,
            'catalogo_id' => $catalogo->id,
        ]);
        $registro = Registro::factory()->create(['pieza_id' => $pieza->id]);

        app(VersionadorCatalogo::class)->nuevaVersion($catalogo);

        expect($registro->fresh()->pieza_id)->toBe($pieza->id)
            ->and($pieza->fresh()->catalogo_id)->toBe($catalogo->id);
    });

    test('no se puede versionar un catalogo historico', function () {
        $obra = Obra::factory()->create();
        $viejo = Catalogo::factory()->create(['obra_id' => $obra->id, 'version' => 1, 'vigente' => false]);
        Catalogo::factory()->create(['obra_id' => $obra->id, 'version' => 2, 'vigente' => true]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.nueva-version', $viejo));

        $response->assertSessionHasErrors('error');
        expect(Catalogo::where('obra_id', $obra->id)->count())->toBe(2);
    });

    test('solo queda un catalogo vigente por obra', function () {
        $catalogo = Catalogo::factory()->create();

        $v2 = app(VersionadorCatalogo::class)->nuevaVersion($catalogo);
        app(VersionadorCatalogo::class)->nuevaVersion($v2);

        expect(Catalogo::where('obra_id', $catalogo->obra_id)->where('vigente', true)->count())->toBe(1)
            ->and(Catalogo::where('obra_id', $catalogo->obra_id)->max('version'))->toBe(3);
    });
});

describe('comparador de versiones', function () {
    test('reporta agregadas, eliminadas, modificadas y sin cambios', function () {
        $catalogo = Catalogo::factory()->create();

        $igual = Concepto::factory()->create([
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
            'marca' => 'SIN-CAMBIO',
            'peso_unitario' => 10.000,
        ]);
        Concepto::factory()->create([
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
            'marca' => 'CAMBIA',
            'peso_unitario' => 20.000,
        ]);
        Concepto::factory()->create([
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
            'marca' => 'SE-VA',
        ]);

        $v2 = app(VersionadorCatalogo::class)->nuevaVersion($catalogo);

        $v2->conceptos()->where('marca', 'CAMBIA')->update(['peso_unitario' => 35.500]);
        $v2->conceptos()->where('marca', 'SE-VA')->delete();
        Concepto::factory()->create([
            'obra_id' => $v2->obra_id,
            'catalogo_id' => $v2->id,
            'marca' => 'NUEVA',
        ]);

        $diff = app(VersionadorCatalogo::class)->comparar($catalogo, $v2);

        expect($diff['agregadas'])->toHaveCount(1)
            ->and($diff['agregadas'][0]['marca'])->toBe('NUEVA')
            ->and($diff['eliminadas'])->toHaveCount(1)
            ->and($diff['eliminadas'][0]['marca'])->toBe('SE-VA')
            ->and($diff['modificadas'])->toHaveCount(1)
            ->and($diff['modificadas'][0]['marca'])->toBe('CAMBIA')
            ->and($diff['modificadas'][0]['cambios'][0]['campo'])->toBe('Peso unitario')
            ->and($diff['sin_cambios'])->toBe(1)
            ->and($igual->fresh())->not->toBeNull();
    });

    test('cruza lo pagado con la cantidad de la version nueva', function () {
        $marca = marcaConPiezas(3, ['marca' => 'RECORTADA']);
        $catalogo = $marca->catalogo;
        $grupo = GrupoTrabajo::factory()->create();
        obraPagaProcesos($marca->obra_id, proceso());
        capturarPiezas($marca->piezas, $grupo, '2026-02-04');

        $v2 = app(VersionadorCatalogo::class)->nuevaVersion($catalogo);
        $v2->conceptos()->where('marca', 'RECORTADA')->update(['cantidad' => 2]);
        Concepto::factory()->create(['obra_id' => $v2->obra_id, 'catalogo_id' => $v2->id, 'marca' => 'NUEVA', 'cantidad' => 5]);

        $diff = app(VersionadorCatalogo::class)->comparar($catalogo, $v2);

        expect($diff['modificadas'][0]['marca'])->toBe('RECORTADA')
            ->and($diff['modificadas'][0]['pagadas'])->toBe(3.0)
            ->and($diff['modificadas'][0]['cantidad'])->toBe(2)
            ->and($diff['modificadas'][0]['excedente'])->toBe(1.0)
            ->and($diff['agregadas'][0]['por_pagar'])->toBe(5.0)
            ->and($diff['pagado']['modelos_con_exceso'])->toBe(1)
            ->and($diff['pagado']['piezas_de_mas'])->toBe(1.0)
            ->and($diff['pagado']['piezas_por_pagar'])->toBe(5.0);
    });

    test('una marca eliminada con pagos queda toda en exceso', function () {
        $marca = marcaConPiezas(2, ['marca' => 'SE-VA']);
        $grupo = GrupoTrabajo::factory()->create();
        obraPagaProcesos($marca->obra_id, proceso());
        capturarPiezas($marca->piezas, $grupo, '2026-02-04');

        $v2 = app(VersionadorCatalogo::class)->nuevaVersion($marca->catalogo);
        $v2->conceptos()->where('marca', 'SE-VA')->delete();

        $diff = app(VersionadorCatalogo::class)->comparar($marca->catalogo, $v2);

        expect($diff['eliminadas'][0]['excedente'])->toBe(2.0)
            ->and($diff['pagado']['piezas_de_mas'])->toBe(2.0);
    });

    test('la pantalla de comparacion renderiza', function () {
        $catalogo = Catalogo::factory()->create();
        Concepto::factory()->create(['obra_id' => $catalogo->obra_id, 'catalogo_id' => $catalogo->id]);
        $v2 = app(VersionadorCatalogo::class)->nuevaVersion($catalogo);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.comparar', [$catalogo, $v2]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/catalogos/comparar')
            ->has('diff')
            ->has('versiones', 2)
        );
    });

    test('no compara catalogos de obras distintas', function () {
        $a = Catalogo::factory()->create();
        $b = Catalogo::factory()->create();

        $this->actingAs($this->user)
            ->get(route('admin.prod.catalogos.comparar', [$a, $b]))
            ->assertNotFound();
    });
});

describe('baja de catalogo', function () {
    test('no se elimina si sus piezas tienen produccion capturada', function () {
        $catalogo = Catalogo::factory()->create();
        $concepto = Concepto::factory()->create([
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
        ]);
        Registro::factory()->create([
            'pieza_id' => Pieza::factory()->create([
                'concepto_id' => $concepto->id,
                'catalogo_id' => $catalogo->id,
            ])->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.catalogos.destroy', $catalogo));

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('prod_catalogos', ['id' => $catalogo->id]);
    });

    test('al borrar la version vigente la anterior vuelve a quedar en uso', function () {
        $v1 = Catalogo::factory()->create();
        $v2 = app(VersionadorCatalogo::class)->nuevaVersion($v1);

        $this->actingAs($this->user)
            ->delete(route('admin.prod.catalogos.destroy', $v2));

        $this->assertDatabaseMissing('prod_catalogos', ['id' => $v2->id]);
        expect($v1->fresh()->vigente)->toBeTrue();
    });
});

describe('comparar versiones con marcas repetidas', function () {
    test('empareja por marca y lote, no solo por marca', function () {
        $catalogo = Catalogo::factory()->create();

        foreach (['1' => 'Lote uno', '2' => 'Lote dos'] as $lote => $descripcion) {
            Concepto::factory()->create([
                'obra_id' => $catalogo->obra_id,
                'catalogo_id' => $catalogo->id,
                'marca' => 'V-01',
                'lote' => $lote,
                'descripcion' => $descripcion,
                'cantidad' => 10,
            ]);
        }

        $nueva = app(VersionadorCatalogo::class)->nuevaVersion($catalogo);

        // Sólo cambia la lote 2: la 1 debe salir sin cambios.
        $nueva->conceptos()->where('lote', '2')->firstOrFail()->update(['cantidad' => 25]);

        $diff = app(VersionadorCatalogo::class)->comparar($catalogo, $nueva);

        expect($diff['sin_cambios'])->toBe(1)
            ->and($diff['agregadas'])->toBeEmpty()
            ->and($diff['eliminadas'])->toBeEmpty()
            ->and($diff['modificadas'])->toHaveCount(1)
            ->and($diff['modificadas'][0]['marca'])->toBe('V-01')
            ->and($diff['modificadas'][0]['lote'])->toBe('2');
    });

    test('versionar arrastra la lote y copia las piezas con su QS', function () {
        $catalogo = Catalogo::factory()->create();
        $marca = Concepto::factory()->create([
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
            'marca' => 'V-01',
            'lote' => 'FASE B',
        ]);
        $original = Pieza::factory()->create([
            'concepto_id' => $marca->id,
            'catalogo_id' => $catalogo->id,
            'qs' => '1042',
        ]);

        $nueva = app(VersionadorCatalogo::class)->nuevaVersion($catalogo);
        $copiaMarca = $nueva->conceptos()->sole();
        $copiaPieza = $nueva->piezas()->sole();

        expect($copiaMarca->lote)->toBe('FASE B')
            ->and($copiaPieza->qs)->toBe('1042')
            ->and($copiaPieza->concepto_id)->toBe($copiaMarca->id)
            // El linaje es lo que sostiene el acumulado entre versiones.
            ->and($copiaPieza->pieza_origen_id)->toBe($original->id);
    });
});
