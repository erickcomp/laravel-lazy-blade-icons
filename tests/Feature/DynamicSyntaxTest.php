<?php

declare(strict_types=1);

use BladeUI\Icons\Factory as IconFactory;
use ErickComp\LazyBladeIcons\Exceptions\AmbiguousIconName;
use ErickComp\LazyBladeIcons\Exceptions\MissingIconName;
use Illuminate\View\ViewException;

function expectedIcon(string $name): string
{
    return app(IconFactory::class)->svg($name)->toHtml();
}

it('resolves the name from a bound is attribute', function () {
    $rendered = bodyOf(render('<x-icon:dynamic :is="$icon" />', ['icon' => 'test-other']));

    expect(trim($rendered))->toBe(expectedIcon('test-other'));
});

it('resolves the name from a literal is attribute', function () {
    expect(trim(bodyOf(render('<x-icon:dynamic is="test-icon" />'))))->toBe(expectedIcon('test-icon'));
});

it('resolves the name from an interpolated is attribute', function () {
    $rendered = bodyOf(render('<x-icon:dynamic is="{{ $icon }}" />', ['icon' => 'test-other']));

    expect(trim($rendered))->toBe(expectedIcon('test-other'));
});

it('resolves the name from an expression in a bound is attribute', function () {
    $rendered = bodyOf(render(
        '<x-icon:dynamic :is="$open ? \'test-icon\' : \'test-other\'" />',
        ['open' => false],
    ));

    expect(trim($rendered))->toBe(expectedIcon('test-other'));
});

it('applies attributes alongside the is attribute', function () {
    $rendered = render('<x-icon:dynamic :is="$icon" class="w-4" id="camera" />', ['icon' => 'test-icon']);

    expect($rendered)
        ->toContain('class="w-4"')
        ->toContain('id="camera"');
});

it('does not leak the is attribute onto the svg element', function () {
    expect(render('<x-icon:dynamic is="test-icon" />'))->not->toContain('is=');
});

it('resolves the name from the tag content', function () {
    $rendered = bodyOf(render('<x-icon:dynamic>{{ $icon }}</x-icon:dynamic>', ['icon' => 'test-other']));

    expect(trim($rendered))->toBe(expectedIcon('test-other'));
});

it('resolves the name from mixed text content', function () {
    $rendered = bodyOf(render(
        '<x-icon:dynamic>test-camera-{{ $style }}</x-icon:dynamic>',
        ['style' => 'solid'],
    ));

    expect(trim($rendered))->toBe(expectedIcon('test-camera-solid'));
});

it('resolves the name from blade control flow in the content', function () {
    $template = <<<'BLADE'
        <x-icon:dynamic class="mt-2">
            @if ($open)
                test-icon
            @else
                test-other
            @endif
        </x-icon:dynamic>
        BLADE;

    expect(bodyOf(render($template, ['open' => false])))->toContain(
        trim(app(IconFactory::class)->svg('test-other', 'mt-2')->toHtml()),
    );
});

it('ignores surrounding whitespace in the content', function () {
    $rendered = bodyOf(render("<x-icon:dynamic>\n    test-icon   \n</x-icon:dynamic>"));

    expect(trim($rendered))->toBe(expectedIcon('test-icon'));
});

it('applies attributes from the opening tag of the content form', function () {
    $rendered = render('<x-icon:dynamic class="w-4" id="camera">test-icon</x-icon:dynamic>');

    expect($rendered)
        ->toContain('class="w-4"')
        ->toContain('id="camera"');
});

it('fails when the icon name is given twice', function () {
    try {
        render('<x-icon:dynamic is="test-icon">test-other</x-icon:dynamic>');
    } catch (ViewException $e) {
        expect($e->getPrevious())->toBeInstanceOf(AmbiguousIconName::class);

        return;
    }

    $this->fail('Expected a ViewException.');
});

it('fails when no icon name is given', function (string $template) {
    try {
        render($template);
    } catch (ViewException $e) {
        expect($e->getPrevious())->toBeInstanceOf(MissingIconName::class);

        return;
    }

    $this->fail('Expected a ViewException.');
})->with([
    'self closing' => '<x-icon:dynamic />',
    'empty content' => '<x-icon:dynamic></x-icon:dynamic>',
    'blank content' => "<x-icon:dynamic>\n   \n</x-icon:dynamic>",
    'empty is' => '<x-icon:dynamic is="" />',
]);

it('honours a custom dynamic keyword', function () {
    config(['lazy-blade-icons.dynamic_keyword' => 'runtime']);

    $rendered = bodyOf(render('<x-icon:runtime :is="$icon" />', ['icon' => 'test-icon']));

    expect(trim($rendered))->toBe(expectedIcon('test-icon'));
});
