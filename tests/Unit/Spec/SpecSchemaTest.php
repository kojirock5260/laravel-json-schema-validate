<?php

declare(strict_types=1);

/**
 * SpecSchema が spec 上の Schema を検証器へ渡せる形へ変換できることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Spec\SpecSchema;
use Kojirock5260\JsonSchemaValidate\Validation\SchemaValidator;

/**
 * fixture の components から Schema を取り出して変換する。
 *
 * @param  string  $name  components.schemas のキー
 * @return object 変換された JSON Schema
 */
function jsonSchemaFor(string $name): object
{
    return SpecSchema::toJsonSchema(specRepository()->load()->components->schemas[$name]);
}

it('converts a schema into a plain object', function (): void {
    expect(jsonSchemaFor('Member'))->toBeInstanceOf(stdClass::class);
});

it('keeps 2020-12 keywords that the parser has no attribute for', function (): void {
    $schema = jsonSchemaFor('Modern');

    expect($schema->prefixItems ?? null)->toBeNull()
        ->and($schema->properties->tuple->prefixItems)->toHaveCount(2)
        ->and($schema->dependentRequired->card)->toBe(['expiry'])
        ->and($schema->unevaluatedProperties)->toBeFalse();
});

it('keeps exclusiveMinimum as a number rather than a boolean', function (): void {
    expect(jsonSchemaFor('Modern')->properties->limit->exclusiveMinimum)->toBe(10);
});

it('keeps a type union instead of rewriting it as nullable', function (): void {
    expect(jsonSchemaFor('Member')->properties->nickname->type)->toBe(['string', 'null']);
});

it('resolves references before conversion', function (): void {
    $schema = SpecSchema::toJsonSchema(
        specRepository()->load()->paths->getPath('/members')->post->requestBody->content['application/json']->schema,
    );

    expect($schema->properties->status->const)->toBe('active');
});

it('produces a schema the validator can use', function (): void {
    $validator = new SchemaValidator;
    $schema = jsonSchemaFor('Modern');

    $valid = json_decode('{"tuple": ["a", 1], "limit": 11}');
    $invalid = json_decode('{"tuple": ["a", "b"], "limit": 10}');

    expect($validator->isValid($valid, $schema))->toBeTrue()
        ->and($validator->isValid($invalid, $schema))->toBeFalse();
});

it('returns null when the value is not a schema', function (): void {
    expect(SpecSchema::toJsonSchema(null))->toBeNull()
        ->and(SpecSchema::toJsonSchema('string'))->toBeNull()
        ->and(SpecSchema::toJsonSchema(new stdClass))->toBeNull();
});
