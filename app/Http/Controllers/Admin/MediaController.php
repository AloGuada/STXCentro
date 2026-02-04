<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaStoreRequest;
use App\Http\Requests\Admin\MediaUpdateRequest;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $media = Media::query()
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%")
                ->orWhere('path', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/media/index', [
            'media' => $media,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/media/create');
    }

    public function store(MediaStoreRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store('media', 'public');

        Media::create([
            'descripcion' => $request->descripcion,
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return to_route('admin.media.index');
    }

    public function edit(Media $medium): Response
    {
        return Inertia::render('admin/media/edit', [
            'media' => $medium,
        ]);
    }

    public function update(MediaUpdateRequest $request, Media $medium): RedirectResponse
    {
        $data = ['descripcion' => $request->descripcion];

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($medium->path);

            $file = $request->file('file');
            $data['path'] = $file->store('media', 'public');
            $data['mime'] = $file->getMimeType();
            $data['size'] = $file->getSize();
        }

        $medium->update($data);

        return to_route('admin.media.index');
    }

    public function destroy(Media $medium): RedirectResponse
    {
        Storage::disk('public')->delete($medium->path);
        $medium->delete();

        return to_route('admin.media.index');
    }
}
