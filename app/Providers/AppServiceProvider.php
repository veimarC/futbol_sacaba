<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use App\Models\Campeonato;
use App\Models\Equipo;
use App\Policies\CampeonatoPolicy;
use App\Policies\EquipoPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::policy(Campeonato::class, CampeonatoPolicy::class);
        Gate::policy(Equipo::class, EquipoPolicy::class);

        // Registrar directivas Blade para facilitar comprobaciones por rol en las vistas.
        // @role('organizador') ... @endrole
        Blade::if('role', function ($rol) {
            $user = auth()->user();
            return $user && method_exists($user, 'isRol') && $user->isRol($rol);
        });

        // @hasanyrol(['organizador','desarrollador']) ... @endhasanyrol
        Blade::if('hasanyrol', function ($roles) {
            $user = auth()->user();
            return $user && method_exists($user, 'hasAnyRol') && $user->hasAnyRol($roles);
        });

        // Alias del middleware 'role' para usar en rutas: ->middleware('role:organizador|desarrollador')
        // Se registra vía Router para evitar depender de app/Http/Kernel.php (ausente en este proyecto).
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('role', \App\Http\Middleware\CheckRole::class);
    }
}
