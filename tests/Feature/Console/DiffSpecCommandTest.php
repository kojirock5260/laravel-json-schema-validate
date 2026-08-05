<?php

declare(strict_types=1);

/**
 * openapi:diff が観測と spec の食い違いを報告できることを確認する。
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Kojirock5260\JsonSchemaValidate\Middleware\ValidateOpenApi;
use Kojirock5260\JsonSchemaValidate\Observation\ObservationStore;

beforeEach(function (): void {
    $this->observations = sys_get_temp_dir().'/openapi-diff-test/observations.jsonl';

    config()->set('json-schema.observe', true);
    config()->set('json-schema.observations', $this->observations);

    app()->forgetInstance(ObservationStore::class);
    @unlink($this->observations);
});

afterEach(function (): void {
    @unlink($this->observations);
    @rmdir(dirname($this->observations));
});

/**
 * openapi:diff を実行し、終了コードと出力を返す。
 *
 * @param  string  $options  コマンドに渡す追加の指定
 * @return array{code: int, output: string} 実行結果
 */
function diffSpec(string $options = ''): array
{
    $code = Artisan::call(trim('openapi:diff '.$options));

    return ['code' => $code, 'output' => Artisan::output()];
}

/**
 * 観測を有効にした GET ルートを登録する。
 *
 * @param  Closure  $handler  ルートの処理
 */
function defineObservedRoute(Closure $handler): void
{
    Route::middleware(ValidateOpenApi::class.':'.ValidateOpenApi::SKIP_RESPONSE)->get('members', $handler);
}

it('reports nothing when no traffic has been recorded', function (): void {
    $result = diffSpec();

    expect($result['output'])->toContain('No observations')
        ->and($result['code'])->toBe(0);
});

it('records traffic that passes through the middleware', function (): void {
    defineObservedRoute(static fn () => response()->json(['data' => [['name' => 'kojirock', 'status' => 'active']]]));

    $this->getJson('/members?page=1')->assertOk();

    expect(app(ObservationStore::class)->all())->toHaveCount(1);
});

it('reports nothing when the traffic matches the specification', function (): void {
    defineObservedRoute(static fn () => response()->json(['data' => [['name' => 'kojirock', 'status' => 'active']]]));

    $this->getJson('/members?page=1')->assertOk();

    expect(diffSpec()['output'])->toContain('matches the specification');
});

it('reports a response field that the specification does not describe', function (): void {
    defineObservedRoute(static fn () => response()
        ->json(['data' => [['name' => 'kojirock', 'status' => 'active', 'joinedAt' => '2026-08-05']]]));

    $this->getJson('/members?page=1')->assertOk();

    expect(diffSpec()['output'])
        ->toContain('GET /members')
        ->toContain('body.data[].joinedAt')
        ->toContain('not described in the specification');
});

it('accepts a field the specification already describes', function (): void {
    // nickname は type: [string, 'null'] として定義済み
    defineObservedRoute(static fn () => response()
        ->json(['data' => [['name' => 'kojirock', 'status' => 'active', 'nickname' => null]]]));

    $this->getJson('/members?page=1')->assertOk();

    expect(diffSpec()['output'])->toContain('matches the specification');
});

it('reports a type that the specification does not allow', function (): void {
    defineObservedRoute(static fn () => response()->json(['data' => [['name' => 123, 'status' => 'active']]]));

    $this->getJson('/members?page=1')->assertOk();

    expect(diffSpec()['output'])
        ->toContain('body.data[].name')
        ->toContain('Observed type integer but the specification allows string');
});

it('reports a required field that was not always present', function (): void {
    Route::middleware(ValidateOpenApi::class.':'.ValidateOpenApi::SKIP_RESPONSE)
        ->get('members', static fn () => response()->json(
            request()->query('full') === '1'
                ? ['data' => [['name' => 'kojirock', 'status' => 'active']]]
                : ['data' => [['status' => 'active']]],
        ));

    $this->getJson('/members?page=1&full=1')->assertOk();
    $this->getJson('/members?page=1')->assertOk();

    expect(diffSpec()['output'])
        ->toContain('body.data[]')
        ->toContain('requires name but they were not always present');
});

it('separates request and response sections', function (): void {
    Route::middleware(ValidateOpenApi::class.':'.ValidateOpenApi::SKIP_RESPONSE)
        ->post('members', static fn () => response()->json(['unexpected' => true], 201));

    $this->postJson('/members', ['name' => 'kojirock', 'status' => 'active', 'extra' => 1])->assertCreated();

    $output = diffSpec()['output'];

    expect($output)->toContain('POST /members')
        ->toContain('request')
        ->toContain('body.extra')
        ->toContain('response 201');
});

it('fails in strict mode when differences are found', function (): void {
    defineObservedRoute(static fn () => response()
        ->json(['data' => [['name' => 'kojirock', 'status' => 'active', 'extra' => true]]]));

    $this->getJson('/members?page=1')->assertOk();

    expect(diffSpec('--strict')['code'])->toBe(1);
});

it('succeeds in strict mode when nothing differs', function (): void {
    defineObservedRoute(static fn () => response()->json(['data' => [['name' => 'kojirock', 'status' => 'active']]]));

    $this->getJson('/members?page=1')->assertOk();

    expect(diffSpec('--strict')['code'])->toBe(0);
});

it('removes the observations when asked', function (): void {
    defineObservedRoute(static fn () => response()->json(['data' => []]));

    $this->getJson('/members?page=1')->assertOk();

    diffSpec('--forget');

    expect(app(ObservationStore::class)->all())->toBe([]);
});

it('does not record anything when observing is disabled', function (): void {
    config()->set('json-schema.observe', false);

    defineObservedRoute(static fn () => response()->json(['data' => []]));

    $this->getJson('/members?page=1')->assertOk();

    expect(app(ObservationStore::class)->all())->toBe([]);
});

it('does not record a request body for methods that carry none', function (): void {
    defineObservedRoute(static fn () => response()->json(['data' => []]));

    $this->getJson('/members?page=1')->assertOk();

    expect(app(ObservationStore::class)->all()[0]->requestBody)->toBeNull();
});

it('records the request body for methods that carry one', function (): void {
    Route::middleware(ValidateOpenApi::class.':'.ValidateOpenApi::SKIP_RESPONSE)
        ->post('members', static fn () => response()->json([], 201));

    $this->postJson('/members', ['name' => 'kojirock', 'status' => 'active'])->assertCreated();

    $recorded = app(ObservationStore::class)->all();

    expect($recorded)->toHaveCount(1)
        ->and($recorded[0]->requestBody['properties'])->toHaveKeys(['name', 'status']);
});

it('keeps no values from the recorded traffic', function (): void {
    defineObservedRoute(static fn () => response()
        ->json(['data' => [['name' => 'kojirock5260@example.com', 'status' => 'active']]]));

    $this->getJson('/members?page=1')->assertOk();

    expect(file_get_contents($this->observations))->not->toContain('example.com');
});
