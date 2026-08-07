<?php

declare(strict_types=1);

/**
 * ErrorKey が JSON Pointer を Laravel のドット記法へ変換できることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Validation\ErrorKey;

it('converts a pointer into dot notation', function (): void {
    expect(ErrorKey::fromPointer('/data/0/name'))->toBe('data.0.name')
        ->and(ErrorKey::fromPointer('/name'))->toBe('name');
});

it('returns the prefix when the pointer refers to the root', function (): void {
    expect(ErrorKey::fromPointer('', 'page'))->toBe('page')
        ->and(ErrorKey::fromPointer('/', 'page'))->toBe('page');
});

it('returns an empty string when there is neither pointer nor prefix', function (): void {
    expect(ErrorKey::fromPointer(''))->toBe('');
});

it('places the prefix in front of the pointer', function (): void {
    expect(ErrorKey::fromPointer('/status', 'filter'))->toBe('filter.status')
        ->and(ErrorKey::fromPointer('/a/b', 'root'))->toBe('root.a.b');
});

it('unescapes json pointer tokens', function (): void {
    expect(ErrorKey::fromPointer('/a~1b'))->toBe('a/b')
        ->and(ErrorKey::fromPointer('/a~0b'))->toBe('a~b')
        ->and(ErrorKey::fromPointer('/a~01b'))->toBe('a~1b');
});

it('rekeys an error list', function (): void {
    $errors = ErrorKey::rekey(['/data/0/name' => ['too short'], '/page' => ['not an integer']]);

    expect($errors)->toBe(['data.0.name' => ['too short'], 'page' => ['not an integer']]);
});

it('uses the root key when the converted key is empty', function (): void {
    expect(ErrorKey::rekey(['/' => ['missing']], '', 'body'))->toBe(['body' => ['missing']]);
});

it('keeps the empty key when no root key is given', function (): void {
    expect(ErrorKey::rekey(['/' => ['missing']]))->toBe(['' => ['missing']]);
});

it('joins messages when two pointers map to the same key', function (): void {
    $errors = ErrorKey::rekey(['' => ['first'], '/' => ['second']], '', 'body');

    expect($errors)->toBe(['body' => ['first', 'second']]);
});

it('merges two error lists', function (): void {
    $merged = ErrorKey::merge(['page' => ['a']], ['status' => ['b']]);

    expect($merged)->toBe(['page' => ['a'], 'status' => ['b']]);
});

it('concatenates messages that share a key when merging', function (): void {
    $merged = ErrorKey::merge(['page' => ['a']], ['page' => ['b']]);

    expect($merged)->toBe(['page' => ['a', 'b']]);
});

it('handles empty lists when merging', function (): void {
    expect(ErrorKey::merge([], ['page' => ['a']]))->toBe(['page' => ['a']])
        ->and(ErrorKey::merge(['page' => ['a']], []))->toBe(['page' => ['a']])
        ->and(ErrorKey::merge([], []))->toBe([]);
});
