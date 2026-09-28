<?php

declare(strict_types=1);

use BladeUI\Icons\Factory as IconFactory;
use Illuminate\Support\HtmlString;

/** Every <svg> element in the markup, in order of appearance. */
function svgElements(string $html): array
{
    preg_match_all('/<svg.*?<\/svg>/s', $html, $matches);

    return $matches[0];
}

it('renders a repeated icon identically', function () {
    $icons = svgElements(render('<x-icon:test-icon class="w-4" /><x-icon:test-icon class="w-4" />'));

    expect($icons)->toBe(array_fill(0, 2, app(IconFactory::class)->svg('test-icon', 'w-4')->toHtml()));
});

it('renders a repeated deferred icon identically and pushes its sprite once', function () {
    $rendered = render('<x-icon:test-icon class="w-4" defer /><x-icon:test-icon class="w-4" defer />');
    $icons = svgElements(bodyOf($rendered));

    expect($icons)->toHaveCount(2);
    expect($icons[1])->toBe($icons[0]);
    expect(definedSpriteIds(stackOf($rendered)))->toBe(referencedSpriteIds($icons[0]));
});

it('pushes the sprite again on the next top-level render', function () {
    $first = render('<x-icon:test-icon defer />');
    $second = render('<x-icon:test-icon defer />');
    $third = render('<x-icon:dynamic is="test-icon" defer />');

    foreach ([$first, $second, $third] as $rendered) {
        expect(definedSpriteIds(stackOf($rendered)))->toBe(referencedSpriteIds(bodyOf($rendered)));
    }

    expect($second)->toBe($first);
});

it('keeps apart icons whose attributes come in a different order', function () {
    $factory = app(IconFactory::class);
    $idFirst = $factory->svg('test-icon', '', ['id' => 'a', 'data-x' => 'b'])->toHtml();
    $dataFirst = $factory->svg('test-icon', '', ['data-x' => 'b', 'id' => 'a'])->toHtml();

    $icons = svgElements(render('<x-icon:test-icon id="a" data-x="b" /><x-icon:test-icon data-x="b" id="a" />'));

    expect($idFirst)->not->toBe($dataFirst);
    expect($icons)->toBe([$idFirst, $dataFirst]);
});

/**
 * Blade casts a bound Stringable to a string before the tag sees it, so these
 * are memoized by value. Objects that do reach the renderer are covered by the
 * unit tests.
 */
it('memoizes bound HtmlString attributes by their value', function () {
    $icons = svgElements(render(
        '<x-icon:test-icon :data-x="$a" /><x-icon:test-icon :data-x="$b" /><x-icon:test-icon :data-x="$a" />',
        ['a' => new HtmlString('first'), 'b' => new HtmlString('second')],
    ));

    expect($icons[0])->toContain('data-x="first"')->toBe($icons[2]);
    expect($icons[1])->toContain('data-x="second"');
});

it('does not let one tag\'s defer leak into the next', function () {
    $rendered = render('<x-icon:test-icon /><x-icon:test-icon defer /><x-icon:test-icon />');
    $icons = svgElements(bodyOf($rendered));

    expect($icons[0])->toBe($icons[2])->toContain('<path');
    expect(referencedSpriteIds($icons[1]))->toHaveCount(1);
    expect(definedSpriteIds(stackOf($rendered)))->toHaveCount(1);
});

it('keeps the title out of the sprite for a repeated deferred icon', function () {
    $rendered = render('<x-icon:test-icon defer title="A square" /><x-icon:test-icon defer title="A square" />');

    expect(substr_count(bodyOf($rendered), '<title>A square</title>'))->toBe(2);
    expect(stackOf($rendered))->not->toContain('<title>');
});
