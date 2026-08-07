<?php

declare(strict_types=1);

/**
 * ParameterInference がクエリパラメータの型を推し量れることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Observation\ParameterInference;
use Kojirock5260\JsonSchemaValidate\Observation\ShapeMerger;

it('returns null when there are no parameters', function (): void {
    expect(ParameterInference::fromQuery([]))->toBeNull();
});

it('infers scalar types from the shape of the value', function (): void {
    $inferred = ParameterInference::fromQuery([
        'page' => '3',
        'ratio' => '1.5',
        'full' => 'true',
        'q' => 'abc',
        'negative' => '-7',
    ]);

    expect($inferred['properties'])->toBe([
        'page' => ['type' => 'integer'],
        'ratio' => ['type' => 'number'],
        'full' => ['type' => 'boolean'],
        'q' => ['type' => 'string'],
        'negative' => ['type' => 'integer'],
    ]);
});

it('records every observed parameter as required', function (): void {
    expect(ParameterInference::fromQuery(['page' => '1', 'q' => 'x'])['required'])->toBe(['page', 'q']);
});

it('infers array parameters', function (): void {
    expect(ParameterInference::fromQuery(['ids' => ['1', '2']])['properties']['ids'])
        ->toBe(['type' => 'array', 'items' => ['type' => 'integer']]);
});

it('drops a parameter from required when it is sometimes absent', function (): void {
    $merged = ShapeMerger::merge(
        ParameterInference::fromQuery(['page' => '1', 'status' => 'active']),
        ParameterInference::fromQuery(['page' => '2']),
    );

    expect($merged['required'])->toBe(['page'])
        ->and($merged['properties'])->toHaveKeys(['page', 'status']);
});

it('unions the type when a parameter is seen with different shapes', function (): void {
    $merged = ShapeMerger::merge(
        ParameterInference::fromQuery(['page' => '1']),
        ParameterInference::fromQuery(['page' => 'first']),
    );

    expect($merged['properties']['page']['type'])->toBe(['integer', 'string']);
});
