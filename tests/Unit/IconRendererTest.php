<?php

declare(strict_types=1);

use BladeUI\Icons\Factory as IconFactory;
use ErickComp\LazyBladeIcons\Exceptions\AmbiguousIconName;
use ErickComp\LazyBladeIcons\Exceptions\InvalidIconTagUsage;
use ErickComp\LazyBladeIcons\Exceptions\MissingIconName;
use ErickComp\LazyBladeIcons\IconRenderer;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\Factory as ViewFactory;

function renderer(bool|string $defer = false, string $keyword = 'dynamic', string $stack = 'bladeicons'): IconRenderer
{
    return new IconRenderer(
        app(IconFactory::class),
        app(ViewFactory::class),
        $keyword,
        $stack,
        $defer,
    );
}

function attrs(array $attributes = []): ComponentAttributeBag
{
    return new ComponentAttributeBag($attributes);
}

function pushed(string $stack = 'bladeicons'): string
{
    return app(ViewFactory::class)->yieldPushContent($stack);
}

it('renders the icon named by the tag suffix', function () {
    $html = renderer()->renderFromTag('x-icon:test-icon', 'x-icon:', attrs());

    expect($html)->toBe(app(IconFactory::class)->svg('test-icon')->toHtml());
});

it('forwards the class and the remaining attributes', function () {
    $html = renderer()->renderFromTag('x-icon:test-icon', 'x-icon:', attrs([
        'class' => 'w-4',
        'id' => 'camera',
    ]));

    expect($html)->toContain('class="w-4"')->toContain('id="camera"');
});

it('renders a sprite reference and pushes the sprite when deferring', function () {
    $html = renderer(defer: true)->renderFromTag('x-icon:test-icon', 'x-icon:', attrs());

    $id = referencedSpriteIds($html)[0];

    expect($html)->not->toContain('<path');
    expect(definedSpriteIds(pushed()))->toBe([$id]);
});

it('pushes a given sprite only once', function () {
    $renderer = renderer(defer: true);

    $renderer->renderFromTag('x-icon:test-icon', 'x-icon:', attrs());
    $renderer->renderFromTag('x-icon:test-icon', 'x-icon:', attrs());

    expect(definedSpriteIds(pushed()))->toHaveCount(1);
});

it('resolves a dynamic name from the is attribute', function () {
    $html = renderer()->renderFromTag('x-icon:dynamic', 'x-icon:', attrs(['is' => 'test-other']));

    expect($html)->toBe(app(IconFactory::class)->svg('test-other')->toHtml());
});

it('resolves a dynamic name from the content', function () {
    $html = renderer()->renderFromTag('x-icon:dynamic', 'x-icon:', attrs(), "\n  test-other \n");

    expect($html)->toBe(app(IconFactory::class)->svg('test-other')->toHtml());
});

it('rejects a name given both ways', function () {
    renderer()->renderFromTag('x-icon:dynamic', 'x-icon:', attrs(['is' => 'test-icon']), 'test-other');
})->throws(AmbiguousIconName::class);

it('rejects a dynamic tag with no name', function () {
    renderer()->renderFromTag('x-icon:dynamic', 'x-icon:', attrs(), '   ');
})->throws(MissingIconName::class);

it('rejects content on a tag that already names its icon', function () {
    renderer()->renderFromTag('x-icon:test-icon', 'x-icon:', attrs(), 'test-other');
})->throws(InvalidIconTagUsage::class);

it('rejects an is attribute on a tag that already names its icon', function () {
    renderer()->renderFromTag('x-icon:test-icon', 'x-icon:', attrs(['is' => 'test-other']));
})->throws(InvalidIconTagUsage::class);

it('honours a custom dynamic keyword', function () {
    $html = renderer(keyword: 'runtime')->renderFromTag('x-icon:runtime', 'x-icon:', attrs(['is' => 'test-icon']));

    expect($html)->toBe(app(IconFactory::class)->svg('test-icon')->toHtml());
});

it('pushes to a custom stack', function () {
    renderer(defer: true, stack: 'my-icons')->renderFromTag('x-icon:test-icon', 'x-icon:', attrs());

    expect(definedSpriteIds(pushed('my-icons')))->toHaveCount(1);
    expect(pushed())->toBe('');
});

it('reads defer from the tag over the configured default', function (bool|string $configured, mixed $attribute, ?string $expected) {
    $html = renderer(defer: $configured)->renderFromTag(
        'x-icon:test-icon',
        'x-icon:',
        attrs($attribute === null ? [] : ['defer' => $attribute]),
    );

    $ids = referencedSpriteIds($html);

    $expected === null
        ? expect($ids)->toBeEmpty()
        : expect($ids[0])->toStartWith($expected);
})->with([
    'off by default' => [false, null, null],
    'on by tag' => [false, true, 'icon-'],
    'on by config' => [true, null, 'icon-'],
    'off by tag' => [true, 'false', null],
    'namespaced by config' => ['admin', null, 'icon-admin-'],
    'namespaced with bare tag' => ['admin', true, 'icon-admin-'],
    'pinned by tag' => ['admin', 'my-id', 'icon-my-id'],
]);

/** A renderer that reports how many icons it holds in its render memo. */
function inspectableRenderer(int $limit = 1000): IconRenderer
{
    return new class(app(IconFactory::class), app(ViewFactory::class), $limit) extends IconRenderer
    {
        public function __construct(IconFactory $icons, ViewFactory $views, int $limit)
        {
            parent::__construct($icons, $views);

            $this->svgMemoLimit = $limit;
        }

        public function memoized(): int
        {
            return count($this->svgMemo);
        }
    };
}

it('memoizes an icon once per name and attributes', function () {
    $renderer = inspectableRenderer();

    $renderer->render('test-icon', attrs(['class' => 'w-4']));
    $renderer->render('test-icon', attrs(['class' => 'w-4', 'defer' => true]));
    $renderer->renderFromTag('x-icon:dynamic', 'x-icon:', attrs(['is' => 'test-icon', 'class' => 'w-4']));

    expect($renderer->memoized())->toBe(1);

    $renderer->render('test-icon', attrs(['class' => 'w-6']));
    $renderer->render('test-icon', attrs(['class' => 'w-4', 'id' => 'a']));

    expect($renderer->memoized())->toBe(3);
});

it('does not memoize an icon with a non-scalar attribute', function () {
    $renderer = inspectableRenderer();
    $counter = new class implements Stringable
    {
        private int $calls = 0;

        public function __toString(): string
        {
            return (string) ++$this->calls;
        }
    };

    expect($renderer->render('test-icon', attrs(['data-n' => $counter])))->toContain('data-n="1"');
    expect($renderer->render('test-icon', attrs(['data-n' => $counter])))->toContain('data-n="2"');
    expect($renderer->render('test-icon', attrs(['data-x' => new HtmlString('a')])))->toContain('data-x="a"');
    expect($renderer->memoized())->toBe(0);
});

it('starts the memo over once it reaches its limit', function () {
    $renderer = inspectableRenderer(limit: 2);

    foreach (['a', 'b', 'c'] as $id) {
        expect($renderer->render('test-icon', attrs(['id' => $id])))
            ->toBe(app(IconFactory::class)->svg('test-icon', '', ['id' => $id])->toHtml());
    }

    expect($renderer->memoized())->toBe(1);
});

it('picks up a set registered after the first render once flushed', function () {
    config(['blade-icons.fallback' => 'test-fallback']);

    $renderer = renderer();
    $fallback = app(IconFactory::class)->svg('test-fallback')->toHtml();

    expect($renderer->render('late-icon', attrs()))->toBe($fallback);

    app(IconFactory::class)->add('late', ['paths' => [__DIR__ . '/../Fixtures/icons'], 'prefix' => 'late']);

    expect($renderer->render('late-icon', attrs()))->toBe($fallback);

    $renderer->flush();

    expect($renderer->render('late-icon', attrs()))
        ->toBe(app(IconFactory::class)->svg('late-icon')->toHtml())
        ->not->toBe($fallback);
});
