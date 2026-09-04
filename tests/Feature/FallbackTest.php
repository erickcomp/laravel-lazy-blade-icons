<?php

declare(strict_types=1);

use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory as IconFactory;
use Illuminate\View\ViewException;

it('renders the set fallback for an unknown icon', function () {
    $this->iconSetOptions = ['fallback' => 'fallback'];

    expect(trim(bodyOf(render('<x-icon:test-nope />'))))
        ->toBe(app(IconFactory::class)->svg('test-fallback')->toHtml());
});

it('renders the global fallback for an unknown icon', function () {
    config(['blade-icons.fallback' => 'test-fallback']);

    expect(trim(bodyOf(render('<x-icon:test-nope />'))))
        ->toBe(app(IconFactory::class)->svg('test-fallback')->toHtml());
});

it('renders the fallback for an unknown dynamic name', function () {
    config(['blade-icons.fallback' => 'test-fallback']);

    expect(trim(bodyOf(render('<x-icon:dynamic :is="$icon" />', ['icon' => 'test-nope']))))
        ->toBe(app(IconFactory::class)->svg('test-fallback')->toHtml());
});

it('fails for an unknown icon when no fallback is configured', function () {
    try {
        render('<x-icon:test-nope />');
    } catch (ViewException $e) {
        expect($e->getPrevious())->toBeInstanceOf(SvgNotFound::class);

        return;
    }

    $this->fail('Expected a ViewException.');
});

/**
 * Documents an advantage over the native per-icon component syntax: blade-icons
 * cannot fall back there, because Blade fails to resolve the component long
 * before the icon factory is consulted.
 */
it('falls back where the native component syntax cannot', function () {
    config(['blade-icons.fallback' => 'test-fallback']);

    expect(fn () => render('<x-test-nope />'))->toThrow(InvalidArgumentException::class);

    expect(bodyOf(render('<x-icon:test-nope />')))->toContain('<path');
});
