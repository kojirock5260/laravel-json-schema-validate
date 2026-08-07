<?php

declare(strict_types=1);

/**
 * openapi:generate が観測から OpenAPI ドキュメントを書き出せることを確認する。
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Kojirock5260\JsonSchemaValidate\Middleware\ValidateOpenApi;
use Kojirock5260\JsonSchemaValidate\Observation\ObservationStore;
use Kojirock5260\JsonSchemaValidate\Spec\OperationResolver;
use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/openapi-generate-test';
    $this->observations = $this->directory.'/observations.jsonl';
    $this->generated = $this->directory.'/generated.yaml';

    config()->set('json-schema.observe', true);
    config()->set('json-schema.observations', $this->observations);
    config()->set('json-schema.cache', null);

    app()->forgetInstance(ObservationStore::class);
    app()->forgetInstance(SpecRepository::class);

    @unlink($this->observations);
    @unlink($this->generated);
});

afterEach(function (): void {
    @unlink($this->observations);
    @unlink($this->generated);
    @rmdir($this->directory);
});

/**
 * spec に定義の無いルートを、観測付きで登録する。
 *
 * @param  string  $uri  ルート URI
 * @param  Closure  $handler  ルートの処理
 * @param  string  $method  HTTP メソッド
 */
function defineUnknownRoute(string $uri, Closure $handler, string $method = 'get'): void
{
    Route::middleware(ValidateOpenApi::class)->{$method}($uri, $handler);
}

it('fails when nothing has been recorded', function (): void {
    expect(Artisan::call('openapi:generate'))->toBe(1)
        ->and(Artisan::output())->toContain('No observations');
});

it('records routes that the specification does not define', function (): void {
    defineUnknownRoute('widgets/{widget}', static fn () => response()->json(['id' => 7, 'label' => 'a']));

    $this->getJson('/widgets/7')->assertOk();

    expect(app(ObservationStore::class)->all()[0]->path)->toBe('/widgets/{widget}');
});

it('writes a document to the given file', function (): void {
    defineUnknownRoute('widgets/{widget}', static fn () => response()->json(['id' => 7, 'label' => 'a']));

    $this->getJson('/widgets/7')->assertOk();

    expect(Artisan::call('openapi:generate --output='.$this->generated))->toBe(0);

    $document = Yaml::parseFile($this->generated);

    expect($document['openapi'])->toBe('3.1.0')
        ->and($document['paths'])->toHaveKey('/widgets/{widget}');
});

it('describes the observed response body', function (): void {
    defineUnknownRoute('widgets/{widget}', static fn () => response()->json(['id' => 7, 'label' => 'a']));

    $this->getJson('/widgets/7')->assertOk();
    Artisan::call('openapi:generate --output='.$this->generated);

    $schema = Yaml::parseFile($this->generated)['paths']['/widgets/{widget}']['get']['responses']['200']['content']['application/json']['schema'];

    expect($schema['properties'])->toBe([
        'id' => ['type' => 'integer'],
        'label' => ['type' => 'string'],
    ])->and($schema['required'])->toBe(['id', 'label']);
});

it('writes status codes as strings', function (): void {
    defineUnknownRoute('widgets', static fn () => response()->json(['ok' => true]));

    $this->getJson('/widgets')->assertOk();
    Artisan::call('openapi:generate --output='.$this->generated);

    // OpenAPI のキーは文字列。整数キーとして書き出されていないことを確かめる
    // （PHP に読み戻すと数値キーへ戻るため、確認できるのはファイル上の表記のみ）
    expect(file_get_contents($this->generated))->toContain("'200':")
        ->and(file_get_contents($this->generated))->not->toContain("\n        200:");
});

it('describes observed query parameters', function (): void {
    defineUnknownRoute('widgets', static fn () => response()->json([]));

    $this->getJson('/widgets?page=2&q=abc')->assertOk();
    $this->getJson('/widgets?page=3')->assertOk();

    Artisan::call('openapi:generate --output='.$this->generated);

    $parameters = Yaml::parseFile($this->generated)['paths']['/widgets']['get']['parameters'];

    expect($parameters)->toBe([
        ['name' => 'page', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']],
        ['name' => 'q', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
    ]);
});

it('describes the observed request body', function (): void {
    defineUnknownRoute('widgets', static fn () => response()->json([], 201), 'post');

    $this->postJson('/widgets', ['label' => 'a'])->assertCreated();
    Artisan::call('openapi:generate --output='.$this->generated);

    $body = Yaml::parseFile($this->generated)['paths']['/widgets']['post']['requestBody'];

    expect($body['content']['application/json']['schema']['properties'])->toHaveKey('label');
});

it('writes json when asked', function (): void {
    defineUnknownRoute('widgets', static fn () => response()->json([]));

    $this->getJson('/widgets')->assertOk();

    $path = $this->directory.'/generated.json';
    Artisan::call('openapi:generate --format=json --output='.$path);

    expect(json_decode((string) file_get_contents($path), true)['openapi'])->toBe('3.1.0');

    @unlink($path);
});

it('prints the document when no output file is given', function (): void {
    defineUnknownRoute('widgets', static fn () => response()->json([]));

    $this->getJson('/widgets')->assertOk();
    Artisan::call('openapi:generate');

    expect(Artisan::output())->toContain('openapi: 3.1.0');
});

it('generates a document that validates the traffic it came from', function (): void {
    defineUnknownRoute('widgets/{widget}', static fn () => response()->json(['id' => 7, 'label' => 'a']));

    $this->getJson('/widgets/7?page=2')->assertOk();
    Artisan::call('openapi:generate --output='.$this->generated);

    // 生成した spec を読み込ませ、同じリクエストが検証を通ることを確かめる
    config()->set('json-schema.path', $this->generated);
    config()->set('json-schema.observe', false);
    app()->forgetInstance(SpecRepository::class);
    app()->forgetInstance(OperationResolver::class);

    $this->getJson('/widgets/7?page=2')->assertOk();
});

it('reports a difference once the implementation drifts from the generated document', function (): void {
    Route::middleware(ValidateOpenApi::class)->get('widgets/{widget}', static fn () => response()->json(
        request()->query('extra') === '1'
            ? ['id' => 7, 'label' => 'a', 'extra' => true]
            : ['id' => 7, 'label' => 'a'],
    ));

    $this->getJson('/widgets/7')->assertOk();
    Artisan::call('openapi:generate --output='.$this->generated);

    config()->set('json-schema.path', $this->generated);
    app()->forgetInstance(SpecRepository::class);
    app()->forgetInstance(OperationResolver::class);
    app(ObservationStore::class)->forget();

    $this->getJson('/widgets/7?extra=1');

    Artisan::call('openapi:diff');

    expect(Artisan::output())->toContain('body.extra');
});
