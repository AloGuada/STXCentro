<?php

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Categoria;
use App\Models\Prod\Pieza;
use App\Models\Prod\Registro;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin conceptos', function () {
    test('create page can be rendered', function () {
        $catalogo = Catalogo::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.create', ['catalogo_id' => $catalogo->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/conceptos/create')
            ->has('catalogo')
        );
    });

    test('concepto can be stored', function () {
        $catalogo = Catalogo::factory()->create();
        $categoria = Categoria::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.store'), [
                'catalogo_id' => $catalogo->id,
                'marca' => 'MK-001',
                'descripcion' => 'Concepto de prueba',
                'cantidad' => 12,
                'peso_unitario' => 25.500,
                'longitud' => 6250,
                'categoria_id' => $categoria->id,
                'version' => 1,
                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.prod.catalogos.show', $catalogo));

        $this->assertDatabaseHas('conceptos', [
            'catalogo_id' => $catalogo->id,
            'obra_id' => $catalogo->obra_id,
            'marca' => 'MK-001',
            'descripcion' => 'Concepto de prueba',
            'cantidad' => 12,
            'longitud' => 6250,
            'categoria_id' => $categoria->id,
        ]);
    });

    test('concepto can be stored without version and activo defaults', function () {
        $catalogo = Catalogo::factory()->create();
        $categoria = Categoria::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.store'), [
                'catalogo_id' => $catalogo->id,
                'marca' => 'MK-002',
                'descripcion' => 'Sin version ni activo',
                'cantidad' => 0,
                'peso_unitario' => 10.000,
                'longitud' => 3000,
                'categoria_id' => $categoria->id,
            ]);

        $response->assertRedirect(route('admin.prod.catalogos.show', $catalogo));

        $this->assertDatabaseHas('conceptos', [
            'marca' => 'MK-002',
            'activo' => true,
            'version' => 1,
        ]);
    });

    test('concepto can be updated', function () {
        $concepto = Concepto::factory()->create();
        $categoria = Categoria::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.prod.conceptos.update', $concepto), [
                'marca' => 'MK-UPD',
                'descripcion' => 'Updated',
                'cantidad' => 7,
                'peso_unitario' => 15.250,
                'longitud' => 9000,
                'categoria_id' => $categoria->id,
                'version' => 2,
                'activo' => false,
            ]);

        $response->assertRedirect(route('admin.prod.catalogos.show', $concepto->catalogo_id));

        $this->assertDatabaseHas('conceptos', [
            'id' => $concepto->id,
            'marca' => 'MK-UPD',
            'cantidad' => 7,
            'longitud' => 9000,
            'categoria_id' => $categoria->id,
            'version' => 2,
            'activo' => false,
        ]);
    });

    test('concepto can be deleted', function () {
        $concepto = Concepto::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.conceptos.destroy', $concepto));

        $response->assertRedirect(route('admin.prod.catalogos.show', $concepto->catalogo_id));
        $this->assertDatabaseMissing('conceptos', ['id' => $concepto->id]);
    });

    test('la marca no se borra si alguna de sus piezas tiene produccion', function () {
        $marca = marcaConPiezas(1);
        Registro::factory()->create(['pieza_id' => $marca->piezas->first()->id]);
        $concepto = $marca;

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.conceptos.destroy', $concepto));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('conceptos', ['id' => $concepto->id]);
    });

    test('validation requires marca and descripcion', function () {
        $catalogo = Catalogo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.store'), [
                'catalogo_id' => $catalogo->id,
                'peso_unitario' => 10,
            ]);

        $response->assertSessionHasErrors(['marca', 'descripcion']);
    });

    test('validation requires longitud and categoria', function () {
        $catalogo = Catalogo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.store'), [
                'catalogo_id' => $catalogo->id,
                'marca' => 'MK-003',
                'descripcion' => 'Falta longitud y categoria',
                'cantidad' => 1,
                'peso_unitario' => 10,
            ]);

        $response->assertSessionHasErrors(['longitud', 'categoria_id']);
    });

    test('create page passes categorias', function () {
        $catalogo = Catalogo::factory()->create();
        Categoria::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.create', ['catalogo_id' => $catalogo->id]));

        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/conceptos/create')
            ->has('categorias', 2)
        );
    });
});

describe('import del layout por QS', function () {
    /**
     * El layout es una lista de piezas: un renglon por QS, repitiendo marca y
     * etapa tantas veces como piezas tenga el modelo.
     */
    function subirLayout(Catalogo $catalogo, string $filas, string $encabezado = 'QS,MARCA,ETAPA,DESCRIPCION,CATEGORIA,CANTIDAD,PESOKG,AREA,LONGITUDMM')
    {
        return test()->actingAs(test()->user)
            ->post(route('admin.prod.catalogos.import-csv', $catalogo), [
                'csv_file' => UploadedFile::fake()->createWithContent('conceptos.csv', $encabezado."\n".$filas),
            ]);
    }

    test('escribe la marca una vez y una pieza por cada QS', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "1001,TG-BAR-1,,OC-BAR,Barandales,2,29.751,1.397,3542.177\n".
            "1002,TG-BAR-1,,OC-BAR,Barandales,2,29.751,1.397,3542.177\n"
        )->assertSessionHas('success');

        $marca = Concepto::where('catalogo_id', $catalogo->id)->sole();

        expect($marca->marca)->toBe('TG-BAR-1')
            ->and($marca->descripcion)->toBe('OC-BAR')
            ->and((float) $marca->peso_unitario)->toBe(29.751)
            ->and($marca->longitud)->toBe(3542)
            ->and($marca->cantidad)->toBe(2)
            ->and($marca->piezas()->pluck('qs')->sort()->values()->all())->toBe(['1001', '1002']);
    });

    test('la categoria se crea al vuelo y se reutiliza', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "1001,TG-CFC-1,,LI-CFC,Contraflambeos,1,44.664,3.768,797.114\n".
            "1002,TG-CFC-2,,LI-CFC,Contraflambeos,1,189.09,16.02,995.424\n"
        );

        expect(Categoria::where('nombre', 'Contraflambeos')->count())->toBe(1);
    });

    test('reimportar sobrescribe la marca y no duplica sus piezas', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo, "1001,TG-BAR-1,,Original,Barandales,1,10.000,1,1000\n");
        subirLayout($catalogo, "1001,TG-BAR-1,,Actualizado,Barandales,1,29.751,1.397,3542.177\n");

        $marca = Concepto::where('catalogo_id', $catalogo->id)->sole();

        expect($marca->descripcion)->toBe('Actualizado')
            ->and((float) $marca->peso_unitario)->toBe(29.751)
            ->and($marca->longitud)->toBe(3542)
            ->and($marca->piezas()->count())->toBe(1);
    });

    test('la misma marca en dos etapas son dos modelos con sus propias piezas', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "1001,TG-BAR-1,1,Etapa uno,Barandales,1,29.751,1.397,3542\n".
            "1002,TG-BAR-1,2,Etapa dos,Barandales,1,31.500,1.500,3600\n"
        );

        $etapa1 = Concepto::where('marca', 'TG-BAR-1')->where('etapa', '1')->sole();
        $etapa2 = Concepto::where('marca', 'TG-BAR-1')->where('etapa', '2')->sole();

        expect($etapa1->descripcion)->toBe('Etapa uno')
            ->and($etapa2->descripcion)->toBe('Etapa dos')
            ->and($etapa1->piezas()->pluck('qs')->all())->toBe(['1001'])
            ->and($etapa2->piezas()->pluck('qs')->all())->toBe(['1002']);
    });

    test('normaliza la etapa y deja nula la vacia', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "1001,TG-BAR-1,  fase  b ,Con etapa,Barandales,1,29.751,1.397,3542\n".
            "1002,TG-BAR-2,   ,Sin etapa,Barandales,1,29.751,1.397,3542\n"
        );

        expect(Concepto::where('marca', 'TG-BAR-1')->value('etapa'))->toBe('FASE B')
            ->and(Concepto::where('marca', 'TG-BAR-2')->value('etapa'))->toBeNull();
    });

    test('avisa cuando los QS no cuadran con la cantidad que declara el layout', function () {
        $catalogo = Catalogo::factory()->create();

        // Dice 5 piezas pero solo vienen 2: el archivo esta incompleto.
        subirLayout($catalogo,
            "1001,TG-BAR-1,,OC-BAR,Barandales,5,29.751,1.397,3542\n".
            "1002,TG-BAR-1,,OC-BAR,Barandales,5,29.751,1.397,3542\n"
        )->assertSessionHasErrors('csv_file');

        // Aun asi se carga lo que llego: el aviso no bloquea.
        expect(Concepto::where('catalogo_id', $catalogo->id)->sole()->piezas()->count())->toBe(2);
    });

    test('avisa del QS repetido y se queda con su primera aparicion', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "1001,TG-BAR-1,,Primera,Barandales,1,29.751,1.397,3542\n".
            "1001,TG-BAR-2,,Segunda,Barandales,1,29.751,1.397,3542\n"
        )->assertSessionHasErrors('csv_file');

        expect(Pieza::where('catalogo_id', $catalogo->id)->count())->toBe(1)
            ->and(Pieza::where('catalogo_id', $catalogo->id)->sole()->marca->descripcion)->toBe('Primera');
    });

    test('el renglon sin QS se ignora y se reporta', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "1001,TG-BAR-1,,Con QS,Barandales,1,29.751,1.397,3542\n".
            ",TG-BAR-2,,Sin QS,Barandales,1,29.751,1.397,3542\n"
        )->assertSessionHasErrors('csv_file');

        expect(Pieza::where('catalogo_id', $catalogo->id)->count())->toBe(1);
    });

    test('el mismo QS puede existir en catalogos de obras distintas', function () {
        $uno = Catalogo::factory()->create();
        $otro = Catalogo::factory()->create();

        subirLayout($uno, "1001,TG-BAR-1,,Obra uno,Barandales,1,10,1,1000\n");
        subirLayout($otro, "1001,TG-BAR-1,,Obra dos,Barandales,1,10,1,1000\n");

        expect(Pieza::where('qs', '1001')->count())->toBe(2);
    });

    test('ignora los renglones de resumen al pie del layout', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "1001,TG-BAR-1,,OC-BAR,Barandales,1,29.751,1.397,3542.177\n".
            "Resúmenes generales,,,,,,,,\n".
            "Cuenta = 257,,,,\"Suma = 4,218.000\",\"Suma = 177,920.590\",,,\n"
        );

        expect(Concepto::where('catalogo_id', $catalogo->id)->count())->toBe(1);
    });

    test('el import exige archivo', function () {
        $catalogo = Catalogo::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.import-csv', $catalogo), [])
            ->assertSessionHasErrors(['csv_file']);
    });

    test('el layout se descarga como xlsx', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.layout'));

        $response->assertOk();
        expect($response->headers->get('content-disposition'))->toContain('layout-conceptos.xlsx');
    });
});
