<?php

declare(strict_types=1);

/**
 * ShapeMerger が複数回の観測をまとめられることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Observation\ShapeInference;
use Kojirock5260\JsonSchemaValidate\Observation\ShapeMerger;

/**
 * JSON 文字列の一覧を観測してまとめる。
 *
 * @param  string  ...$samples  観測した値を表す JSON 文字列
 * @return array<string, mixed> まとめられたスキーマ
 */
function observe(string ...$samples): array
{
    return ShapeMerger::mergeAll(array_map(
        static fn (string $json): array => ShapeInference::fromValue(
            json_decode($json, false, 512, JSON_THROW_ON_ERROR),
        ),
        $samples,
    ));
}

it('returns the other side when one is empty', function (): void {
    expect(ShapeMerger::merge([], ['type' => 'string']))->toBe(['type' => 'string'])
        ->and(ShapeMerger::merge(['type' => 'string'], []))->toBe(['type' => 'string'])
        ->and(ShapeMerger::mergeAll([]))->toBe([]);
});

it('keeps a single type as a string', function (): void {
    expect(observe('"a"', '"b"'))->toBe(['type' => 'string']);
});

it('unions differing types', function (): void {
    expect(observe('"a"', 'null'))->toBe(['type' => ['null', 'string']]);
});

it('sorts unioned types so the output is stable', function (): void {
    expect(observe('"a"', 'null', '1'))->toBe(observe('1', 'null', '"a"'));
});

it('intersects required across observations', function (): void {
    expect(observe('{"a": 1, "b": 2}', '{"a": 3}'))->toBe([
        'type' => 'object',
        'required' => ['a'],
        'properties' => [
            'a' => ['type' => 'integer'],
            'b' => ['type' => 'integer'],
        ],
    ]);
});

it('keeps properties that appear in only one observation', function (): void {
    $merged = observe('{"a": 1}', '{"b": "x"}');

    expect($merged['properties'])->toHaveKeys(['a', 'b'])
        ->and($merged)->not->toHaveKey('required');
});

it('unions the type of a property observed with different types', function (): void {
    expect(observe('{"a": 1}', '{"a": null}')['properties']['a'])
        ->toBe(['type' => ['integer', 'null']]);
});

it('merges nested objects recursively', function (): void {
    $merged = observe('{"user": {"name": "x", "age": 1}}', '{"user": {"name": "y"}}');

    expect($merged['properties']['user']['required'])->toBe(['name'])
        ->and($merged['properties']['user']['properties'])->toHaveKeys(['name', 'age']);
});

it('merges items across observations', function (): void {
    expect(observe('[1]', '["a"]')['items'])->toBe(['type' => ['integer', 'string']]);
});

it('keeps items when only one observation has them', function (): void {
    expect(observe('[]', '[1]')['items'])->toBe(['type' => 'integer']);
});

it('drops required when an object is sometimes null', function (): void {
    $merged = observe('{"a": 1}', 'null');

    expect($merged['type'])->toBe(['null', 'object'])
        ->and($merged)->not->toHaveKey('required');
});

it('produces the same result regardless of observation order', function (): void {
    $samples = ['{"a": 1, "b": 2}', '{"a": null}', '{"a": 3, "c": true}'];

    expect(observe(...$samples))->toEqual(observe(...array_reverse($samples)));
});
