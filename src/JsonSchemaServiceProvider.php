<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Kojirock5260\JsonSchemaValidate\Console\CacheSpecCommand;
use Kojirock5260\JsonSchemaValidate\Console\ClearSpecCommand;
use Kojirock5260\JsonSchemaValidate\Console\DiffSpecCommand;
use Kojirock5260\JsonSchemaValidate\Console\GenerateSpecCommand;
use Kojirock5260\JsonSchemaValidate\Middleware\ValidateOpenApi;
use Kojirock5260\JsonSchemaValidate\Observation\ObservationRecorder;
use Kojirock5260\JsonSchemaValidate\Observation\ObservationStore;
use Kojirock5260\JsonSchemaValidate\Spec\OperationResolver;
use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;
use Kojirock5260\JsonSchemaValidate\Validation\RequestValidator;
use Kojirock5260\JsonSchemaValidate\Validation\ResponseValidator;

class JsonSchemaServiceProvider extends ServiceProvider
{
    /**
     * 設定ファイルのパス。
     */
    private const string CONFIG_PATH = __DIR__.'/../config/json-schema.php';

    /**
     * 設定を既定値と統合し、検証に使う部品をコンテナへ登録する。
     */
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'json-schema');

        $this->app->singleton(
            SpecRepository::class,
            static function (Application $app): SpecRepository {
                $config = $app->make('config');
                $cache = $config->get('json-schema.cache');

                return new SpecRepository(
                    (string) $config->get('json-schema.path'),
                    $cache === null ? null : (string) $cache,
                );
            },
        );

        $this->app->singleton(
            OperationResolver::class,
            static fn (Application $app): OperationResolver => new OperationResolver(
                $app->make(SpecRepository::class),
                (string) $app->make('config')->get('json-schema.base_path'),
            ),
        );

        $this->app->singleton(
            ObservationStore::class,
            static fn (Application $app): ObservationStore => new ObservationStore(
                (string) $app->make('config')->get('json-schema.observations'),
            ),
        );

        $this->app->bind(
            ObservationRecorder::class,
            static fn (Application $app): ObservationRecorder => new ObservationRecorder(
                $app->make(ObservationStore::class),
            ),
        );

        $this->app->bind(
            ValidateOpenApi::class,
            static fn (Application $app): ValidateOpenApi => new ValidateOpenApi(
                $app->make(OperationResolver::class),
                new RequestValidator,
                new ResponseValidator,
                $app->make('config')->get('json-schema.observe') ? $app->make(ObservationRecorder::class) : null,
            ),
        );
    }

    /**
     * 設定ファイルを公開できるようにする。
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::CONFIG_PATH => config_path('json-schema.php')], 'config');
            $this->commands([
                CacheSpecCommand::class,
                ClearSpecCommand::class,
                DiffSpecCommand::class,
                GenerateSpecCommand::class,
            ]);
        }
    }
}
