<?php

declare(strict_types=1);

/**
 * ParameterCoercer が文字列をスキーマの型へ変換できることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Validation\ParameterCoercer;

it('converts a numeric string into an integer', function (): void {
    expect(ParameterCoercer::coerce('3', schema('{"type": "integer"}')))->toBe(3)
        ->and(ParameterCoercer::coerce('-42', schema('{"type": "integer"}')))->toBe(-42);
});

it('converts a numeric string into a number', function (): void {
    expect(ParameterCoercer::coerce('1.5', schema('{"type": "number"}')))->toBe(1.5);
});

it('converts the literal true and false into booleans', function (): void {
    expect(ParameterCoercer::coerce('true', schema('{"type": "boolean"}')))->toBeTrue()
        ->and(ParameterCoercer::coerce('false', schema('{"type": "boolean"}')))->toBeFalse();
});

it('leaves a value untouched when it does not match the declared type', function (): void {
    // 変換せず文字列のまま返すことで、検証側が不正と判定できる
    expect(ParameterCoercer::coerce('abc', schema('{"type": "integer"}')))->toBe('abc')
        ->and(ParameterCoercer::coerce('3.5', schema('{"type": "integer"}')))->toBe('3.5')
        ->and(ParameterCoercer::coerce('yes', schema('{"type": "boolean"}')))->toBe('yes');
});

it('keeps strings as strings', function (): void {
    expect(ParameterCoercer::coerce('3', schema('{"type": "string"}')))->toBe('3');
});

it('honours the declared order when the type is a union', function (): void {
    expect(ParameterCoercer::coerce('3', schema('{"type": ["integer", "string"]}')))->toBe(3)
        ->and(ParameterCoercer::coerce('3', schema('{"type": ["string", "integer"]}')))->toBe('3');
});

it('skips types it cannot produce and tries the next one', function (): void {
    expect(ParameterCoercer::coerce('7', schema('{"type": ["null", "integer"]}')))->toBe(7);
});

it('coerces every item of an array against the items schema', function (): void {
    $coerced = ParameterCoercer::coerce(
        ['1', '2', '3'],
        schema('{"type": "array", "items": {"type": "integer"}}'),
    );

    expect($coerced)->toBe([1, 2, 3]);
});

it('leaves an array untouched when the schema is not an array type', function (): void {
    expect(ParameterCoercer::coerce(['1'], schema('{"type": "string"}')))->toBe(['1']);
});

it('leaves values untouched when the schema declares no type', function (): void {
    expect(ParameterCoercer::coerce('3', schema('{"minLength": 1}')))->toBe('3');
});

it('passes non string values through untouched', function (): void {
    expect(ParameterCoercer::coerce(5, schema('{"type": "integer"}')))->toBe(5)
        ->and(ParameterCoercer::coerce(null, schema('{"type": "integer"}')))->toBeNull();
});

it('passes values through when the schema is a boolean', function (): void {
    expect(ParameterCoercer::coerce('3', true))->toBe('3');
});
