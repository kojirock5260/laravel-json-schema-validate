<?php

declare(strict_types=1);

/**
 * SpecRepository の読み込み挙動を確認する。
 */

use Kojirock5260\JsonSchemaValidate\Exception\SpecException;
use Kojirock5260\JsonSchemaValidate\Spec\SpecRepository;

it('reads an openapi 3.1 document', function (): void {
    $spec = specRepository();

    expect($spec->version())->toBe('3.1.0')
        ->and($spec->isVersion31())->toBeTrue();
});

it('parses the document only once', function (): void {
    $spec = specRepository();

    expect($spec->load())->toBe($spec->load());
});

it('resolves references inside the document', function (): void {
    $member = specRepository()->load()->components->schemas['Member'];

    expect($member->properties['status']->const)->toBe('active');
});

it('throws when the document is missing', function (): void {
    (new SpecRepository('/nowhere/openapi.yaml'))->load();
})->throws(SpecException::class);

it('throws when the extension is not supported', function (): void {
    (new SpecRepository(fixturePath('openapi.txt')))->load();
})->throws(SpecException::class);
