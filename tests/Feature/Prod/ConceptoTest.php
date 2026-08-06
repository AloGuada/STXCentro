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

describe('import del layout por QR', function () {
    /**
     * Layout vigente: un renglon por pieza, repitiendo marca y lote tantas veces
     * como piezas tenga el modelo. El encabezado va con espacios a proposito
     * ("PESO KG"), que es como lo manda planta.
     *
     * Columnas: QR, MARCA, DESCRIPCION, CATEGORIA, QS, CANTIDAD, PESO KG, AREA,
     * LONGITUD MM, LOTE.
     */
    function subirLayout(Catalogo $catalogo, string $filas, string $encabezado = 'QR,MARCA,DESCRIPCION,CATEGORIA,QS,CANTIDAD,PESO KG,AREA,LONGITUD MM,LOTE')
    {
        return test()->actingAs(test()->user)
            ->post(route('admin.prod.catalogos.import-csv', $catalogo), [
                'csv_file' => UploadedFile::fake()->createWithContent('conceptos.csv', $encabezado."\n".$filas),
            ]);
    }

    test('escribe la marca una vez y una pieza por cada renglon', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "QR-01,TG-BAR-1,OC-BAR,Barandales,1001,2,29.751,1.397,3542.177,\n".
            "QR-02,TG-BAR-1,OC-BAR,Barandales,1002,2,29.751,1.397,3542.177,\n"
        )->assertSessionHas('success');

        $marca = Concepto::where('catalogo_id', $catalogo->id)->sole();

        expect($marca->marca)->toBe('TG-BAR-1')
            ->and($marca->descripcion)->toBe('OC-BAR')
            ->and((float) $marca->peso_unitario)->toBe(29.751)
            ->and($marca->longitud)->toBe(3542)
            ->and($marca->cantidad)->toBe(2)
            ->and($marca->piezas()->pluck('qr')->sort()->values()->all())->toBe(['QR-01', 'QR-02'])
            ->and($marca->piezas()->pluck('qs')->sort()->values()->all())->toBe(['1001', '1002']);
    });

    test('la categoria se crea al vuelo y se reutiliza', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "QR-01,TG-CFC-1,LI-CFC,Contraflambeos,1001,1,44.664,3.768,797.114,\n".
            "QR-02,TG-CFC-2,LI-CFC,Contraflambeos,1002,1,189.09,16.02,995.424,\n"
        );

        expect(Categoria::where('nombre', 'Contraflambeos')->count())->toBe(1);
    });

    test('reimportar sobrescribe la marca y no duplica sus piezas', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo, "QR-01,TG-BAR-1,Original,Barandales,1001,1,10.000,1,1000,\n");
        subirLayout($catalogo, "QR-01,TG-BAR-1,Actualizado,Barandales,1001,1,29.751,1.397,3542.177,\n");

        $marca = Concepto::where('catalogo_id', $catalogo->id)->sole();

        expect($marca->descripcion)->toBe('Actualizado')
            ->and((float) $marca->peso_unitario)->toBe(29.751)
            ->and($marca->longitud)->toBe(3542)
            ->and($marca->piezas()->count())->toBe(1);
    });

    test('la misma marca en dos lotes son dos modelos, aunque repitan el QS', function () {
        $catalogo = Catalogo::factory()->create();

        // Mismo QS en los dos lotes: es justo por esto que el identificador es el QR.
        subirLayout($catalogo,
            "QR-01,TG-BAR-1,Lote uno,Barandales,1001,1,29.751,1.397,3542,L1\n".
            "QR-02,TG-BAR-1,Lote dos,Barandales,1001,1,31.500,1.500,3600,L2\n"
        );

        $lote1 = Concepto::where('marca', 'TG-BAR-1')->where('lote', 'L1')->sole();
        $lote2 = Concepto::where('marca', 'TG-BAR-1')->where('lote', 'L2')->sole();

        expect($lote1->descripcion)->toBe('Lote uno')
            ->and($lote2->descripcion)->toBe('Lote dos')
            ->and($lote1->piezas()->pluck('qr')->all())->toBe(['QR-01'])
            ->and($lote2->piezas()->pluck('qr')->all())->toBe(['QR-02'])
            ->and(Pieza::where('catalogo_id', $catalogo->id)->where('qs', '1001')->count())->toBe(2);
    });

    test('normaliza el lote y deja nulo el vacio', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "QR-01,TG-BAR-1,Con lote,Barandales,1001,1,29.751,1.397,3542,  lote  b \n".
            "QR-02,TG-BAR-2,Sin lote,Barandales,1002,1,29.751,1.397,3542,   \n"
        );

        expect(Concepto::where('marca', 'TG-BAR-1')->value('lote'))->toBe('LOTE B')
            ->and(Concepto::where('marca', 'TG-BAR-2')->value('lote'))->toBeNull();
    });

    test('la cantidad se cuenta de las piezas, no de la columna del layout', function () {
        $catalogo = Catalogo::factory()->create();

        // Dice 5 piezas pero solo vienen 2: manda lo que llego.
        subirLayout($catalogo,
            "QR-01,TG-BAR-1,OC-BAR,Barandales,1001,5,29.751,1.397,3542,\n".
            "QR-02,TG-BAR-1,OC-BAR,Barandales,1002,5,29.751,1.397,3542,\n"
        )->assertSessionHasErrors('csv_file');

        // Aun asi se carga lo que llego: el aviso no bloquea.
        $marca = Concepto::where('catalogo_id', $catalogo->id)->sole();

        expect($marca->cantidad)->toBe(2)
            ->and($marca->piezas()->count())->toBe(2);
    });

    test('un layout parcial suma sus piezas a la cantidad que ya tenia la marca', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo, "QR-01,TG-BAR-1,OC-BAR,Barandales,1001,1,29.751,1.397,3542,\n");

        expect(Concepto::where('catalogo_id', $catalogo->id)->sole()->cantidad)->toBe(1);

        subirLayout($catalogo, "QR-02,TG-BAR-1,OC-BAR,Barandales,1002,1,29.751,1.397,3542,\n");

        $marca = Concepto::where('catalogo_id', $catalogo->id)->sole();

        expect($marca->cantidad)->toBe(2)
            ->and($marca->piezas()->count())->toBe(2);
    });

    test('avisa del QR repetido y se queda con su primera aparicion', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "QR-01,TG-BAR-1,Primera,Barandales,1001,1,29.751,1.397,3542,\n".
            "QR-01,TG-BAR-2,Segunda,Barandales,1002,1,29.751,1.397,3542,\n"
        )->assertSessionHasErrors('csv_file');

        expect(Pieza::where('catalogo_id', $catalogo->id)->count())->toBe(1)
            ->and(Pieza::where('catalogo_id', $catalogo->id)->sole()->marca->descripcion)->toBe('Primera');
    });

    test('el renglon sin QR ni QS se ignora y se reporta', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "QR-01,TG-BAR-1,Con QR,Barandales,1001,1,29.751,1.397,3542,\n".
            ",TG-BAR-2,Sin nada,Barandales,,1,29.751,1.397,3542,\n"
        )->assertSessionHasErrors('csv_file');

        expect(Pieza::where('catalogo_id', $catalogo->id)->count())->toBe(1);
    });

    test('sin QR se cae al QS, que es como venia el layout viejo', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo, "QR-01,TG-BAR-1,Con QR,Barandales,1001,1,10,1,1000,\n");
        subirLayout($catalogo, ",TG-BAR-2,Solo QS,Barandales,2002,1,10,1,1000,\n");

        expect(Pieza::where('catalogo_id', $catalogo->id)->pluck('qr')->sort()->values()->all())
            ->toBe(['2002', 'QR-01']);
    });

    test('el layout viejo sigue cargando y su ETAPA entra como lote', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "1001,TG-BAR-1,FASE B,OC-BAR,Barandales,1,29.751,1.397,3542\n",
            'QS,MARCA,ETAPA,DESCRIPCION,CATEGORIA,CANTIDAD,PESOKG,AREA,LONGITUDMM',
        );

        $marca = Concepto::where('catalogo_id', $catalogo->id)->sole();
        $pieza = $marca->piezas()->sole();

        expect($marca->lote)->toBe('FASE B')
            ->and($pieza->qr)->toBe('1001')
            ->and($pieza->qs)->toBe('1001');
    });

    test('el mismo QR puede existir en catalogos de obras distintas', function () {
        $uno = Catalogo::factory()->create();
        $otro = Catalogo::factory()->create();

        subirLayout($uno, "QR-01,TG-BAR-1,Obra uno,Barandales,1001,1,10,1,1000,\n");
        subirLayout($otro, "QR-01,TG-BAR-1,Obra dos,Barandales,1001,1,10,1,1000,\n");

        expect(Pieza::where('qr', 'QR-01')->count())->toBe(2);
    });

    test('ignora los renglones de resumen al pie del layout', function () {
        $catalogo = Catalogo::factory()->create();

        subirLayout($catalogo,
            "QR-01,TG-BAR-1,OC-BAR,Barandales,1001,1,29.751,1.397,3542.177,\n".
            "Resúmenes generales,,,,,,,,,\n".
            "Cuenta = 257,,,,,\"Suma = 4,218.000\",\"Suma = 177,920.590\",,,\n"
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
