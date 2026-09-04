<?php

declare(strict_types=1);

it('does not defer by default', function () {
    $rendered = render('<x-icon:test-icon />');

    expect(referencedSpriteIds($rendered))->toBeEmpty();
    expect(definedSpriteIds($rendered))->toBeEmpty();
    expect(bodyOf($rendered))->toContain('<path');
});

it('defers a single tag when the attribute is present', function () {
    $rendered = render('<x-icon:test-icon defer />');
    $id = referencedSpriteIds(bodyOf($rendered))[0] ?? null;

    expect($id)->toStartWith('icon-');
    expect(definedSpriteIds(stackOf($rendered)))->toBe([$id]);
    expect(bodyOf($rendered))->not->toContain('<path');
});

it('defers every tag when the config says so', function () {
    config(['lazy-blade-icons.defer' => true]);

    $rendered = render('<x-icon:test-icon /><x-icon:test-other />');

    expect(referencedSpriteIds(bodyOf($rendered)))->toHaveCount(2);
    expect(definedSpriteIds(stackOf($rendered)))->toHaveCount(2);
});

it('lets a tag opt out of the configured defer', function () {
    config(['lazy-blade-icons.defer' => true]);

    $rendered = render('<x-icon:test-icon defer="false" />');

    expect(referencedSpriteIds($rendered))->toBeEmpty();
    expect(bodyOf($rendered))->toContain('<path');
});

it('namespaces generated ids when the config holds a string', function () {
    config(['lazy-blade-icons.defer' => 'admin']);

    $rendered = render('<x-icon:test-icon /><x-icon:test-other />');
    $ids = definedSpriteIds(stackOf($rendered));

    expect($ids)->toHaveCount(2);
    expect($ids[0])->toStartWith('icon-admin-');
    expect($ids[1])->toStartWith('icon-admin-');
    expect($ids[0])->not->toBe($ids[1]);
});

it('keeps the namespace when a tag only reasserts defer', function () {
    config(['lazy-blade-icons.defer' => 'admin']);

    $withAttribute = referencedSpriteIds(render('<x-icon:test-icon defer />'))[0];
    $withoutAttribute = referencedSpriteIds(render('<x-icon:test-icon />'))[0];

    expect($withAttribute)->toStartWith('icon-admin-')->toBe($withoutAttribute);
});

it('pins the id when the tag gives defer a value', function () {
    config(['lazy-blade-icons.defer' => 'admin']);

    $rendered = render('<x-icon:test-icon defer="my-id" />');

    expect(referencedSpriteIds(bodyOf($rendered)))->toBe(['icon-my-id']);
    expect(definedSpriteIds(stackOf($rendered)))->toBe(['icon-my-id']);
});

it('pushes the sprite only once for repeated icons', function () {
    $rendered = render('<x-icon:test-icon defer /><x-icon:test-icon defer /><x-icon:test-icon defer />');

    expect(referencedSpriteIds(bodyOf($rendered)))->toHaveCount(3);
    expect(definedSpriteIds(stackOf($rendered)))->toHaveCount(1);
});

it('shares one sprite across the three syntaxes', function () {
    $rendered = render(
        '<x-icon:test-icon defer />'
        . '<x-icon:dynamic :is="$icon" defer />'
        . '<x-icon:dynamic defer>{{ $icon }}</x-icon:dynamic>',
        ['icon' => 'test-icon'],
    );

    $referenced = referencedSpriteIds(bodyOf($rendered));

    expect($referenced)->toHaveCount(3);
    expect(array_unique($referenced))->toHaveCount(1);
    expect(definedSpriteIds(stackOf($rendered)))->toBe([$referenced[0]]);
});

it('keeps distinct icons in distinct sprites', function () {
    $rendered = render('<x-icon:test-icon defer /><x-icon:test-other defer />');
    $ids = definedSpriteIds(stackOf($rendered));

    expect($ids)->toHaveCount(2);
    expect($ids[0])->not->toBe($ids[1]);
});

it('honours a custom stack', function () {
    config(['lazy-blade-icons.stack' => 'my-icons']);

    $rendered = render('<x-icon:test-icon defer />', stack: 'my-icons');

    expect(definedSpriteIds(stackOf($rendered)))->toHaveCount(1);
});

it('keeps the title on the visible element when deferring', function () {
    $rendered = render('<x-icon:test-icon defer title="A square" />');

    expect(bodyOf($rendered))->toContain('<title>A square</title>');
    expect(stackOf($rendered))->not->toContain('<title>');
});
