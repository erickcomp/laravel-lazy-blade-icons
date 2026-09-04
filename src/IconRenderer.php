<?php

declare(strict_types=1);

namespace ErickComp\LazyBladeIcons;

use BladeUI\Icons\Factory as IconFactory;
use ErickComp\LazyBladeIcons\Exceptions\AmbiguousIconName;
use ErickComp\LazyBladeIcons\Exceptions\InvalidIconTagUsage;
use ErickComp\LazyBladeIcons\Exceptions\MissingIconName;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\Factory as ViewFactory;

class IconRenderer
{
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
        $svg = $this->icons->svg(
            $name,
            (string) $attributes->get('class', ''),
            $attributes->except(['class', 'defer', 'is'])->all(),
        );

        // Captured before toHtml(), which injects the attributes (and a <title>
        // element) into the markup. blade-icons hashes and sprites this same
        // pristine content, so both syntaxes converge on the same sprite id.
        $contents = $svg->contents();
        $html = $svg->toHtml();

        [$defer, $fromAttribute] = $this->resolveDefer($attributes);

        if ($defer === false) {
            return $html;
        }

        return $this->deferIcon($html, $contents, $defer, $fromAttribute);
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
        return \preg_replace(['/<svg[^>]*>/', '/<\/svg>/'], '', $contents);
    }

    protected function deferId(string $inner, bool|string $defer, bool $fromAttribute): string
    {
        if ($fromAttribute && \is_string($defer)) {
            return 'icon-' . $defer;
        }

        $namespace = \is_string($this->defer) ? $this->defer . '-' : '';

        // Unix line endings, so the id matches blade-icons' own on any platform.
        return 'icon-' . $namespace . \md5(\str_replace(\PHP_EOL, "\n", $inner));
    }

    protected function sprite(string $id, string $inner): string
    {
        return \PHP_EOL . '<g id="' . $id . '">' . \PHP_EOL . \trim($inner) . \PHP_EOL . '</g>' . \PHP_EOL;
    }
}
