<?php

declare(strict_types=1);

/**
 * SpecGenerator が観測から OpenAPI ドキュメントを組み立てられることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Spec\SpecGenerator;

/**
 * まとめられた観測を1件ぶん作る。
 *
 * @param  string  $path  パステンプレート
 * @param  string  $method  小文字の HTTP メソッド
 * @param  array<string, mixed>  $overrides  上書きする項目
 * @return array<string, array<string, mixed>> まとめられた観測
 */
function mergedEntry(string $path, string $method = 'get', array $overrides = []): array
{
    return [$method.' '.$path => array_replace([
        'path' => $path,
        'method' => $method,
        'query' => [],
        'request' => [],
        'responses' => [],
    ], $overrides)];
}

it('produces a 3.1 document', function (): void {
    $document = (new SpecGenerator)->generate(mergedEntry('/members'));

    expect($document['openapi'])->toBe('3.1.0')
        ->and($document['info'])->toHaveKeys(['title', 'version']);
});

it('uses the given title and version', function (): void {
    $document = (new SpecGenerator('Member API', '2.0.0'))->generate(mergedEntry('/members'));

    expect($document['info'])->toBe(['title' => 'Member API', 'version' => '2.0.0']);
});

it('groups methods under the same path', function (): void {
    $merged = [...mergedEntry('/members', 'get'), ...mergedEntry('/members', 'post')];

    expect(array_keys((new SpecGenerator)->generate($merged)['paths']['/members']))->toBe(['get', 'post']);
});

it('sorts paths so the output is stable', function (): void {
    $merged = [...mergedEntry('/zeta'), ...mergedEntry('/alpha')];

    expect(array_keys((new SpecGenerator)->generate($merged)['paths']))->toBe(['/alpha', '/zeta']);
});

it('derives path parameters from the template', function (): void {
    $document = (new SpecGenerator)->generate(mergedEntry('/members/{member}/posts/{post}'));

    expect($document['paths']['/members/{member}/posts/{post}']['get']['parameters'])->toBe([
        ['name' => 'member', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
        ['name' => 'post', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ]);
});

it('writes query parameters with their observed requiredness', function (): void {
    $merged = mergedEntry('/members', 'get', ['query' => [
        'type' => 'object',
        'required' => ['page'],
        'properties' => ['page' => ['type' => 'integer'], 'status' => ['type' => 'string']],
    ]]);

    expect((new SpecGenerator)->generate($merged)['paths']['/members']['get']['parameters'])->toBe([
        ['name' => 'page', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']],
        ['name' => 'status', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
    ]);
});

it('writes the request body when one was observed', function (): void {
    $merged = mergedEntry('/members', 'post', ['request' => ['type' => 'object']]);

    expect((new SpecGenerator)->generate($merged)['paths']['/members']['post']['requestBody'])->toBe([
        'required' => true,
        'content' => ['application/json' => ['schema' => ['type' => 'object']]],
    ]);
});

it('omits the request body when none was observed', function (): void {
    expect((new SpecGenerator)->generate(mergedEntry('/members'))['paths']['/members']['get'])
        ->not->toHaveKey('requestBody');
});

it('writes responses keyed by status with a description', function (): void {
    $merged = mergedEntry('/members', 'get', ['responses' => [200 => ['type' => 'object']]]);

    expect((new SpecGenerator)->generate($merged)['paths']['/members']['get']['responses'])->toBe([
        '200' => [
            'description' => 'OK',
            'content' => ['application/json' => ['schema' => ['type' => 'object']]],
        ],
    ]);
});

it('writes a response without content when no body was observed', function (): void {
    $merged = mergedEntry('/members', 'get', ['responses' => [204 => []]]);

    expect((new SpecGenerator)->generate($merged)['paths']['/members']['get']['responses']['204'])
        ->toBe(['description' => 'No Content']);
});

it('falls back to a default response when no status was observed', function (): void {
    expect((new SpecGenerator)->generate(mergedEntry('/members'))['paths']['/members']['get']['responses'])
        ->toBe(['default' => ['description' => 'Response']]);
});
