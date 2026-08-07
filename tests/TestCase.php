<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Tests;

use Illuminate\Foundation\Application;
use Kojirock5260\JsonSchemaValidate\JsonSchemaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * テスト用アプリケーションに登録するサービスプロバイダを返す。
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [JsonSchemaServiceProvider::class];
    }

    /**
     * テスト用アプリケーションの設定を差し替える。
     *
     * @param  Application  $app
     */
    #[\Override]
    protected function defineEnvironment($app): void
    {
        $app->make('config')->set('json-schema.path', __DIR__.'/fixtures/openapi.yaml');
    }
}
