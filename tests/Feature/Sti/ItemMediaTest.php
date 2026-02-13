<?php

use App\Models\Sti\Item;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    Storage::fake('public');
});

describe('item media', function () {
    test('media can be uploaded to an item', function () {
        $item = Item::factory()->create();
        $file = UploadedFile::fake()->create('foto-item.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.items.media.store', $item), [
                'archivo' => $file,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('media', [
            'mediable_id' => $item->id,
            'mediable_type' => Item::class,
        ]);
        expect($item->media()->count())->toBe(1);
    });

    test('media can be deleted from an item', function () {
        $item = Item::factory()->create();
        $file = UploadedFile::fake()->create('foto-item.jpg', 100, 'image/jpeg');
        $path = $file->store('sti/items', 'public');

        $media = $item->media()->create([
            'descripcion' => 'foto-item.jpg',
            'path' => $path,
            'mime' => 'image/jpeg',
            'size' => $file->getSize(),
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.sti.items.media.destroy', [$item, $media]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    });

    test('media upload requires a file', function () {
        $item = Item::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.items.media.store', $item), []);

        $response->assertSessionHasErrors(['archivo']);
    });

    test('cannot delete media belonging to another item', function () {
        $item = Item::factory()->create();
        $otherItem = Item::factory()->create();

        $media = $otherItem->media()->create([
            'descripcion' => 'other.jpg',
            'path' => 'sti/items/other.jpg',
            'mime' => 'image/jpeg',
            'size' => 1000,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.sti.items.media.destroy', [$item, $media]));

        $response->assertNotFound();
    });
});
