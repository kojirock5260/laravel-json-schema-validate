<?php

declare(strict_types=1);

/**
 * OperationResolver が Laravel のルートを OpenAPI の Operation へ
 * 対応づけられることを確認する。
 */

use Kojirock5260\JsonSchemaValidate\Spec\OperationResolver;

it('resolves a concrete path', function (): void {
    $resolved = (new OperationResolver(specRepository()))->resolve(testRoute('members'), 'GET');

    expect($resolved)->not->toBeNull()
        ->and($resolved->operation->operationId)->toBe('listMembers')
        ->and($resolved->describe())->toBe('GET /members');
});

it('matches templated paths even when the parameter names differ', function (): void {
    // Laravel 側は {member}、spec 側は {memberId}
    $resolved = (new OperationResolver(specRepository()))->resolve(testRoute('members/{member}'), 'GET');

    expect($resolved)->not->toBeNull()
        ->and($resolved->operation->operationId)->toBe('showMember')
        ->and($resolved->path)->toBe('/members/{memberId}');
});

it('maps spec parameter names onto laravel parameter names', function (): void {
    $resolved = (new OperationResolver(specRepository()))->resolve(testRoute('members/{member}'), 'GET');

    expect($resolved->parameterMap)->toBe(['memberId' => 'member'])
        ->and($resolved->routeParameterName('memberId'))->toBe('member');
});

it('falls back to the spec name when no mapping exists', function (): void {
    $resolved = (new OperationResolver(specRepository()))->resolve(testRoute('members'), 'GET');

    expect($resolved->parameterMap)->toBe([])
        ->and($resolved->routeParameterName('page'))->toBe('page');
});

it('strips the optional marker from laravel parameter names', function (): void {
    $resolved = (new OperationResolver(specRepository()))->resolve(testRoute('members/{member?}'), 'GET');

    expect($resolved->parameterMap)->toBe(['memberId' => 'member']);
});

it('is case insensitive about the http method', function (): void {
    $resolved = (new OperationResolver(specRepository()))->resolve(testRoute('members', 'POST'), 'post');

    expect($resolved)->not->toBeNull()
        ->and($resolved->operation->operationId)->toBe('createMember');
});

it('resolves optional laravel parameters', function (): void {
    $resolved = (new OperationResolver(specRepository()))->resolve(testRoute('members/{member?}'), 'GET');

    expect($resolved)->not->toBeNull()
        ->and($resolved->path)->toBe('/members/{memberId}');
});

it('strips the configured base path before matching', function (): void {
    $resolver = new OperationResolver(specRepository(), '/api');

    expect($resolver->resolve(testRoute('api/members'), 'GET'))->not->toBeNull()
        ->and($resolver->resolve(testRoute('members'), 'GET'))->not->toBeNull();
});

it('returns null when the path is absent from the spec', function (): void {
    expect((new OperationResolver(specRepository()))->resolve(testRoute('unknown'), 'GET'))->toBeNull();
});

it('returns null when the path exists but the method does not', function (): void {
    expect((new OperationResolver(specRepository()))->resolve(testRoute('members/{member}'), 'DELETE'))->toBeNull();
});
