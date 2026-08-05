<?php

declare(strict_types=1);

/**
 * SchemaValidator が JSON Schema 2020-12 として動作することを確認する。
 *
 * ここで扱うキーワードは OpenAPI 3.0 の Schema Object では表現できず、
 * 3.1（= JSON Schema 2020-12）で初めて使えるものを選んでいる。
 */

use Kojirock5260\JsonSchemaValidate\Validation\JsonValue;
use Kojirock5260\JsonSchemaValidate\Validation\SchemaValidator;

beforeEach(function (): void {
    $this->validator = new SchemaValidator;
});

it('supports const (2020-12, absent from OpenAPI 3.0)', function (): void {
    $schema = schema('{"const": "active"}');

    expect($this->validator->isValid('active', $schema))->toBeTrue()
        ->and($this->validator->isValid('inactive', $schema))->toBeFalse();
});

it('supports prefixItems for tuple validation (2020-12)', function (): void {
    $schema = schema('{"type": "array", "prefixItems": [{"type": "string"}, {"type": "integer"}]}');

    expect($this->validator->isValid(JsonValue::normalize(['a', 1]), $schema))->toBeTrue()
        ->and($this->validator->isValid(JsonValue::normalize(['a', 'b']), $schema))->toBeFalse();
});

it('treats exclusiveMinimum as a number, not a boolean (2020-12)', function (): void {
    $schema = schema('{"type": "integer", "exclusiveMinimum": 10}');

    expect($this->validator->isValid(11, $schema))->toBeTrue()
        ->and($this->validator->isValid(10, $schema))->toBeFalse();
});

it('expresses nullability as a type union instead of nullable:true (3.1 style)', function (): void {
    $schema = schema('{"type": ["string", "null"]}');

    expect($this->validator->isValid(null, $schema))->toBeTrue()
        ->and($this->validator->isValid('hello', $schema))->toBeTrue()
        ->and($this->validator->isValid(123, $schema))->toBeFalse();
});

it('supports dependentRequired (2020-12)', function (): void {
    $schema = schema('{"type": "object", "dependentRequired": {"card": ["expiry"]}}');

    $withExpiry = JsonValue::normalizeObject(['card' => '4111', 'expiry' => '12/30']);
    $withoutExpiry = JsonValue::normalizeObject(['card' => '4111']);

    expect($this->validator->isValid($withExpiry, $schema))->toBeTrue()
        ->and($this->validator->isValid($withoutExpiry, $schema))->toBeFalse();
});

it('keys errors by data pointer so they map onto laravel validation messages', function (): void {
    $schema = schema('{
        "type": "object",
        "required": ["page"],
        "properties": {
            "page": {"type": "integer", "minimum": 1},
            "mail": {"type": "string", "format": "email"}
        }
    }');

    $errors = $this->validator->errors(
        JsonValue::normalizeObject(['page' => 0, 'mail' => 'not-an-email']),
        $schema,
    );

    expect($errors)->toBeArray()
        ->and($errors)->not->toBeEmpty()
        ->and(array_keys($errors))->toContain('/page');

    foreach ($errors as $messages) {
        expect($messages)->toBeArray()->each->toBeString();
    }
});

it('returns an empty array when the data is valid', function (): void {
    $schema = schema('{"type": "object", "properties": {"page": {"type": "integer"}}}');

    expect($this->validator->errors(JsonValue::normalizeObject(['page' => 3]), $schema))->toBe([]);
});
