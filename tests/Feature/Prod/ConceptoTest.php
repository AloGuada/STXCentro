<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Categoria;
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

    test('concepto cannot be deleted with registros', function () {
        $concepto = Concepto::factory()->create();
        Registro::factory()->create(['concepto_id' => $concepto->id]);

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

describe('conceptos csv import al catalogo', function () {
    test('csv imports with the detailed layout correctly', function () {
        $catalogo = Catalogo::factory()->create();

        $csvContent = "MARCA,DESCRIPCION,CATEGORIA,CANTIDAD,PESOKG,AREA,LONGITUDMM\n";
        $csvContent .= "TG-BAR-1,OC-BAR,Barandales,1,29.751,1.397,3542.177\n";
        $csvContent .= "TG-CEM-2,CE-MURO,Canal de muro,6,1251.576,44.586,12200\n";

        $file = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.import-csv', $catalogo), [
                'csv_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $barandales = Categoria::where('nombre', 'Barandales')->first();
        expect($barandales)->not->toBeNull();

        $this->assertDatabaseHas('conceptos', [
            'catalogo_id' => $catalogo->id,
            'obra_id' => $catalogo->obra_id,
            'marca' => 'TG-BAR-1',
            'descripcion' => 'OC-BAR',
            'categoria_id' => $barandales->id,
            'cantidad' => 1,
            'peso_unitario' => 29.751, // ya viene en kg
            'longitud' => 3542, // mm redondeado
            'activo' => true,
        ]);

        $this->assertDatabaseHas('conceptos', [
            'catalogo_id' => $catalogo->id,
            'marca' => 'TG-CEM-2',
            'cantidad' => 6,
            'peso_unitario' => 1251.576,
            'longitud' => 12200,
        ]);

        expect(Concepto::where('catalogo_id', $catalogo->id)->count())->toBe(2);
    });

    test('csv import creates categorias on the fly and reuses them', function () {
        $catalogo = Catalogo::factory()->create();

        $csvContent = "MARCA,DESCRIPCION,CATEGORIA,CANTIDAD,PESOKG,AREA,LONGITUDMM\n";
        $csvContent .= "TG-CFC-1,LI-CFC,Contraflambeos,24,44.664,3.768,797.114\n";
        $csvContent .= "TG-CFC-2,LI-CFC,Contraflambeos,90,189.09,16.02,995.424\n";

        $file = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent);

        $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.import-csv', $catalogo), [
                'csv_file' => $file,
            ]);

        expect(Categoria::where('nombre', 'Contraflambeos')->count())->toBe(1);
    });

    test('csv import overwrites existing marca in the same catalogo', function () {
        $catalogo = Catalogo::factory()->create();
        $categoria = Categoria::factory()->create();
        Concepto::factory()->create([
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
            'marca' => 'TG-BAR-1',
            'descripcion' => 'Original',
            'cantidad' => 1,
            'peso_unitario' => 10.000,
            'longitud' => 1000,
            'categoria_id' => $categoria->id,
            'version' => 3,
        ]);

        $csvContent = "MARCA,DESCRIPCION,CATEGORIA,CANTIDAD,PESOKG,AREA,LONGITUDMM\n";
        $csvContent .= "TG-BAR-1,Actualizado,Barandales,4,29.751,1.397,3542.177\n";

        $file = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent);

        $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.import-csv', $catalogo), [
                'csv_file' => $file,
            ]);

        $this->assertDatabaseHas('conceptos', [
            'catalogo_id' => $catalogo->id,
            'marca' => 'TG-BAR-1',
            'descripcion' => 'Actualizado',
            'cantidad' => 4,
            'peso_unitario' => 29.751,
            'longitud' => 3542,
            'version' => 3, // la version se conserva
        ]);

        expect(Concepto::where('catalogo_id', $catalogo->id)->where('marca', 'TG-BAR-1')->count())->toBe(1);
    });

    test('csv import skips summary/footer rows', function () {
        $catalogo = Catalogo::factory()->create();

        $csvContent = "MARCA,DESCRIPCION,CATEGORIA,CANTIDAD,PESOKG,AREA,LONGITUDMM\n";
        $csvContent .= "TG-BAR-1,OC-BAR,Barandales,1,29.751,1.397,3542.177\n";
        $csvContent .= "Resúmenes generales,,,,,,\n";
        $csvContent .= "Cuenta = 257,,,\"Suma = 4,218.000\",\"Suma = 177,920.590\",,\n";

        $file = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent);

        $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.import-csv', $catalogo), [
                'csv_file' => $file,
            ]);

        expect(Concepto::where('catalogo_id', $catalogo->id)->count())->toBe(1);
    });

    test('csv import requires file', function () {
        $catalogo = Catalogo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.catalogos.import-csv', $catalogo), []);

        $response->assertSessionHasErrors(['csv_file']);
    });

    test('layout can be downloaded as xlsx', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.layout'));

        $response->assertOk();
        expect($response->headers->get('content-disposition'))->toContain('layout-conceptos.xlsx');
    });
});

describe('obra csv import conceptos', function () {
    test('csv imports conceptos correctly', function () {
        $obra = Obra::factory()->create();

        $csvContent = "PLANO,PIEZA,CONCEPTO,LARGO,KG.UNIT.,CANT.,TOTAL KG.,OBSERVACIONES\n";
        $csvContent .= "MK-100,PIEZA,Viga principal,3.50,25.500,10,255.00,1\n";
        $csvContent .= "MK-101,PIEZA,Columna,2.00,15.000,5,75.00,1\n";

        $file = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent);

        $response = $this->actingAs($this->user)
            ->post(route('admin.obras.import-conceptos', $obra), [
                'csv_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('conceptos', [
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Viga principal',
            'version' => 1,
        ]);

        $this->assertDatabaseHas('conceptos', [
            'obra_id' => $obra->id,
            'marca' => 'MK-101',
        ]);

        expect(Concepto::where('obra_id', $obra->id)->count())->toBe(2);
    });

    test('csv import estrena catalogo vigente cuando la obra no tiene', function () {
        $obra = Obra::factory()->create();

        $csvContent = "PLANO,PIEZA,CONCEPTO,LARGO,KG.UNIT.,CANT.,TOTAL KG.,OBSERVACIONES\n";
        $csvContent .= "MK-100,PIEZA,Viga principal,3.50,25.500,10,255.00,1\n";

        $file = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent);

        $this->actingAs($this->user)
            ->post(route('admin.obras.import-conceptos', $obra), ['csv_file' => $file]);

        $catalogo = $obra->fresh()->catalogoVigente()->first();

        expect($catalogo)->not->toBeNull()
            ->and($catalogo->version)->toBe(1)
            ->and(Concepto::where('obra_id', $obra->id)->pluck('catalogo_id')->unique()->all())
            ->toBe([$catalogo->id]);
    });

    test('csv import dedup by version only updates higher version', function () {
        $obra = Obra::factory()->create();
        Concepto::factory()->create([
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Original',
            'peso_unitario' => 10.000,
            'version' => 2,
        ]);

        $csvContent = "PLANO,PIEZA,CONCEPTO,LARGO,KG.UNIT.,CANT.,TOTAL KG.,OBSERVACIONES\n";
        $csvContent .= "MK-100,PIEZA,Updated lower,3.50,25.500,10,255.00,1\n";

        $file = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent);

        $this->actingAs($this->user)
            ->post(route('admin.obras.import-conceptos', $obra), [
                'csv_file' => $file,
            ]);

        $this->assertDatabaseHas('conceptos', [
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Original',
            'version' => 2,
        ]);

        $csvContent2 = "PLANO,PIEZA,CONCEPTO,LARGO,KG.UNIT.,CANT.,TOTAL KG.,OBSERVACIONES\n";
        $csvContent2 .= "MK-100,PIEZA,Updated higher,4.00,30.000,8,240.00,3\n";

        $file2 = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent2);

        $this->actingAs($this->user)
            ->post(route('admin.obras.import-conceptos', $obra), [
                'csv_file' => $file2,
            ]);

        $this->assertDatabaseHas('conceptos', [
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Updated higher',
            'version' => 3,
        ]);
    });
});
