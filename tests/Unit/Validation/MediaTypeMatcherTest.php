<?php

declare(strict_types=1);

/**
 * MediaTypeMatcher が Content-Type と spec の content 定義を
 * 突き合わせられることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Validation\MediaTypeMatcher;

it('matches an exact media type', function (): void {
    expect(MediaTypeMatcher::match(['application/json'], 'application/json'))->toBe('application/json');
});

it('ignores parameters attached to the header', function (): void {
    expect(MediaTypeMatcher::match(['application/json'], 'application/json; charset=utf-8'))
        ->toBe('application/json');
});

it('ignores letter case', function (): void {
    expect(MediaTypeMatcher::match(['Application/JSON'], 'application/json'))->toBe('Application/JSON')
        ->and(MediaTypeMatcher::match(['application/json'], 'APPLICATION/JSON'))->toBe('application/json');
});

it('falls back to a subtype wildcard', function (): void {
    expect(MediaTypeMatcher::match(['application/*'], 'application/json'))->toBe('application/*');
});

it('falls back to a full wildcard', function (): void {
    expect(MediaTypeMatcher::match(['*/*'], 'text/plain'))->toBe('*/*');
});

it('prefers an exact match over a wildcard', function (): void {
    expect(MediaTypeMatcher::match(['*/*', 'application/*', 'application/json'], 'application/json'))
        ->toBe('application/json');
});

it('does not use a subtype wildcard from another primary type', function (): void {
    expect(MediaTypeMatcher::match(['text/*'], 'application/json'))->toBeNull();
});

it('returns null when nothing matches', function (): void {
    expect(MediaTypeMatcher::match(['application/json'], 'text/plain'))->toBeNull()
        ->and(MediaTypeMatcher::match([], 'application/json'))->toBeNull();
});

it('returns null for a missing or empty header', function (): void {
    expect(MediaTypeMatcher::match(['application/json'], null))->toBeNull()
        ->and(MediaTypeMatcher::match(['application/json'], ''))->toBeNull()
        ->and(MediaTypeMatcher::match(['application/json'], '  '))->toBeNull();
});

it('recognises json media types', function (): void {
    expect(MediaTypeMatcher::isJson('application/json'))->toBeTrue()
        ->and(MediaTypeMatcher::isJson('application/json; charset=utf-8'))->toBeTrue()
        ->and(MediaTypeMatcher::isJson('application/problem+json'))->toBeTrue()
        ->and(MediaTypeMatcher::isJson('application/vnd.api+json'))->toBeTrue();
});

it('does not treat other media types as json', function (): void {
    expect(MediaTypeMatcher::isJson('text/plain'))->toBeFalse()
        ->and(MediaTypeMatcher::isJson('application/xml'))->toBeFalse()
        ->and(MediaTypeMatcher::isJson(null))->toBeFalse()
        ->and(MediaTypeMatcher::isJson(''))->toBeFalse();
});

it('does not treat a wildcard as json', function (): void {
    // spec が */* だけを定義している場合、ボディの中身は検証できない
    expect(MediaTypeMatcher::isJson('*/*'))->toBeFalse();
});
