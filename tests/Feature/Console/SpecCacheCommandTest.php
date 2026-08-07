<?php

declare(strict_types=1);

/**
 * openapi:cache と openapi:clear が動作することを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;

beforeEach(function (): void {
    $this->cache = sys_get_temp_dir().'/openapi-command-test/openapi.cache';

    config()->set('json-schema.cache', $this->cache);
    app()->forgetInstance(SpecRepository::class);
});

afterEach(function (): void {
    @unlink($this->cache);
    @rmdir(dirname($this->cache));
});

it('writes the cache file', function (): void {
    $this->artisan('openapi:cache')->assertSuccessful();

    expect(is_file($this->cache))->toBeTrue();
});

it('reports the cache path', function (): void {
    $this->artisan('openapi:cache')
        ->expectsOutputToContain($this->cache)
        ->assertSuccessful();
});

it('fails when no cache path is configured', function (): void {
    config()->set('json-schema.cache', null);
    app()->forgetInstance(SpecRepository::class);

    $this->artisan('openapi:cache')
        ->expectsOutputToContain('No cache path is configured')
        ->assertFailed();
});

it('fails when the document is missing', function (): void {
    config()->set('json-schema.path', '/nowhere/openapi.yaml');
    app()->forgetInstance(SpecRepository::class);

    $this->artisan('openapi:cache')
        ->expectsOutputToContain('was not found')
        ->assertFailed();
});

it('removes the cache file', function (): void {
    $this->artisan('openapi:cache')->assertSuccessful();

    $this->artisan('openapi:clear')
        ->expectsOutputToContain('was removed')
        ->assertSuccessful();

    expect(is_file($this->cache))->toBeFalse();
});

it('reports when there is nothing to remove', function (): void {
    $this->artisan('openapi:clear')
        ->expectsOutputToContain('no cached OpenAPI document')
        ->assertSuccessful();
});

it('rebuilds the cache when run again', function (): void {
    $this->artisan('openapi:cache')->assertSuccessful();
    $first = filesize($this->cache);

    $this->artisan('openapi:cache')->assertSuccessful();

    expect(filesize($this->cache))->toBe($first);
});

it('serves validated requests from the cached document', function (): void {
    $this->artisan('openapi:cache')->assertSuccessful();
    app()->forgetInstance(SpecRepository::class);

    expect(app(SpecRepository::class)->isCached())->toBeTrue()
        ->and(app(SpecRepository::class)->version())->toBe('3.1.0');
});
