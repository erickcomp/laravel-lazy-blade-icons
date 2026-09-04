<?php

declare(strict_types=1);

use ErickComp\LazyBladeIcons\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

uses(TestCase::class)->in(__DIR__);

/**
 * Renders a Blade string with the deferred icon stack appended, so a single
 * assertion target holds both the icons and the sprites they pushed.
 */
function render(string $template, array $data = [], string $stack = 'bladeicons'): string
{
    return Blade::render(
        $template . "\n<!--STACK-->@stack('{$stack}')",
        $data,
        deleteCachedView: true,
    );
}

function stackOf(string $rendered): string
{
    return substr($rendered, strpos($rendered, '<!--STACK-->') + strlen('<!--STACK-->'));
}

function bodyOf(string $rendered): string
{
    return substr($rendered, 0, strpos($rendered, '<!--STACK-->'));
}

/** Sprite ids referenced by <use> elements, in order of appearance. */
function referencedSpriteIds(string $html): array
{
    preg_match_all('/<use href="#([^"]+)"><\/use>/', $html, $matches);

    return $matches[1];
}

/** Sprite ids defined by <g> elements, in order of appearance. */
function definedSpriteIds(string $html): array
{
    preg_match_all('/<g id="([^"]+)">/', $html, $matches);

    return $matches[1];
}
