<?php

namespace App\Providers;

use App\Events\Costos\PresupuestoExcedido;
use App\Listeners\Costos\NotificarAprobadoresPresupuesto;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Usuario;
use App\Services\Alm\ResolvedorArticulo;
use App\Services\Rh\Cv\OllamaClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OllamaClient::class, fn () => OllamaClient::fromConfig());

        // Singleton porque cachea la correspondencia producto => articulo, y un
        // import de layout resolveria el mismo producto cientos de veces.
        $this->app->singleton(ResolvedorArticulo::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerGates();
        $this->registerEvents();
    }

    protected function registerEvents(): void
    {
        Event::listen(PresupuestoExcedido::class, NotificarAprobadoresPresupuesto::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function registerGates(): void
    {
        Gate::define('aprobador-costos', fn (Usuario $user): bool => AprobacionDepartamento::query()
            ->where('aprobador_id', $user->getKey())
            ->exists()
        );

        Gate::define('costos.acceso', fn (Usuario $user): bool => $this->tienePermisoDelModulo($user, 'costos'));
    }

    /**
     * ¿El usuario tiene algún permiso del módulo, sin importar cuál?
     *
     * Para pantallas transversales que no ameritan permiso propio. Se consulta
     * a mano y filtrando sólo por `model_uuid`: las tablas pivote de Spatie
     * guardan `App\Models\Usuario` como model_type mientras que el usuario
     * autenticado es `App\Models\User`, así que filtrar por tipo no encuentra
     * nada.
     */
    protected function tienePermisoDelModulo(Usuario $user, string $modulo): bool
    {
        $prefijo = $modulo.'.%';

        $directo = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_uuid', $user->getKey())
            ->where('permissions.name', 'like', $prefijo)
            ->exists();

        return $directo || DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_uuid', $user->getKey())
            ->where('permissions.name', 'like', $prefijo)
            ->exists();
    }
}
