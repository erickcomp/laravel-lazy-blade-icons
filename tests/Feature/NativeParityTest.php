<?php

declare(strict_types=1);

use BladeUI\Icons\Factory as IconFactory;

it('produces the same markup as the native component when not deferring', function () {
    $ours = trim(bodyOf(render('<x-icon:test-icon class="w-4" id="camera" />')));
    $native = trim(bodyOf(render('<x-test-icon class="w-4" id="camera" />')));

    expect($ours)->toBe($native);
});

it('shares one sprite with the native component when deferring', function () {
    $rendered = render('<x-icon:test-icon defer /><x-test-icon defer />');

    $referenced = referencedSpriteIds(bodyOf($rendered));

    expect($referenced)->toHaveCount(2);
    expect(array_unique($referenced))->toHaveCount(1);
    expect(definedSpriteIds(stackOf($rendered)))->toBe([$referenced[0]]);
});

it('matches the native custom defer id', function () {
    $ours = referencedSpriteIds(render('<x-icon:test-icon defer="pinned" />'));
    $native = referencedSpriteIds(render('<x-test-icon defer="pinned" />'));

    expect($ours)->toBe(['icon-pinned'])->toBe($native);
});

/**
 * Documents why this package reimplements deferring instead of passing the
 * attribute down to blade-icons: outside the Blade component pipeline, the
 * markup it returns still carries uncompiled Blade directives. If this ever
 * fails, upstream changed strategy and IconRenderer should be revisited.
 */
it('cannot reuse the upstream defer markup outside a component', function () {
    $html = app(IconFactory::class)->svg('test-icon', '', ['defer' => true])->toHtml();

    expect($html)
        ->toContain('@once')
        ->toContain('@push')
        ->toContain('@endpush');
});
