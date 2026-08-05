<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
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
            ->has('conceptos', 2)
            ->has('versiones', 2)
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
        $registro = Registro::factory()->create(['concepto_id' => $concepto->id]);

        app(VersionadorCatalogo::class)->nuevaVersion($catalogo);

        expect($registro->fresh()->concepto_id)->toBe($concepto->id)
            ->and($concepto->fresh()->catalogo_id)->toBe($catalogo->id);
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
        Registro::factory()->create(['concepto_id' => $concepto->id]);

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
    test('empareja por marca y etapa, no solo por marca', function () {
        $catalogo = Catalogo::factory()->create();

        foreach (['1' => 'Etapa uno', '2' => 'Etapa dos'] as $etapa => $descripcion) {
            Concepto::factory()->create([
                'obra_id' => $catalogo->obra_id,
                'catalogo_id' => $catalogo->id,
                'marca' => 'V-01',
                'etapa' => $etapa,
                'descripcion' => $descripcion,
                'cantidad' => 10,
            ]);
        }

        $nueva = app(VersionadorCatalogo::class)->nuevaVersion($catalogo);

        // Sólo cambia la etapa 2: la 1 debe salir sin cambios.
        $nueva->conceptos()->where('etapa', '2')->firstOrFail()->update(['cantidad' => 25]);

        $diff = app(VersionadorCatalogo::class)->comparar($catalogo, $nueva);

        expect($diff['sin_cambios'])->toBe(1)
            ->and($diff['agregadas'])->toBeEmpty()
            ->and($diff['eliminadas'])->toBeEmpty()
            ->and($diff['modificadas'])->toHaveCount(1)
            ->and($diff['modificadas'][0]['marca'])->toBe('V-01')
            ->and($diff['modificadas'][0]['etapa'])->toBe('2');
    });

    test('versionar arrastra qs y etapa a la copia', function () {
        $catalogo = Catalogo::factory()->create();
        Concepto::factory()->create([
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
            'qs' => '1042',
            'marca' => 'V-01',
            'etapa' => 'FASE B',
        ]);

        $copia = app(VersionadorCatalogo::class)->nuevaVersion($catalogo)->conceptos()->sole();

        expect($copia->qs)->toBe('1042')
            ->and($copia->etapa)->toBe('FASE B');
    });
});
