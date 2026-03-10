<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $isDrive = $request->user('externo') !== null;
        $isPortal = $request->user('proveedor') !== null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => fn () => $isDrive
                ? [
                    'user' => $request->user('externo')?->load('carpetas'),
                    'guard' => 'externo',
                    'permissions' => [],
                ]
                : ($isPortal
                    ? [
                        'user' => $request->user('proveedor'),
                        'guard' => 'proveedor',
                        'permissions' => [],
                    ]
                    : [
                        'user' => $request->user(),
                        'guard' => 'web',
                        'permissions' => $request->user()
                            ? DB::table('role_has_permissions')
                                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                                ->join('model_has_roles', 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
                                ->where('model_has_roles.model_uuid', $request->user()->getKey())
                                ->pluck('permissions.name')
                                ->unique()
                                ->values()
                                ->toArray()
                            : [],
                    ]),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
