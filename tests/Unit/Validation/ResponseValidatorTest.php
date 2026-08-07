<?php

declare(strict_types=1);

/**
 * ResponseValidator がステータス・ヘッダ・ボディを検証できることを確認する。
 */

use Illuminate\Http\Response;
use Kojirock5260\JsonSchemaValidate\Spec\OperationResolver;
use Kojirock5260\JsonSchemaValidate\Validation\ResponseValidator;

/**
 * レスポンスを組み立てて検証する。
 *
 * @param  string  $routeUri  ルート定義の URI
 * @param  int  $status  ステータスコード
 * @param  mixed  $body  ボディに載せる値。null なら空ボディ
 * @param  array<string, string>  $headers  付与するヘッダ
 * @param  string  $method  HTTP メソッド
 * @return array<string, list<string>> 検証エラーの一覧
 */
function validateResponse(
    string $routeUri,
    int $status = 200,
    mixed $body = null,
    array $headers = [],
    string $method = 'GET',
): array {
    $content = $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR);
    $response = new Response($content, $status, $headers);
    $resolved = (new OperationResolver(specRepository()))->resolve(testRoute($routeUri, $method), $method);

    return (new ResponseValidator)->validate($resolved, $response);
}

/**
 * 妥当な一覧レスポンスの中身を返す。
 *
 * @return array<string, mixed> ボディに載せる値
 */
function validListBody(): array
{
    return ['data' => [['name' => 'kojirock', 'status' => 'active']]];
}

/**
 * 妥当な一覧レスポンスのヘッダを返す。
 *
 * @return array<string, string> 付与するヘッダ
 */
function validListHeaders(): array
{
    return ['Content-Type' => 'application/json', 'X-Total-Count' => '1'];
}

it('accepts a response that matches the spec', function (): void {
    expect(validateResponse('members', 200, validListBody(), validListHeaders()))->toBe([]);
});

it('reports a status code the operation does not define', function (): void {
    // /members/{memberId} は 200 しか定義していない
    $errors = validateResponse('members/{member}', 503);

    expect($errors)->toHaveKey('status')
        ->and($errors['status'][0])->toContain('503');
});

it('prefers an exact status over a range', function (): void {
    // 200 は完全一致があるので 4XX や default には落ちない
    $errors = validateResponse('members', 200, ['message' => 'x'], validListHeaders());

    expect($errors)->toHaveKey('body');
});

it('falls back to a range when there is no exact match', function (): void {
    $headers = ['Content-Type' => 'application/json'];

    expect(validateResponse('members', 404, ['message' => 'not found'], $headers))->toBe([])
        ->and(validateResponse('members', 404, ['error' => 'not found'], $headers))->toHaveKey('body');
});

it('falls back to default when neither exact nor range match', function (): void {
    $headers = ['Content-Type' => 'application/json'];

    expect(validateResponse('members', 503, ['error' => 'unavailable'], $headers))->toBe([])
        ->and(validateResponse('members', 503, ['message' => 'unavailable'], $headers))->toHaveKey('body');
});

it('reports a missing required response header', function (): void {
    $errors = validateResponse('members', 200, validListBody(), ['Content-Type' => 'application/json']);

    expect($errors)->toHaveKey('X-Total-Count')
        ->and($errors['X-Total-Count'][0])->toContain('required');
});

it('coerces and validates response header values', function (): void {
    $headers = ['Content-Type' => 'application/json', 'X-Total-Count' => '-1'];

    expect(validateResponse('members', 200, validListBody(), $headers))->toHaveKey('X-Total-Count');
});

it('ignores optional response headers when absent', function (): void {
    expect(validateResponse('members', 200, validListBody(), validListHeaders()))->toBe([]);
});

it('validates optional response headers when present', function (): void {
    $headers = validListHeaders() + ['X-Cache' => 'hit'];
    $broken = validListHeaders() + ['X-Cache' => 'stale'];

    expect(validateResponse('members', 200, validListBody(), $headers))->toBe([])
        ->and(validateResponse('members', 200, validListBody(), $broken))->toHaveKey('X-Cache');
});

it('reports a content type the response does not define', function (): void {
    $headers = ['Content-Type' => 'text/plain', 'X-Total-Count' => '1'];

    $errors = validateResponse('members', 200, validListBody(), $headers);

    expect($errors)->toHaveKey('body')
        ->and($errors['body'][0])->toContain('not defined');
});

it('reports an empty body when a schema is defined', function (): void {
    $errors = validateResponse('members', 200, null, validListHeaders());

    expect($errors)->toHaveKey('body')
        ->and($errors['body'][0])->toContain('empty');
});

it('skips body validation when the response defines no content', function (): void {
    // /members/{memberId} の 200 は description だけを持つ
    expect(validateResponse('members/{member}', 200))->toBe([]);
});

it('reports body fields using their own names', function (): void {
    $body = ['data' => [['name' => 'kojirock', 'status' => 'retired']]];

    expect(validateResponse('members', 200, $body, validListHeaders()))->toHaveKey('data.0.status');
});
