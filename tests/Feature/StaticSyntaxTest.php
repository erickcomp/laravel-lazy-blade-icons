<?php

declare(strict_types=1);

use BladeUI\Icons\Factory as IconFactory;
use ErickComp\LazyBladeIcons\Exceptions\InvalidIconTagUsage;
use Illuminate\View\ViewException;

it('renders the icon named by the tag suffix', function () {
    $rendered = bodyOf(render('<x-icon:test-icon />'));

    expect(trim($rendered))->toBe(app(IconFactory::class)->svg('test-icon')->toHtml());
});

it('applies the class attribute', function () {
    expect(render('<x-icon:test-icon class="w-4 h-4" />'))->toContain('class="w-4 h-4"');
});

it('passes other attributes through to the svg element', function () {
    $rendered = render('<x-icon:test-icon id="camera" data-role="icon" aria-hidden />');

    expect($rendered)
        ->toContain('id="camera"')
        ->toContain('data-role="icon"')
        ->toContain('aria-hidden');
});

it('applies default classes from the set and from the global config', function () {
    config(['blade-icons.class' => 'global-icon']);
    $this->iconSetOptions = ['class' => 'set-icon'];

    expect(render('<x-icon:test-icon />'))->toContain('class="global-icon set-icon"');
});

it('renders a title element with the img role', function () {
    $rendered = render('<x-icon:test-icon title="A square" />');

    expect($rendered)
        ->toContain('<title>A square</title>')
        ->toContain('role="img"');
});

it('supports blade attribute syntax on the tag', function () {
    $rendered = render(
        '<x-icon:test-icon @class(["w-4", "active" => $on]) :$id />',
        ['on' => true, 'id' => 'camera'],
    );

    expect($rendered)
        ->toContain('w-4 active')
        ->toContain('id="camera"');
});

it('rejects content on a tag that already names its icon', function () {
    render('<x-icon:test-icon>test-other</x-icon:test-icon>');
})->throws(ViewException::class);

it('rejects an is attribute on a tag that already names its icon', function () {
    try {
        render('<x-icon:test-icon is="test-other" />');
    } catch (ViewException $e) {
        expect($e->getPrevious())->toBeInstanceOf(InvalidIconTagUsage::class);

        return;
    }

    $this->fail('Expected a ViewException.');
});
