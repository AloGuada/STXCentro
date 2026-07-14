<?php

namespace App\Http\Middleware;

use App\Models\BadgeConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'esProduccion' => app()->isProduction(),
            'auth' => fn () => $isDrive
                ? [
                    'user' => $request->user('externo')?->load('carpetas'),
                    'guard' => 'externo',
                    'permissions' => [],
                    'badges' => [],
                ]
                : ($isPortal
                    ? [
                        'user' => $request->user('proveedor'),
                        'guard' => 'proveedor',
                        'permissions' => [],
                        'badges' => [],
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
                        'roles' => $request->user()
                            ? DB::table('model_has_roles')
                                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                                ->where('model_has_roles.model_uuid', $request->user()->getKey())
                                ->pluck('roles.name')
                                ->toArray()
                            : [],
                        'badges' => $request->user()
                            ? $this->computeBadges($request)
                            : [],
                        'dg_puede_subir' => $request->user()
                            ? DB::table('dg_carpeta_usuario')
                                ->where('usuario_id', $request->user()->getKey())
                                ->where('puede_escribir', true)
                                ->exists()
                            : false,
                        'es_aprobador_costos' => $request->user()
                            ? $request->user()->can('aprobador-costos')
                            : false,
                    ]),
            'flash' => fn () => [
                'permiso' => $request->session()->get('permiso'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Computa badges para el sidebar: nativos (por usuario) + configurables (por rol).
     *
     * @return array<string, array{count: int, filterHref: string|null}>
     */
    private function computeBadges(Request $request): array
    {
        $badges = $this->computeNativeBadges($request);

        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_uuid', $request->user()->getKey())
            ->pluck('roles.name')
            ->toArray();

        if (! empty($roles)) {
            $searchRoles = $roles;
            if (in_array('super-admin', $roles)) {
                $searchRoles = BadgeConfig::activo()->distinct()->pluck('rol')->toArray();
            }

            $configs = BadgeConfig::activo()
                ->whereIn('rol', $searchRoles)
                ->get();

            foreach ($configs as $config) {
                $count = $this->computeBadgeCount($config);

                if ($count > 0) {
                    $key = $config->nav_href;

                    if (isset($badges[$key])) {
                        $badges[$key]['count'] += $count;
                    } else {
                        $badges[$key] = [
                            'count' => $count,
                            'filterHref' => $config->filter_href,
                        ];
                    }
                }
            }
        }

        return $badges;
    }

    /**
     * Badges nativos que dependen del usuario específico (no del rol).
     *
     * @return array<string, array{count: int, filterHref: string|null}>
     */
    private function computeNativeBadges(Request $request): array
    {
        $badges = [];
        $userId = $request->user()->getKey();

        $pendientes = DB::table('costos_aprobaciones as a')
            ->where('a.aprobador_id', $userId)
            ->where('a.estatus', 'pendiente')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('costos_aprobaciones as prev')
                    ->whereColumn('prev.aprobable_type', 'a.aprobable_type')
                    ->whereColumn('prev.aprobable_id', 'a.aprobable_id')
                    ->where('prev.estatus', 'pendiente')
                    ->whereColumn('prev.nivel', '<', 'a.nivel');
            })
            ->count();

        if ($pendientes > 0) {
            $badges['/admin/costos/aprobaciones'] = [
                'count' => $pendientes,
                'filterHref' => '/admin/costos/aprobaciones',
            ];
        }

        return $badges;
    }

    /**
     * Ejecuta la query de conteo para una badge config.
     */
    private function computeBadgeCount(BadgeConfig $config): int
    {
        $query = DB::table($config->tabla);

        $value = $this->resolveValue($config->valor_estatus, $config->operador);
        $query->where($config->campo_estatus, $config->operador, $value);

        if ($config->condiciones_extra) {
            foreach ($config->condiciones_extra as $condicion) {
                // Condición de existencia de relación (ej. "tiene al menos una OC
                // configurada"): whereExists contra otra tabla por su FK.
                if (($condicion['tipo'] ?? 'campo') === 'existe') {
                    $query->whereExists(function ($q) use ($config, $condicion) {
                        $q->select(DB::raw(1))
                            ->from($condicion['tabla'])
                            ->whereColumn($condicion['tabla'].'.'.$condicion['fk'], $config->tabla.'.id');
                    });

                    continue;
                }

                $op = $condicion['operador'] ?? '=';
                $val = $this->resolveValue($condicion['valor'], $op);
                $query->where($condicion['campo'], $op, $val);
            }
        }

        // Las facturas de OC de contado (pagadas por anticipo o solicitud de
        // pago) son solo el comprobante fiscal de un pago ya realizado; no
        // entran al flujo de aprobación/pago, así que no cuentan en las
        // notificaciones.
        if ($config->tabla === 'costos_facturas') {
            $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('costos_ordenes_compra')
                    ->whereColumn('costos_ordenes_compra.id', 'costos_facturas.orden_compra_id')
                    ->where('costos_ordenes_compra.tipo_pago', 'contado');
            });
        }

        return $query->count();
    }

    /**
     * Resuelve valores dinámicos (ej: "-7 days" → Carbon date).
     */
    private function resolveValue(mixed $value, string $operador): mixed
    {
        if (is_string($value) && in_array($operador, ['>=', '<=', '>', '<']) && preg_match('/^-?\d+\s+(days?|hours?|minutes?)$/', $value)) {
            return Carbon::now()->modify($value);
        }

        return $value;
    }
}
