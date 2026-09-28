<?php

declare(strict_types=1);

namespace ErickComp\LazyBladeIcons;

use BladeUI\Icons\Factory as IconFactory;
use ErickComp\LazyBladeIcons\Exceptions\AmbiguousIconName;
use ErickComp\LazyBladeIcons\Exceptions\InvalidIconTagUsage;
use ErickComp\LazyBladeIcons\Exceptions\MissingIconName;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\Factory as ViewFactory;

/**
 * Renders icon tags. Bound as a singleton, so the memos below live as long as
 * the instance: one request under PHP-FPM, and possibly many under a
 * long-lived worker such as Octane. That is safe because the markup only
 * depends on the icon sets and the blade-icons config, which are fixed once the
 * app has booted; call flush() after registering a set at runtime.
 */
class IconRenderer
{
    /**
     * How many rendered icons render() keeps before starting over. Its keys
     * carry attribute values, so without a cap an attribute that differs on
     * every row (an id, a wire:key) would grow it for the worker's lifetime.
     */
    protected int $svgMemoLimit = 1000;

    /**
     * The markup and the pristine contents of rendered icons, keyed by name and
     * attributes.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected array $svgMemo = [];

    /**
     * Keyed by the svg contents. Like blade-icons' own contents cache, bounded
     * by the icons the sets hold, so it takes no cap.
     *
     * @var array<string, string>
     */
    protected array $innerContentsMemo = [];

    /** @var array<string, string> md5 of the inner contents, keyed by them */
    protected array $hashMemo = [];

    public function __construct(
        protected IconFactory $icons,
        protected ViewFactory $views,
        protected string $dynamicKeyword = 'dynamic',
        protected string $stack = 'bladeicons',
        protected bool|string $defer = false,
    ) {}

    public function renderFromTag(
        string $tag,
        string $prefix,
        ComponentAttributeBag $attributes,
        ?string $content = null,
    ): string {
        $suffix = \substr($tag, \strlen($prefix));

        return $this->render(
            $this->resolveName($tag, $suffix, $attributes, $content),
            $attributes,
        );
    }

    public function render(string $name, ComponentAttributeBag $attributes): string
    {
        [$html, $contents] = $this->svg($name, $attributes);

        [$defer, $fromAttribute] = $this->resolveDefer($attributes);

        if ($defer === false) {
            return $html;
        }

        // Runs on every call, memoized svg or not: the view factory forgets
        // which sprites it pushed when a top-level render finishes, so the
        // next one has to push them again.
        return $this->deferIcon($html, $contents, $defer, $fromAttribute);
    }

    /**
     * Forgets every memoized icon. Only needed after registering an icon set,
     * or otherwise changing blade-icons, once icons have been rendered.
     */
    public function flush(): void
    {
        $this->svgMemo = [];
        $this->innerContentsMemo = [];
        $this->hashMemo = [];
    }

    /**
     * @return array{0: string, 1: string} the icon markup, and the pristine svg contents
     */
    protected function svg(string $name, ComponentAttributeBag $attributes): array
    {
        $class = (string) $attributes->get('class', '');
        $rest = $attributes->except(['class', 'defer', 'is'])->all();
        $key = $this->svgMemoKey($name, $class, $rest);

        if ($key !== null && isset($this->svgMemo[$key])) {
            return $this->svgMemo[$key];
        }

        $svg = $this->icons->svg($name, $class, $rest);

        // Captured before toHtml(), which injects the attributes (and a <title>
        // element) into the markup. blade-icons hashes and sprites this same
        // pristine content, so both syntaxes converge on the same sprite id.
        $contents = $svg->contents();
        $html = $svg->toHtml();

        if ($key === null) {
            return [$html, $contents];
        }

        if (\count($this->svgMemo) >= $this->svgMemoLimit) {
            $this->svgMemo = [];
        }

        return $this->svgMemo[$key] = [$html, $contents];
    }

    /**
     * Null when an attribute holds anything but a scalar or null: an object
     * renders through its own __toString(), which a key cannot capture.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    protected function svgMemoKey(string $name, string $class, array $attributes): ?string
    {
        foreach ($attributes as $value) {
            if ($value !== null && ! \is_scalar($value)) {
                return null;
            }
        }

        // serialize() keeps the attributes' order, which is also their order in
        // the markup, and their types.
        return \serialize([$name, $class, $attributes]);
    }

    protected function resolveName(
        string $tag,
        string $suffix,
        ComponentAttributeBag $attributes,
        ?string $content,
    ): string {
        $fromContent = $content === null ? null : (\trim($content) ?: null);

        if ($suffix !== $this->dynamicKeyword) {
            if ($attributes->has('is')) {
                throw InvalidIconTagUsage::unexpectedIsAttribute($tag, $this->dynamicKeyword);
            }

            if ($fromContent !== null) {
                throw InvalidIconTagUsage::unexpectedContent($tag, $this->dynamicKeyword);
            }

            return $suffix;
        }

        $fromAttribute = $attributes->has('is')
            ? (\trim((string) $attributes->get('is')) ?: null)
            : null;

        if ($fromAttribute !== null && $fromContent !== null) {
            throw AmbiguousIconName::forTag($tag, $fromAttribute, $fromContent);
        }

        return $fromAttribute ?? $fromContent ?? throw MissingIconName::forTag($tag);
    }

    /**
     * @return array{0: bool|string, 1: bool} the resolved defer value, and whether it came from the tag
     */
    protected function resolveDefer(ComponentAttributeBag $attributes): array
    {
        if (! $attributes->has('defer')) {
            return [$this->defer, false];
        }

        $value = $attributes->get('defer');

        return [\filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) ?? $value, true];
    }

    protected function deferIcon(string $html, string $contents, bool|string $defer, bool $fromAttribute): string
    {
        $inner = $this->innerContents($contents);
        $id = $this->deferId($inner, $defer, $fromAttribute);

        $html = \str_replace($inner, '<use href="#' . $id . '"></use>', $html) . \PHP_EOL;

        if (! $this->views->hasRenderedOnce($id)) {
            $this->views->startPush($this->stack, $this->sprite($id, $inner));
            $this->views->markAsRenderedOnce($id);
        }

        return $html;
    }

    protected function innerContents(string $contents): string
    {
        return $this->innerContentsMemo[$contents]
            ??= \preg_replace(['/<svg[^>]*>/', '/<\/svg>/'], '', $contents);
    }

    protected function deferId(string $inner, bool|string $defer, bool $fromAttribute): string
    {
        if ($fromAttribute && \is_string($defer)) {
            return 'icon-' . $defer;
        }

        $namespace = \is_string($this->defer) ? $this->defer . '-' : '';

        // Unix line endings, so the id matches blade-icons' own on any platform.
        return 'icon-' . $namespace . ($this->hashMemo[$inner] ??= \md5(\str_replace(\PHP_EOL, "\n", $inner)));
    }

    protected function sprite(string $id, string $inner): string
    {
        return \PHP_EOL . '<g id="' . $id . '">' . \PHP_EOL . \trim($inner) . \PHP_EOL . '</g>' . \PHP_EOL;
    }
}
