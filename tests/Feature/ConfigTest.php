<?php

declare(strict_types=1);

use ErickComp\LazyBladeIcons\IconRenderer;
use ErickComp\LazyBladeIcons\LazyBladeIconsServiceProvider;

it('merges the package config', function () {
    expect(config('lazy-blade-icons'))->toBe([
        'prefix' => 'icon:',
        'dynamic_keyword' => 'dynamic',
        'stack' => 'bladeicons',
        'defer' => false,
    ]);
});

it('binds the renderer as a singleton', function () {
    expect(app(IconRenderer::class))->toBe(app(IconRenderer::class));
});

it('honours a custom tag prefix', function () {
    config(['lazy-blade-icons.prefix' => 'svg:']);
    (new LazyBladeIconsServiceProvider($this->app))->boot();

    expect(bodyOf(render('<x-svg:test-icon class="w-4" />')))->toContain('class="w-4"');
});

it('accepts a prefix that already carries the x- marker', function () {
    config(['lazy-blade-icons.prefix' => 'x-svg:']);
    (new LazyBladeIconsServiceProvider($this->app))->boot();

    expect(bodyOf(render('<x-svg:test-icon />')))->toContain('<path');
});

it('leaves unrelated component tags alone', function () {
    expect(render('<x-test-icon />'))->toContain('<path');
});
