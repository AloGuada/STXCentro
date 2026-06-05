<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Registro;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin conceptos', function () {
    test('index page shows obras', function () {
        $obra = Obra::factory()->create();
        Concepto::factory()->count(3)->create(['obra_id' => $obra->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/conceptos/index')
            ->has('obras.data')
        );
    });

    test('show by obra lists conceptos', function () {
        $obra = Obra::factory()->create();
        Concepto::factory()->count(2)->create(['obra_id' => $obra->id]);
        Concepto::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.show-by-obra', $obra));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/conceptos/show')
            ->has('conceptos', 2)
            ->has('obra')
        );
    });

    test('create page can be rendered', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.create', ['obra_id' => $obra->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/conceptos/create')
            ->has('obra')
        );
    });

    test('concepto can be stored', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.store'), [
                'obra_id' => $obra->id,
                'marca' => 'MK-001',
                'descripcion' => 'Concepto de prueba',
                'cantidad' => 12,
                'peso_unitario' => 25.500,
                'version' => 1,
                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.prod.conceptos.show-by-obra', $obra));

        $this->assertDatabaseHas('conceptos', [
            'obra_id' => $obra->id,
            'marca' => 'MK-001',
            'descripcion' => 'Concepto de prueba',
            'cantidad' => 12,
        ]);
    });

    test('concepto can be stored without optional fields', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.store'), [
                'obra_id' => $obra->id,
                'marca' => 'MK-002',
                'descripcion' => 'Sin opcionales',
                'cantidad' => 0,
                'peso_unitario' => 10.000,
            ]);

        $response->assertRedirect(route('admin.prod.conceptos.show-by-obra', $obra));

        $this->assertDatabaseHas('conceptos', [
            'marca' => 'MK-002',
            'activo' => true,
            'version' => 1,
        ]);
    });

    test('concepto can be updated', function () {
        $concepto = Concepto::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.prod.conceptos.update', $concepto), [
                'obra_id' => $concepto->obra_id,
                'marca' => 'MK-UPD',
                'descripcion' => 'Updated',
                'cantidad' => 7,
                'peso_unitario' => 15.250,
                'version' => 2,
                'activo' => false,
            ]);

        $response->assertRedirect(route('admin.prod.conceptos.show-by-obra', $concepto->obra_id));

        $this->assertDatabaseHas('conceptos', [
            'id' => $concepto->id,
            'marca' => 'MK-UPD',
            'cantidad' => 7,
            'version' => 2,
            'activo' => false,
        ]);
    });

    test('concepto can be deleted', function () {
        $concepto = Concepto::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.conceptos.destroy', $concepto));

        $response->assertRedirect(route('admin.prod.conceptos.show-by-obra', $concepto->obra_id));
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
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.store'), [
                'obra_id' => $obra->id,
                'peso_unitario' => 10,
            ]);

        $response->assertSessionHasErrors(['marca', 'descripcion']);
    });
});

describe('conceptos csv import from show-by-obra', function () {
    test('csv imports with new format correctly', function () {
        $obra = Obra::factory()->create();

        $csvContent = "ID de Marca,Marca,Descripción,cantidad,Peso(T),Revisión de documentos,(Long_Ensamble)\n";
        $csvContent .= "1,MK-100,Viga principal,10,0.0255,REV 1,3.50\n";
        $csvContent .= "2,MK-101,Columna,5,0.015,REV 2,2.00\n";

        $file = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.import-csv', $obra), [
                'csv_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('conceptos', [
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Viga principal',
            'peso_unitario' => 25.500, // 0.0255 T * 1000
            'version' => 1,
        ]);

        $this->assertDatabaseHas('conceptos', [
            'obra_id' => $obra->id,
            'marca' => 'MK-101',
            'peso_unitario' => 15.000, // 0.015 T * 1000
            'version' => 2,
        ]);

        expect(Concepto::where('obra_id', $obra->id)->count())->toBe(2);
    });

    test('csv import converts tons to kilos and extracts REV version', function () {
        $obra = Obra::factory()->create();

        $csvContent = "ID de Marca,Marca,Descripción,cantidad,Peso(T),Revisión de documentos,(Long_Ensamble)\n";
        $csvContent .= "1,MK-200,Placa base,4,1.5,REV 3,1.00\n";

        $file = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent);

        $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.import-csv', $obra), [
                'csv_file' => $file,
            ]);

        $this->assertDatabaseHas('conceptos', [
            'obra_id' => $obra->id,
            'marca' => 'MK-200',
            'peso_unitario' => 1500.000, // 1.5 T * 1000
            'version' => 3,
        ]);
    });

    test('csv import dedup only updates higher version', function () {
        $obra = Obra::factory()->create();
        Concepto::factory()->create([
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Original',
            'peso_unitario' => 25.500,
            'version' => 3,
        ]);

        // Lower version should NOT update
        $csvContent = "ID de Marca,Marca,Descripción,cantidad,Peso(T),Revisión de documentos,(Long_Ensamble)\n";
        $csvContent .= "1,MK-100,Updated lower,10,0.030,REV 2,3.50\n";

        $file = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent);

        $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.import-csv', $obra), [
                'csv_file' => $file,
            ]);

        $this->assertDatabaseHas('conceptos', [
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Original',
            'version' => 3,
        ]);

        // Higher version SHOULD update
        $csvContent2 = "ID de Marca,Marca,Descripción,cantidad,Peso(T),Revisión de documentos,(Long_Ensamble)\n";
        $csvContent2 .= "1,MK-100,Updated higher,10,0.050,REV 5,4.00\n";

        $file2 = UploadedFile::fake()->createWithContent('conceptos.csv', $csvContent2);

        $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.import-csv', $obra), [
                'csv_file' => $file2,
            ]);

        $this->assertDatabaseHas('conceptos', [
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Updated higher',
            'peso_unitario' => 50.000,
            'version' => 5,
        ]);

        expect(Concepto::where('obra_id', $obra->id)->where('marca', 'MK-100')->count())->toBe(1);
    });

    test('csv import requires file', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.import-csv', $obra), []);

        $response->assertSessionHasErrors(['csv_file']);
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

        expect(Concepto::where('obra_id', $obra->id)->where('marca', 'MK-100')->count())->toBe(1);
    });

    test('csv import requires file', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.obras.import-conceptos', $obra), []);

        $response->assertSessionHasErrors(['csv_file']);
    });

    test('obra edit loads conceptos', function () {
        $obra = Obra::factory()->create();
        Concepto::factory()->count(2)->create(['obra_id' => $obra->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.obras.edit', $obra));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/obras/edit')
            ->has('obra.conceptos', 2)
        );
    });
});
