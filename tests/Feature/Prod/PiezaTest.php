<?php

use App\Models\Obra;
use App\Models\Pieza;
use App\Models\Prod\Fabricado;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin piezas', function () {
    test('index page can be rendered', function () {
        Pieza::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.piezas.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/piezas/index')
            ->has('piezas.data', 3)
        );
    });

    test('index can filter by obra', function () {
        $obra = Obra::factory()->create();
        Pieza::factory()->count(2)->create(['obra_id' => $obra->id]);
        Pieza::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.piezas.index', ['obra_id' => $obra->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('piezas.data', 2)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.piezas.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/piezas/create')
            ->has('obras')
        );
    });

    test('pieza can be stored', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.piezas.store'), [
                'obra_id' => $obra->id,
                'marca' => 'MK-001',
                'descripcion' => 'Pieza de prueba',
                'longitud' => 3.50,
                'peso' => 25.50,
                'cantidad' => 10,
                'version' => 1,
            ]);

        $response->assertRedirect(route('admin.prod.piezas.index'));

        $this->assertDatabaseHas('piezas', [
            'obra_id' => $obra->id,
            'marca' => 'MK-001',
            'descripcion' => 'Pieza de prueba',
            'longitud' => 3.50,
            'peso' => 25.50,
            'cantidad' => 10,
            'version' => 1,
        ]);
    });

    test('pieza can be stored without optional fields', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.piezas.store'), [
                'obra_id' => $obra->id,
                'marca' => 'MK-002',
                'descripcion' => 'Pieza sin opcionales',
                'peso' => 10,
                'cantidad' => 5,
            ]);

        $response->assertRedirect(route('admin.prod.piezas.index'));

        $this->assertDatabaseHas('piezas', [
            'marca' => 'MK-002',
            'longitud' => null,
        ]);
    });

    test('pieza can be updated', function () {
        $pieza = Pieza::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.prod.piezas.update', $pieza), [
                'obra_id' => $pieza->obra_id,
                'marca' => 'MK-UPD',
                'descripcion' => 'Updated',
                'longitud' => 5.25,
                'peso' => 10,
                'cantidad' => 5,
                'version' => 2,
            ]);

        $response->assertRedirect(route('admin.prod.piezas.index'));

        $this->assertDatabaseHas('piezas', [
            'id' => $pieza->id,
            'marca' => 'MK-UPD',
            'longitud' => 5.25,
            'peso' => 10,
            'version' => 2,
        ]);
    });

    test('pieza can be deleted', function () {
        $pieza = Pieza::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.piezas.destroy', $pieza));

        $response->assertRedirect(route('admin.prod.piezas.index'));
        $this->assertDatabaseMissing('piezas', ['id' => $pieza->id]);
    });

    test('pieza cannot be deleted with fabricados', function () {
        $pieza = Pieza::factory()->create();
        Fabricado::factory()->create(['pieza_id' => $pieza->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.piezas.destroy', $pieza));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('piezas', ['id' => $pieza->id]);
    });

    test('validation requires marca and descripcion', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.piezas.store'), [
                'obra_id' => $obra->id,
                'peso' => 10,
                'cantidad' => 1,
            ]);

        $response->assertSessionHasErrors(['marca', 'descripcion']);
    });
});

describe('obra csv import', function () {
    test('csv imports piezas correctly', function () {
        $obra = Obra::factory()->create();

        $csvContent = "PLANO,PIEZA,CONCEPTO,LARGO,KG.UNIT.,CANT.,TOTAL KG.,OBSERVACIONES\n";
        $csvContent .= "MK-100,PIEZA,Viga principal,3.50,25.50,10,255.00,1\n";
        $csvContent .= "MK-101,PIEZA,Columna,2.00,15.00,5,75.00,1\n";

        $file = UploadedFile::fake()->createWithContent('piezas.csv', $csvContent);

        $response = $this->actingAs($this->user)
            ->post(route('admin.obras.import-piezas', $obra), [
                'csv_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('piezas', [
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Viga principal',
            'longitud' => 3.50,
            'peso' => 25.50,
            'cantidad' => 10,
            'version' => 1,
        ]);

        $this->assertDatabaseHas('piezas', [
            'obra_id' => $obra->id,
            'marca' => 'MK-101',
        ]);

        expect(Pieza::where('obra_id', $obra->id)->count())->toBe(2);
    });

    test('csv import dedup by version only updates higher version', function () {
        $obra = Obra::factory()->create();
        Pieza::factory()->create([
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Original',
            'peso' => 10.00,
            'cantidad' => 5,
            'version' => 2,
        ]);

        // CSV has version 1 (lower) and version 3 (higher)
        $csvContent = "PLANO,PIEZA,CONCEPTO,LARGO,KG.UNIT.,CANT.,TOTAL KG.,OBSERVACIONES\n";
        $csvContent .= "MK-100,PIEZA,Updated lower,3.50,25.50,10,255.00,1\n";

        $file = UploadedFile::fake()->createWithContent('piezas.csv', $csvContent);

        $this->actingAs($this->user)
            ->post(route('admin.obras.import-piezas', $obra), [
                'csv_file' => $file,
            ]);

        // Should NOT update because CSV version (1) < existing version (2)
        $this->assertDatabaseHas('piezas', [
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Original',
            'version' => 2,
        ]);

        // Now import with higher version
        $csvContent2 = "PLANO,PIEZA,CONCEPTO,LARGO,KG.UNIT.,CANT.,TOTAL KG.,OBSERVACIONES\n";
        $csvContent2 .= "MK-100,PIEZA,Updated higher,4.00,30.00,8,240.00,3\n";

        $file2 = UploadedFile::fake()->createWithContent('piezas.csv', $csvContent2);

        $this->actingAs($this->user)
            ->post(route('admin.obras.import-piezas', $obra), [
                'csv_file' => $file2,
            ]);

        // Should update because CSV version (3) > existing version (2)
        $this->assertDatabaseHas('piezas', [
            'obra_id' => $obra->id,
            'marca' => 'MK-100',
            'descripcion' => 'Updated higher',
            'version' => 3,
        ]);

        // Still only 1 pieza with that marca
        expect(Pieza::where('obra_id', $obra->id)->where('marca', 'MK-100')->count())->toBe(1);
    });

    test('csv import requires file', function () {
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.obras.import-piezas', $obra), []);

        $response->assertSessionHasErrors(['csv_file']);
    });

    test('obra edit loads piezas', function () {
        $obra = Obra::factory()->create();
        Pieza::factory()->count(2)->create(['obra_id' => $obra->id]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.obras.edit', $obra));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/obras/edit')
            ->has('obra.piezas', 2)
        );
    });
});
