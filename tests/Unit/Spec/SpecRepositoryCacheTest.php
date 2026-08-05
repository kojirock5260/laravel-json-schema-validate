<?php

declare(strict_types=1);

/**
 * SpecRepository のキャッシュ挙動を確認する。
 */

use Kojirock5260\JsonSchemaValidate\Exception\SpecException;
use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;

beforeEach(function (): void {
    $this->source = sys_get_temp_dir().'/openapi-cache-test/openapi.yaml';
    $this->cache = sys_get_temp_dir().'/openapi-cache-test/nested/openapi.cache';

    @mkdir(dirname($this->source), 0o755, true);
    copy(fixturePath('openapi.yaml'), $this->source);
});

afterEach(function (): void {
    @unlink($this->source);
    @unlink($this->cache);
    @rmdir(dirname($this->cache));
    @rmdir(dirname($this->source));
});

it('reports no cache before it is written', function (): void {
    expect((new SpecRepository($this->source, $this->cache))->isCached())->toBeFalse();
});

it('writes a cache file and creates missing directories', function (): void {
    $path = (new SpecRepository($this->source, $this->cache))->cache();

    expect($path)->toBe($this->cache)
        ->and(is_file($this->cache))->toBeTrue();
});

it('restores the document from the cache', function (): void {
    (new SpecRepository($this->source, $this->cache))->cache();

    $restored = new SpecRepository($this->source, $this->cache);

    expect($restored->isCached())->toBeTrue()
        ->and($restored->version())->toBe('3.1.0')
        ->and($restored->load()->paths->getPath('/members')->get->operationId)->toBe('listMembers');
});

it('serves the same document whether or not a cache is used', function (): void {
    (new SpecRepository($this->source, $this->cache))->cache();

    $cached = (new SpecRepository($this->source, $this->cache))->load();
    $parsed = (new SpecRepository($this->source))->load();

    expect($cached->getSerializableData())->toEqual($parsed->getSerializableData());
});

it('ignores the cache when the document has changed', function (): void {
    (new SpecRepository($this->source, $this->cache))->cache();

    touch($this->source, time() + 10);
    clearstatcache();

    expect((new SpecRepository($this->source, $this->cache))->isCached())->toBeFalse();
});

it('still loads the document when the cache is stale', function (): void {
    (new SpecRepository($this->source, $this->cache))->cache();

    touch($this->source, time() + 10);
    clearstatcache();

    expect((new SpecRepository($this->source, $this->cache))->version())->toBe('3.1.0');
});

it('ignores a corrupt cache file', function (): void {
    (new SpecRepository($this->source, $this->cache))->cache();
    file_put_contents($this->cache, 'not serialized data');

    $repository = new SpecRepository($this->source, $this->cache);

    expect($repository->isCached())->toBeFalse()
        ->and($repository->version())->toBe('3.1.0');
});

it('removes the cache file', function (): void {
    $repository = new SpecRepository($this->source, $this->cache);
    $repository->cache();

    expect($repository->forgetCache())->toBeTrue()
        ->and(is_file($this->cache))->toBeFalse()
        ->and($repository->forgetCache())->toBeFalse();
});

it('throws when caching without a configured path', function (): void {
    (new SpecRepository($this->source))->cache();
})->throws(SpecException::class, 'No cache path is configured');

it('works without a cache path at all', function (): void {
    $repository = new SpecRepository($this->source);

    expect($repository->isCached())->toBeFalse()
        ->and($repository->version())->toBe('3.1.0')
        ->and($repository->forgetCache())->toBeFalse();
});
