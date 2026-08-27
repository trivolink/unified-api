<?php

namespace Spaseossr\UnifiedApi;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Inertia\ResponseFactory as InertiaResponseFactory;
use Spaseossr\UnifiedApi\Middleware\TransformRedirectsForApiClients;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        // Guarantee Inertia's bindings exist before ours, even when
        // package discovery is disabled. Registering twice is a no-op.
        $this->app->register(\Inertia\ServiceProvider::class);

        // Rebinding the same key wins because this provider loads after
        // Inertia's (we depend on it), and self-registration above also
        // ensures the ordering inside this method.
        $this->app->singleton(InertiaResponseFactory::class, UnifiedResponseFactory::class);

        $this->mergeConfigFrom(__DIR__.'/../config/unified-api.php', 'unified-api');
    }

    public function boot(Router $router): void
    {
        $this->callAfterResolving(HttpKernelContract::class, function ($kernel) {
            if ($kernel instanceof Kernel) {
                $kernel->pushMiddleware(TransformRedirectsForApiClients::class);
            }
        });

        $router->aliasMiddleware(
            'unified.csrf',
            Middleware\ValidateCsrfTokenExceptApiClients::class
        );

        if (config('unified-api.token_endpoint.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/token.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/unified-api.php' => config_path('unified-api.php'),
            ]);
        }
    }
}
