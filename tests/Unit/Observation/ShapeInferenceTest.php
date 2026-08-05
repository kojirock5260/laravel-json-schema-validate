<?php

declare(strict_types=1);

/**
 * ShapeInference が観測した値から型と構造を取り出せることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Observation\ShapeInference;

/**
 * JSON 文字列を復号して推論する。
 *
 * @param  string  $json  観測した値を表す JSON 文字列
 * @return array<string, mixed> 推論されたスキーマ
 */
function inferFrom(string $json): array
{
    return ShapeInference::fromValue(json_decode($json, false, 512, JSON_THROW_ON_ERROR));
}

it('infers scalar types', function (): void {
    expect(inferFrom('null'))->toBe(['type' => 'null'])
        ->and(inferFrom('true'))->toBe(['type' => 'boolean'])
        ->and(inferFrom('7'))->toBe(['type' => 'integer'])
        ->and(inferFrom('1.5'))->toBe(['type' => 'number'])
        ->and(inferFrom('"kojirock"'))->toBe(['type' => 'string']);
});

it('records every observed key as required', function (): void {
    expect(inferFrom('{"name": "kojirock", "age": 20}'))->toBe([
        'type' => 'object',
        'required' => ['name', 'age'],
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
        ],
    ]);
});

it('infers nested objects', function (): void {
    expect(inferFrom('{"user": {"name": "kojirock"}}'))->toBe([
        'type' => 'object',
        'required' => ['user'],
        'properties' => [
            'user' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => ['name' => ['type' => 'string']],
            ],
        ],
    ]);
});

it('merges array elements into a single items schema', function (): void {
    expect(inferFrom('[1, 2, 3]'))->toBe([
        'type' => 'array',
        'items' => ['type' => 'integer'],
    ]);
});

it('unions the types of mixed array elements', function (): void {
    expect(inferFrom('[1, "a", null]'))->toBe([
        'type' => 'array',
        'items' => ['type' => ['integer', 'null', 'string']],
    ]);
});

it('drops keys that are missing from some elements out of required', function (): void {
    expect(inferFrom('[{"a": 1, "b": 2}, {"a": 3}]'))->toBe([
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'required' => ['a'],
            'properties' => [
                'a' => ['type' => 'integer'],
                'b' => ['type' => 'integer'],
            ],
        ],
    ]);
});

it('omits items for an empty array', function (): void {
    expect(inferFrom('[]'))->toBe(['type' => 'array']);
});

it('omits properties for an empty object', function (): void {
    expect(inferFrom('{}'))->toBe(['type' => 'object']);
});

it('records structure without keeping any value', function (): void {
    $inferred = inferFrom('{"mail": "kojirock5260@example.com", "token": "s3cret"}');

    expect(json_encode($inferred))->not->toContain('example.com')
        ->and(json_encode($inferred))->not->toContain('s3cret');
});
