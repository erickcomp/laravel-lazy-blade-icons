# laravel-lazy-blade-icons

> Component-like tag syntax for [blade-icons](https://github.com/blade-ui-kit/blade-icons), with `defer`, fallbacks and runtime icon names — without registering every icon as a Blade component on every request.

```blade
<x-icon:fas-camera class="w-6 h-6" defer />
<x-icon:dynamic :is="$icon" />
<x-icon:dynamic>{{ $icon }}</x-icon:dynamic>
```

## Why this exists

blade-icons offers three ways to render an icon, and each one makes you give something up:

| | `<x-fas-camera />` | `<x-icon name="…" />` | `@svg()` / helper | **this package** |
|---|---|---|---|---|
| Icon named by the tag itself | yes | no | no | **yes** |
| Registers every icon on every request | **always** | avoidable with `components.disabled` | avoidable, same way | **never** |
| `defer` | yes | yes | **no** | **yes** |
| Fallback icons | **no** | yes | yes | **yes** |
| Writes a compiled view file per rendered icon | yes | yes | no | **no** |
| Runtime icon names | no | yes | yes | **yes** |

Two of those rows deserve an explanation.

**Registration.** `BladeUI\Icons\Factory::registerComponents()` walks the whole icon manifest and calls `Blade::component()` once per icon, for every set — on every request that renders any view, no matter how many icons the page actually uses. `php artisan icons:cache` removes the filesystem scan, not the registration loop. blade-icons acknowledges the problem itself: `components.disabled` exists, in its own words, "to avoid performance problems when working with large icon libraries" — but turning it on costs you the tag syntax entirely.

**Defer.** `Svg::deferContent()` returns a string containing *literal Blade directives* (`@once`, `@push`). Those are only ever compiled on one path: a class-based Blade component, whose returned string Laravel writes to `storage/framework/views` and renders as a real view. That is why the blade-icons README warns that deferring "doesn't work with the `@svg` Blade directive or the `svg()` helper function" — and why this package reimplements deferring against the view factory directly, instead of forwarding the attribute.

The upshot is that this package occupies a spot none of the native syntaxes do: short tags, deferring, fallbacks and runtime names, at no fixed per-request cost.

## Requirements

- PHP 8.3+
- Laravel 12.60+ or 13.10+
- blade-icons 1.10+

## Installation

```bash
composer require erickcomp/laravel-lazy-blade-icons
```

The service provider is auto-discovered. Publish the config if you want to change anything:

```bash
php artisan vendor:publish --tag=lazy-blade-icons
```

## Usage

### Static icons

The icon name is the tag suffix — the same name you would pass to `@svg()`:

```blade
<x-icon:fas-camera />
<x-icon:fas-camera class="w-6 h-6" />
<x-icon:fas-camera class="w-6 h-6" id="settings-icon" style="color:#555" data-baz />
```

Every attribute lands on the `<svg>` element, and the set's and the global default classes apply, exactly as with the native syntaxes. `title` produces a `<title>` element with `role="img"`, same as blade-icons.

### Runtime icon names

Use the reserved `dynamic` suffix. The name can come from the `is` attribute:

```blade
<x-icon:dynamic :is="$icon" />
<x-icon:dynamic is="fas-camera" />
<x-icon:dynamic is="{{ $icon }}" />
<x-icon:dynamic :is="$open ? 'fas-door-open' : 'fas-door-closed'" class="w-6" defer />
```

…or from the tag content, which is handy when the name is assembled or chosen by control flow:

```blade
<x-icon:dynamic>fa-{{ $style }}-camera</x-icon:dynamic>

<x-icon:dynamic class="mt-2" defer>
    @if ($open)
        fas-door-open
    @else
        fas-door-closed
    @endif
</x-icon:dynamic>
```

The content is template text, so the name goes in as plain text — no `echo`, no quotes. Surrounding whitespace is trimmed.

Giving the name **both** ways throws, as does giving it neither. Content or an `is` attribute on a tag that already names its icon (`<x-icon:fas-camera>…`) throws too — the error messages say which is which.

> `dynamic` is a reserved suffix. Icons in a set are always prefixed (`fas-`, `heroicon-`…), so a real icon can't collide with it; if you somehow have one, rename the keyword with the `dynamic_keyword` config option.

### Deferring

When the same icon appears many times on a page, `defer` emits it once as a sprite and references it everywhere else:

```blade
<x-icon:fas-camera defer />
```

The sprites are pushed to the `bladeicons` stack, so your layout needs it near the bottom:

```blade
    <svg hidden class="hidden">
        @stack('bladeicons')
    </svg>
</body>
```

> **Without that stack, deferred icons render nothing** — they become `<use>` elements pointing at an id that was never emitted.

This is the same stack, the same id scheme and the same hash as blade-icons' own `defer`, so the two interoperate: `<x-icon:fas-camera defer />` and `<x-fas-camera defer />` on the same page share a single sprite.

Pin an id when you want to reference the icon from JavaScript:

```blade
<x-icon:fas-camera defer="my-custom-hash" />
```

```js
const icon = () => <svg><use href="#icon-my-custom-hash"></use></svg>
```

### Deferring everything

Set `defer` in the config to stop repeating the attribute:

| config | tag | resulting id | effect |
|---|---|---|---|
| `false` (default) | — | — | nothing is deferred |
| `false` | `defer` | `icon-{md5}` | defers that tag |
| `true` | — | `icon-{md5}` | defers everything |
| `true` | `defer="false"` | — | opts that tag out |
| `'admin'` | — | `icon-admin-{md5}` | defers everything, ids namespaced |
| `'admin'` | `defer` | `icon-admin-{md5}` | same — the bare attribute only reasserts it |
| any | `defer="my-id"` | `icon-my-id` | pins that icon's id |

A string in the config namespaces the generated ids; a string on a tag pins that one icon's id. Two different icons can therefore never collide on a shared id.

### Fallback icons

blade-icons' `fallback` options work here, including the per-set one — which the native per-icon component syntax cannot do, because Blade fails to resolve the unknown component before the icon factory is ever consulted.

## Configuration

```php
return [
    'prefix' => 'icon:',            // <x-icon:fas-camera />
    'dynamic_keyword' => 'dynamic', // <x-icon:dynamic :is="$icon" />
    'stack' => 'bladeicons',        // @stack('bladeicons')
    'defer' => false,               // false | true | 'namespace'
];
```

## Benchmark

Measured with 9 icon sets installed (24,991 icons: Font Awesome, Heroicons, Bootstrap Icons, Lucide, Tabler, Phosphor), PHP 8.4, Laravel 13, Xdebug off, medians of 7 runs. Reproduce with `composer install -d benchmarks && php benchmarks/run.php`.

**What every request pays before a single icon is rendered:**

| Sets | Icons | Manifest scan (no `icons:cache`) | Manifest read (`icons:cache`) | `Blade::component()` loop | Per request with `icons:cache` |
|---|---|---|---|---|---|
| 1 set | 1,288 | 1.21 ms | 0.22 ms | 0.56 ms | 0.78 ms |
| 2 sets | 10,360 | 8.84 ms | 1.79 ms | 3.76 ms | 5.55 ms |
| 4 sets | 14,486 | 12.91 ms | 3.40 ms | 5.42 ms | 8.82 ms |
| 9 sets | 24,991 | 23.55 ms | 5.42 ms | 9.14 ms | **14.56 ms** |

This package pays none of it.

**Rendering:**

| Syntax | 1 icon | 25 icons | 250 icons | Compiled view files per 50 icons |
|---|---|---|---|---|
| `<x-fas-camera />` | 0.10 ms | 1.55 ms | 15.77 ms | 49 |
| `<x-icon name="…" />` | 0.10 ms | 1.55 ms | 15.54 ms | 51 |
| `@svg()` | 0.04 ms | 0.29 ms | 2.59 ms | 1 |
| `<x-icon:fas-camera />` | 0.07 ms | 0.57 ms | 5.41 ms | 1 |
| `<x-icon:dynamic :is="…" />` | 0.07 ms | 0.74 ms | 5.52 ms | 1 |
| `<x-icon:dynamic>…</x-icon:dynamic>` | 0.07 ms | 0.60 ms | 5.52 ms | 1 |

**Total per request, 250 icons on the page:**

| Setup | Fixed | Render | Total |
|---|---|---|---|
| `<x-fas-camera />` (components enabled) | 14.71 ms | 15.77 ms | 30.47 ms |
| `<x-icon name="…" />` + `components.disabled` | 0.00 ms | 15.54 ms | 15.54 ms |
| `@svg()` + `components.disabled` (no `defer`) | 0.00 ms | 2.59 ms | 2.59 ms |
| **`<x-icon:fas-camera />`** | **0.00 ms** | **5.41 ms** | **5.41 ms** |
| **`<x-icon:dynamic …>`** | **0.00 ms** | **5.52 ms** | **5.52 ms** |

Deferring 250 copies of one icon takes the HTML from 254.4 KB to 44.9 KB, in 6.26 ms against 18.16 ms for the native component.

## Limitations

- **No IDE support for the tags.** They never reach Blade's component registry, so editor plugins that understand blade-icons — Laravel Idea in particular — won't autocomplete or navigate them.
- **Tag matching is its own regex.** It comes from [erickcomp/laravel-raw-blade-components](https://packagist.org/packages/erickcomp/laravel-raw-blade-components) and parallels Blade's own component-tag grammar rather than reusing it, so exotic edge cases may diverge.
- **`@verbatim` does not protect these tags.** A native component inside `@verbatim` is left alone; one of these tags is still rewritten, so `@verbatim<x-icon:fas-camera />@endverbatim` renders the icon instead of printing the tag. The rewrite runs in Blade's `prepareStringsForCompilationUsing` pass, which happens before the pass that shields verbatim blocks. It only bites when you are documenting the syntax inside a Blade view; use `&lt;x-icon:…&gt;` or a plain string there.
- **Deferring reads blade-icons' output.** It post-processes the markup `Svg::toHtml()` returns; an upstream change to that format would need a matching change here. Two tests guard the boundary: one asserts the sprite id matches the native component's, the other asserts the upstream defer markup still carries uncompiled directives.
- **No slots or `@props`.** Icons don't need them, but don't expect this to generalise into a component system.

## How it works

The package registers a single prefixed raw component through [erickcomp/laravel-raw-blade-components](https://packagist.org/packages/erickcomp/laravel-raw-blade-components), which rewrites the tags during Blade's `prepareStringsForCompilationUsing` pass — before the component compiler ever sees them. The compiled template calls `IconRenderer`, which asks `BladeUI\Icons\Factory` for the SVG and, when deferring, pushes the sprite through the view factory's own stack and once-tracking. There is no component class, no alias registration and no per-icon compiled view.

## Testing

```bash
composer install
vendor/bin/pest
```

## License

MIT.
