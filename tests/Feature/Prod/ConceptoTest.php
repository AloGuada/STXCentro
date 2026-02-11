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
    test('index page can be rendered', function () {
        Concepto::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/conceptos/index')
            ->has('conceptos.data', 3)
        );
    });

    test('index can filter by obra', function () {
        $obra = Obra::factory()->create();
        Concepto::factory()->count(2)->create(['obra_id' => $obra->id]);
        Concepto::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.index', ['obra_id' => $obra->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('conceptos.data', 2)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.conceptos.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/conceptos/create')
            ->has('obras')
        );
    });

    test('concepto can be stored', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.store'), [
                'obra_id' => $obra->id,
                'marca' => 'MK-001',
                'descripcion' => 'Concepto de prueba',
                'peso_unitario' => 25.500,
                'version' => 1,
                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.prod.conceptos.index'));

        $this->assertDatabaseHas('conceptos', [
            'obra_id' => $obra->id,
            'marca' => 'MK-001',
            'descripcion' => 'Concepto de prueba',
        ]);
    });

    test('concepto can be stored without optional fields', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.conceptos.store'), [
                'obra_id' => $obra->id,
                'marca' => 'MK-002',
                'descripcion' => 'Sin opcionales',
                'peso_unitario' => 10.000,
            ]);

        $response->assertRedirect(route('admin.prod.conceptos.index'));

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
                'peso_unitario' => 15.250,
                'version' => 2,
                'activo' => false,
            ]);

        $response->assertRedirect(route('admin.prod.conceptos.index'));

        $this->assertDatabaseHas('conceptos', [
            'id' => $concepto->id,
            'marca' => 'MK-UPD',
            'version' => 2,
            'activo' => false,
        ]);
    });

    test('concepto can be deleted', function () {
        $concepto = Concepto::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.conceptos.destroy', $concepto));

        $response->assertRedirect(route('admin.prod.conceptos.index'));
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
