<?php

declare(strict_types=1);

/**
 * RequestValidator がパラメータとリクエストボディを検証できることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Spec\OperationResolver;
use Kojirock5260\JsonSchemaValidate\Validation\RequestValidator;

/**
 * リクエストを解決し、検証結果を返す。
 *
 * @param  string  $routeUri  ルート定義の URI
 * @param  string  $target  実際にアクセスする URI
 * @param  string  $method  HTTP メソッド
 * @param  string|null  $content  リクエストボディ
 * @param  array<string, string>  $headers  付与するヘッダ
 * @return array<string, list<string>> 検証エラーの一覧
 */
function validateRequest(
    string $routeUri,
    string $target,
    string $method = 'GET',
    ?string $content = null,
    array $headers = [],
): array {
    $request = requestFor($routeUri, $target, $method, $content, $headers);
    $resolved = (new OperationResolver(specRepository()))->resolve($request->route(), $method);

    return (new RequestValidator)->validate($resolved, $request);
}

it('accepts a request that matches the spec', function (): void {
    expect(validateRequest('members', '/members?page=3'))->toBe([]);
});

it('reports a missing required query parameter', function (): void {
    $errors = validateRequest('members', '/members');

    expect($errors)->toHaveKey('page')
        ->and($errors['page'][0])->toContain('required');
});

it('reports a query parameter that cannot be coerced to the declared type', function (): void {
    expect(validateRequest('members', '/members?page=abc'))->toHaveKey('page');
});

it('applies numeric constraints after coercion', function (): void {
    expect(validateRequest('members', '/members?page=0'))->toHaveKey('page')
        ->and(validateRequest('members', '/members?page=1'))->toBe([]);
});

it('validates optional query parameters when they are present', function (): void {
    expect(validateRequest('members', '/members?page=1&status=active'))->toBe([])
        ->and(validateRequest('members', '/members?page=1&status=unknown'))->toHaveKey('status');
});

it('ignores optional parameters when they are absent', function (): void {
    expect(validateRequest('members', '/members?page=1'))->toBe([]);
});

it('validates header parameters', function (): void {
    expect(validateRequest('members', '/members?page=1', headers: ['X-Request-Id' => 'abcd']))->toBe([])
        ->and(validateRequest('members', '/members?page=1', headers: ['X-Request-Id' => 'ab']))
        ->toHaveKey('X-Request-Id');
});

it('validates path parameters declared at the path item level', function (): void {
    expect(validateRequest('members/{member}', '/members/7'))->toBe([])
        ->and(validateRequest('members/{member}', '/members/0'))->toHaveKey('memberId');
});

it('reports a missing required body', function (): void {
    $errors = validateRequest('members', '/members', 'POST');

    expect($errors)->toHaveKey('body')
        ->and($errors['body'][0])->toContain('required');
});

it('reports a body that is not valid json', function (): void {
    $errors = validateRequest(
        'members',
        '/members',
        'POST',
        '{broken',
        ['Content-Type' => 'application/json'],
    );

    expect($errors)->toHaveKey('body')
        ->and($errors['body'][0])->toContain('not valid JSON');
});

it('reports a content type that the operation does not define', function (): void {
    $errors = validateRequest(
        'members',
        '/members',
        'POST',
        'name=kojirock',
        ['Content-Type' => 'application/x-www-form-urlencoded'],
    );

    expect($errors)->toHaveKey('body')
        ->and($errors['body'][0])->toContain('not defined');
});

it('accepts a body that matches the schema', function (): void {
    $body = json_encode(['name' => 'kojirock', 'status' => 'active', 'nickname' => null]);

    expect(validateRequest('members', '/members', 'POST', $body, ['Content-Type' => 'application/json']))
        ->toBe([]);
});

it('reports body fields using their own names', function (): void {
    $body = json_encode(['name' => 'kojirock', 'status' => 'retired']);

    $errors = validateRequest('members', '/members', 'POST', $body, ['Content-Type' => 'application/json']);

    expect(array_keys($errors))->toBe(['status']);
});

it('reports errors on the body itself under the body key', function (): void {
    $body = json_encode(['status' => 'active']);

    $errors = validateRequest('members', '/members', 'POST', $body, ['Content-Type' => 'application/json']);

    expect(array_keys($errors))->toBe(['body'])
        ->and($errors['body'][0])->toContain('name');
});

it('collects every violation instead of stopping at the first', function (): void {
    $body = json_encode(['name' => '', 'status' => 'retired']);

    $errors = validateRequest('members', '/members', 'POST', $body, ['Content-Type' => 'application/json']);

    expect(array_keys($errors))->toContain('name')
        ->and(array_keys($errors))->toContain('status');
});
