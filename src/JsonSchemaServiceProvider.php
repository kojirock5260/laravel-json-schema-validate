<?php

declare(strict_types=1);

namespace Kojirock\JsonSchemaValidate;

use Illuminate\Support\ServiceProvider;

class JsonSchemaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/json-schema.php' => config_path('json-schema.php'),
            ], 'config');
        }
    }
}
