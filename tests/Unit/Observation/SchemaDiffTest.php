<?php

declare(strict_types=1);

/**
 * SchemaDiff が観測と spec の食い違いを列挙できることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Observation\SchemaDiff;
use Kojirock5260\JsonSchemaValidate\Observation\ShapeInference;

/**
 * JSON 文字列を観測し、spec のスキーマと突き合わせる。
 *
 * @param  string  $sample  観測した値を表す JSON 文字列
 * @param  string|null  $spec  spec 側のスキーマを表す JSON 文字列。定義が無ければ null
 * @return array<string, list<string>> 食い違いの一覧
 */
function diffAgainst(string $sample, ?string $spec): array
{
    return SchemaDiff::compare(
        ShapeInference::fromValue(json_decode($sample, false, 512, JSON_THROW_ON_ERROR)),
        $spec === null ? null : json_decode($spec, true, 512, JSON_THROW_ON_ERROR),
    );
}

it('reports nothing when the observation matches the spec', function (): void {
    expect(diffAgainst(
        '{"name": "kojirock"}',
        '{"type": "object", "required": ["name"], "properties": {"name": {"type": "string"}}}',
    ))->toBe([]);
});

it('reports that the spec defines no schema at all', function (): void {
    $differences = diffAgainst('{"name": "kojirock"}', null);

    expect($differences)->toHaveKey('body')
        ->and($differences['body'][0])->toContain('defines no schema');
});

it('reports a property that the spec does not describe', function (): void {
    $differences = diffAgainst(
        '{"name": "kojirock", "nickname": "koji"}',
        '{"type": "object", "properties": {"name": {"type": "string"}}}',
    );

    expect($differences)->toHaveKey('body.nickname')
        ->and($differences['body.nickname'][0])->toContain('not described');
});

it('reports a type the spec does not allow', function (): void {
    $differences = diffAgainst(
        '{"name": null}',
        '{"type": "object", "properties": {"name": {"type": "string"}}}',
    );

    expect($differences)->toHaveKey('body.name')
        ->and($differences['body.name'][0])->toContain('Observed type null');
});

it('accepts a type that the spec lists in a union', function (): void {
    expect(diffAgainst(
        '{"name": null}',
        '{"type": "object", "properties": {"name": {"type": ["string", "null"]}}}',
    ))->toBe([]);
});

it('reports a required property that was not always present', function (): void {
    $differences = diffAgainst(
        '{"name": "kojirock"}',
        '{"type": "object", "required": ["name", "status"], "properties": {"name": {"type": "string"}}}',
    );

    expect($differences)->toHaveKey('body')
        ->and($differences['body'][0])->toContain('status');
});

it('does not report spec properties that were simply not observed', function (): void {
    expect(diffAgainst(
        '{"name": "kojirock"}',
        '{"type": "object", "properties": {"name": {"type": "string"}, "age": {"type": "integer"}}}',
    ))->toBe([]);
});

it('walks into array items', function (): void {
    $differences = diffAgainst(
        '{"data": [{"name": "kojirock", "extra": true}]}',
        '{"type": "object", "properties": {"data": {"type": "array", "items": {"type": "object", "properties": {"name": {"type": "string"}}}}}}',
    );

    expect($differences)->toHaveKey('body.data[].extra');
});

it('walks into nested objects', function (): void {
    $differences = diffAgainst(
        '{"user": {"name": 1}}',
        '{"type": "object", "properties": {"user": {"type": "object", "properties": {"name": {"type": "string"}}}}}',
    );

    expect($differences)->toHaveKey('body.user.name');
});

it('ignores a spec schema that declares no type', function (): void {
    expect(diffAgainst('{"name": "kojirock"}', '{"properties": {"name": {}}}'))->toBe([]);
});

it('reports nothing for an empty observation', function (): void {
    expect(SchemaDiff::compare([], null))->toBe([]);
});

it('converts a spec schema object into a comparable array', function (): void {
    $converted = SchemaDiff::fromSpec(json_decode('{"type": "object"}'));

    expect($converted)->toBe(['type' => 'object'])
        ->and(SchemaDiff::fromSpec(null))->toBeNull();
});
