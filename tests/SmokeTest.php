<?php

declare(strict_types=1);

it('boots the laravel application', function (): void {
    expect(app())->not->toBeNull()
        ->and((int) app()->version())->toBeGreaterThanOrEqual(12);
});
