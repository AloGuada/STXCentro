<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BadgeConfigStoreRequest;
use App\Http\Requests\Admin\BadgeConfigUpdateRequest;
use App\Models\BadgeConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BadgeConfigController extends Controller
{
    public function index(Request $request): Response
    {
        $configs = BadgeConfig::query()
            ->when($request->search, fn ($q, $s) => $q->where('nombre', 'like', "%{$s}%"))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/badge-configs/index', [
            'configs' => $configs,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/badge-configs/create', [
            'roles' => $this->getRoles(),
        ]);
    }

    public function store(BadgeConfigStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (isset($data['condiciones_extra']) && is_string($data['condiciones_extra'])) {
            $data['condiciones_extra'] = json_decode($data['condiciones_extra'], true);
        }

        BadgeConfig::create($data);

        return to_route('admin.badge-configs.index');
    }

    public function edit(BadgeConfig $badgeConfig): Response
    {
        return Inertia::render('admin/badge-configs/edit', [
            'badgeConfig' => $badgeConfig,
            'roles' => $this->getRoles(),
        ]);
    }

    public function update(BadgeConfigUpdateRequest $request, BadgeConfig $badgeConfig): RedirectResponse
    {
        $data = $request->validated();

        if (isset($data['condiciones_extra']) && is_string($data['condiciones_extra'])) {
            $data['condiciones_extra'] = json_decode($data['condiciones_extra'], true);
        }

        $badgeConfig->update($data);

        return to_route('admin.badge-configs.index');
    }

    public function destroy(BadgeConfig $badgeConfig): RedirectResponse
    {
        $badgeConfig->delete();

        return to_route('admin.badge-configs.index');
    }

    /**
     * @return list<string>
     */
    private function getRoles(): array
    {
        return DB::table('roles')
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
    }
}
