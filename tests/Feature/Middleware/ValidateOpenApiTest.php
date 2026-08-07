<?php

declare(strict_types=1);

/**
 * ValidateOpenApi が Laravel のパイプライン上で機能することを確認する。
 */

use Illuminate\Support\Facades\Route;
use Kojirock5260\JsonSchemaValidate\Exception\RequestValidationException;
use Kojirock5260\JsonSchemaValidate\Exception\ResponseValidationException;
use Kojirock5260\JsonSchemaValidate\Middleware\ValidateOpenApi;

/**
 * ミドルウェアを適用したルートを登録する。
 *
 * @param  string  $method  HTTP メソッド
 * @param  string  $uri  ルート URI
 * @param  Closure  $handler  ルートの処理
 * @param  string  ...$options  ミドルウェアへ渡す指定
 */
function defineValidatedRoute(string $method, string $uri, Closure $handler, string ...$options): void
{
    $middleware = ValidateOpenApi::class.($options === [] ? '' : ':'.implode(',', $options));

    Route::middleware($middleware)->{strtolower($method)}($uri, $handler);
}

/**
 * 仕様に適合する一覧レスポンスを返すハンドラを登録する。
 *
 * @param  string  ...$options  ミドルウェアへ渡す指定
 */
function defineMemberListRoute(string ...$options): void
{
    defineValidatedRoute('GET', 'members', static fn () => response()
        ->json(['data' => [['name' => 'kojirock', 'status' => 'active']]])
        ->header('X-Total-Count', '1'), ...$options);
}

it('lets a valid request and response through', function (): void {
    defineMemberListRoute();

    $this->getJson('/members?page=3')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'kojirock');
});

it('rejects an invalid request with a 422 and laravel error format', function (): void {
    defineMemberListRoute();

    $this->getJson('/members')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['page']);
});

it('reports body fields under their own names', function (): void {
    defineValidatedRoute('POST', 'members', static fn () => response()->json([], 201));

    $this->postJson('/members', ['name' => 'kojirock', 'status' => 'retired'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('throws a request validation exception that can be caught', function (): void {
    defineMemberListRoute();

    $this->withoutExceptionHandling();

    expect(fn () => $this->getJson('/members'))->toThrow(RequestValidationException::class);
});

it('rejects a response that does not match the specification', function (): void {
    defineValidatedRoute('GET', 'members', static fn () => response()
        ->json(['data' => [['name' => 'kojirock', 'status' => 'retired']]])
        ->header('X-Total-Count', '1'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->getJson('/members?page=1'))->toThrow(ResponseValidationException::class);
});

it('reports a missing response header', function (): void {
    defineValidatedRoute('GET', 'members', static fn () => response()
        ->json(['data' => []]));

    $this->withoutExceptionHandling();

    expect(fn () => $this->getJson('/members?page=1'))
        ->toThrow(ResponseValidationException::class, 'X-Total-Count');
});

it('skips response validation when asked', function (): void {
    defineValidatedRoute('GET', 'members', static fn () => response()
        ->json(['unexpected' => true]), ValidateOpenApi::SKIP_RESPONSE);

    $this->getJson('/members?page=1')->assertOk();
});

it('skips request validation when asked', function (): void {
    defineMemberListRoute(ValidateOpenApi::SKIP_REQUEST);

    $this->getJson('/members')->assertOk();
});

it('lets routes that the spec does not define through', function (): void {
    defineValidatedRoute('GET', 'unknown', static fn () => response()->json(['anything' => true]));

    $this->getJson('/unknown')->assertOk();
});

it('validates path parameters through the pipeline', function (): void {
    defineValidatedRoute('GET', 'members/{member}', static fn () => response()->noContent(200));

    $this->getJson('/members/7')->assertOk();
    $this->getJson('/members/0')->assertStatus(422)->assertJsonValidationErrors(['memberId']);
});
