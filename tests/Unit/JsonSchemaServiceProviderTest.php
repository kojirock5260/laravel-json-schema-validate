<?php

declare(strict_types=1);

/**
 * JsonSchemaServiceProvider が設定を統合し、部品を登録することを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Spec\OperationResolver;
use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;

it('merges the default configuration', function (): void {
    // path はテスト用に差し替えているため、既定値は設定ファイル側で確認する
    expect(config()->has('json-schema.path'))->toBeTrue()
        ->and(config('json-schema.base_path'))->toBe('');
});

it('ships a default document path under the application root', function (): void {
    $defaults = require dirname(__DIR__, 2).'/config/json-schema.php';

    expect($defaults['path'])->toBe(base_path('openapi.yaml'))
        ->and($defaults['base_path'])->toBe('');
});

it('resolves the spec repository as a singleton', function (): void {
    expect(app(SpecRepository::class))->toBeInstanceOf(SpecRepository::class)
        ->and(app(SpecRepository::class))->toBe(app(SpecRepository::class));
});

it('resolves the operation resolver as a singleton', function (): void {
    expect(app(OperationResolver::class))->toBeInstanceOf(OperationResolver::class)
        ->and(app(OperationResolver::class))->toBe(app(OperationResolver::class));
});

it('builds the spec repository from the configured path', function (): void {
    config()->set('json-schema.path', fixturePath('openapi.yaml'));
    app()->forgetInstance(SpecRepository::class);

    expect(app(SpecRepository::class)->version())->toBe('3.1.0');
});

it('passes the configured base path to the resolver', function (): void {
    config()->set('json-schema.path', fixturePath('openapi.yaml'));
    config()->set('json-schema.base_path', 'api');
    app()->forgetInstance(SpecRepository::class);
    app()->forgetInstance(OperationResolver::class);

    $resolved = app(OperationResolver::class)->resolve(testRoute('api/members'), 'GET');

    expect($resolved)->not->toBeNull()
        ->and($resolved->operation->operationId)->toBe('listMembers');
});
