<?php

declare(strict_types=1);

/**
 * JsonValue が PHP の値を JSON Schema 向けに正規化できることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Validation\JsonValue;

it('converts nested associative arrays into objects', function (): void {
    $normalized = JsonValue::normalize(['user' => ['name' => 'kojirock']]);

    expect($normalized)->toBeInstanceOf(stdClass::class)
        ->and($normalized->user)->toBeInstanceOf(stdClass::class)
        ->and($normalized->user->name)->toBe('kojirock');
});

it('keeps json lists as arrays', function (): void {
    expect(JsonValue::normalize(['a', 'b']))->toBeArray();
});

it('passes scalars and null through untouched', function (): void {
    expect(JsonValue::normalize(1))->toBe(1)
        ->and(JsonValue::normalize('x'))->toBe('x')
        ->and(JsonValue::normalize(true))->toBeTrue()
        ->and(JsonValue::normalize(null))->toBeNull();
});

it('normalizes an empty array into an object, not a json list', function (): void {
    expect(JsonValue::normalizeObject([]))->toBeInstanceOf(stdClass::class);
});
