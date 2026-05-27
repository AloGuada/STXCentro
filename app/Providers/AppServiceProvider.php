<?php

namespace App\Providers;

use App\Events\Costos\PresupuestoExcedido;
use App\Listeners\Costos\NotificarAprobadoresPresupuesto;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Usuario;
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
    }
}
