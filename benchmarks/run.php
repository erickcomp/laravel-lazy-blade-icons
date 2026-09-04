<?php

declare(strict_types=1);

/**
 * Benchmark harness for erickcomp/laravel-lazy-blade-icons.
 *
 * Run it with Xdebug OFF, or the numbers are meaningless:
 *   php benchmarks/run.php
 */

require __DIR__ . '/vendor/autoload.php';

use BladeUI\Icons\Factory as IconFactory;
use BladeUI\Icons\IconsManifest;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\Foundation\Application as Testbench;

if (\extension_loaded('xdebug')) {
    \fwrite(\STDERR, "Refusing to run: Xdebug is loaded and would distort every measurement.\n");
    exit(1);
}

const ITERATIONS = 7;
const RENDER_COUNTS = [1, 25, 250];

/** @return array{median: float, min: float, max: float} milliseconds */
function bench(callable $callback, int $iterations = ITERATIONS): array
{
    $callback();

    $samples = [];

    for ($i = 0; $i < $iterations; $i++) {
        $start = \hrtime(true);
        $callback();
        $samples[] = (\hrtime(true) - $start) / 1e6;
    }

    \sort($samples);

    return [
        'median' => $samples[\intdiv(\count($samples), 2)],
        'min' => $samples[0],
        'max' => $samples[\count($samples) - 1],
    ];
}

function ms(array $result): string
{
    return \number_format($result['median'], 2) . ' ms';
}

function bytes(int $value): string
{
    return \number_format($value / 1024, 1) . ' KB';
}

function heading(string $title): void
{
    echo "\n\n## {$title}\n\n";
}

function table(array $headers, array $rows): void
{
    echo '| ' . \implode(' | ', $headers) . " |\n";
    echo '|' . \str_repeat('---|', \count($headers)) . "\n";

    foreach ($rows as $row) {
        echo '| ' . \implode(' | ', $row) . " |\n";
    }
}

/**
 * Testbench's own discovery does not see this project's vendor directory, so
 * the providers are read straight from what composer actually installed.
 *
 * @return list<string>
 */
function installedProviders(): array
{
    $installed = \json_decode(\file_get_contents(__DIR__ . '/vendor/composer/installed.json'), true);

    $providers = [];

    foreach ($installed['packages'] as $package) {
        foreach ($package['extra']['laravel']['providers'] ?? [] as $provider) {
            $providers[] = $provider;
        }
    }

    return $providers;
}

function makeApp(array $config = []): \Illuminate\Foundation\Application
{
    return Testbench::create(
        basePath: __DIR__ . '/vendor/orchestra/testbench-core/laravel',
        resolvingCallback: function ($app) use ($config) {
            foreach ($config as $key => $value) {
                $app['config']->set($key, $value);
            }
        },
        options: ['extra' => ['providers' => installedProviders()]],
    );
}

$app = makeApp();

// Views compiled by earlier runs would hide the per-icon view files the
// component pipeline writes, and would skew the first timings.
foreach (\glob($app['config']->get('view.compiled') . '/*.php') as $compiled) {
    @\unlink($compiled);
}

/** @var IconFactory $factory */
$factory = $app->make(IconFactory::class);
$sets = $factory->all();

echo "# Benchmark: erickcomp/laravel-lazy-blade-icons\n";
echo "\nPHP " . \PHP_VERSION . ', Laravel ' . $app->version() . ', ' . \count($sets) . " icon sets installed.\n";

$manifest = $app->make(IconsManifest::class)->getManifest($sets);
$iconCount = 0;
$namesBySet = [];

foreach ($manifest as $set => $paths) {
    foreach ($paths as $icons) {
        $iconCount += \count($icons);
        $prefix = $sets[$set]['prefix'];
        foreach ($icons as $icon) {
            $namesBySet[$set][] = "{$prefix}-{$icon}";
        }
    }
}

echo "\nTotal icons: " . \number_format($iconCount) . "\n";

table(
    ['Set', 'Prefix', 'Icons'],
    \array_map(
        fn ($set) => [$set, $sets[$set]['prefix'], \number_format(\count($namesBySet[$set]))],
        \array_keys($namesBySet),
    ),
);

// ---------------------------------------------------------------------------
// A — what every request pays before a single icon is rendered
// ---------------------------------------------------------------------------

heading('A — Per-request cost of the native component syntax');

$setNames = \array_keys($sets);
$cachePath = \sys_get_temp_dir() . '/blade-icons-benchmark.php';
$rows = [];

foreach ([1, 2, 4, \count($setNames)] as $count) {
    $subset = \array_intersect_key($sets, \array_flip(\array_slice($setNames, 0, $count)));

    $subsetIcons = 0;
    foreach (\array_keys($subset) as $set) {
        $subsetIcons += \count($namesBySet[$set]);
    }

    $scan = bench(function () use ($subset) {
        (new IconsManifest(new Filesystem, '/nonexistent/manifest.php'))->getManifest($subset);
    }, 3);

    (new IconsManifest(new Filesystem, $cachePath))->write($subset);

    $cached = bench(function () use ($subset, $cachePath) {
        (new IconsManifest(new Filesystem, $cachePath))->getManifest($subset);
    });

    $warmManifest = new IconsManifest(new Filesystem, $cachePath);
    $warmManifest->getManifest($subset);

    $register = bench(function () use ($subset, $warmManifest, $app) {
        $factory = new IconFactory(new Filesystem, $warmManifest, null, ['components' => ['disabled' => false]]);

        foreach ($subset as $set => $options) {
            $factory->add($set, $options);
        }

        $factory->registerComponents();
    }, 3);

    $fixedCost = $cached['median'] + $register['median'];

    $rows[] = [
        $count . ' set' . ($count === 1 ? '' : 's'),
        \number_format($subsetIcons),
        ms($scan),
        ms($cached),
        ms($register),
        ms(['median' => $fixedCost]),
    ];
}

@\unlink($cachePath);

table(
    ['Sets', 'Icons', 'Manifest scan (no icons:cache)', 'Manifest read (icons:cache)', 'Blade::component() loop', 'Per request with icons:cache'],
    $rows,
);

echo "\nThis package pays **none** of the above: it never registers icons as Blade components.\n";

// ---------------------------------------------------------------------------
// B — rendering cost per syntax
// ---------------------------------------------------------------------------

$names = $namesBySet['heroicons'] ?? \reset($namesBySet);

function templateFor(string $syntax, array $names, bool $defer = false): string
{
    $attribute = $defer ? ' defer' : '';

    return \implode('', \array_map(static fn ($name) => match ($syntax) {
        'native component' => "<x-{$name}{$attribute} />",
        'native <x-icon>' => "<x-icon name=\"{$name}\"{$attribute} />",
        '@svg directive' => "@svg('{$name}')",
        'x-icon: static' => "<x-icon:{$name}{$attribute} />",
        'x-icon:dynamic is' => "<x-icon:dynamic is=\"{$name}\"{$attribute} />",
        'x-icon:dynamic content' => "<x-icon:dynamic{$attribute}>{$name}</x-icon:dynamic>",
    }, $names));
}

function compiledComponentFiles(\Illuminate\Foundation\Application $app): int
{
    // Component::createBladeViewFromString() writes these next to the compiled
    // views, one per distinct rendered string.
    $path = $app['config']->get('view.compiled');

    return \is_dir($path) ? \count(\glob($path . '/*.blade.php')) : 0;
}

heading('B — Rendering cost per syntax');

$syntaxes = ['native component', 'native <x-icon>', '@svg directive', 'x-icon: static', 'x-icon:dynamic is', 'x-icon:dynamic content'];
$rows = [];

$renderCost = [];

foreach ($syntaxes as $index => $syntax) {
    $row = [$syntax];

    foreach (RENDER_COUNTS as $count) {
        $template = templateFor($syntax, \array_slice($names, 0, $count));

        $result = bench(fn () => Blade::render($template));
        $renderCost[$syntax][$count] = $result['median'];

        $row[] = ms($result);
    }

    // A fresh batch per syntax: these files are keyed by the rendered svg, so a
    // shared batch would let the first syntax measured absorb the whole cost.
    $batch = \array_slice($names, 300 + $index * 50, 50);

    $before = compiledComponentFiles($app);
    Blade::render(templateFor($syntax, $batch));
    $row[] = (string) (compiledComponentFiles($app) - $before);

    $rows[] = $row;
}

table(
    ['Syntax', ...\array_map(fn ($count) => "{$count} icons", RENDER_COUNTS), 'Compiled view files for 50 new icons'],
    $rows,
);

// ---------------------------------------------------------------------------
// C — deferring
// ---------------------------------------------------------------------------

heading('C — Deferring the same icon many times');

$rows = [];

$repeated = \array_fill(0, 250, $names[0]);

foreach (['native component', 'native <x-icon>', 'x-icon: static', 'x-icon:dynamic is'] as $syntax) {
    $template = templateFor($syntax, $repeated, defer: true);

    $rows[] = [
        $syntax,
        ms(bench(fn () => Blade::render($template))),
        bytes(\strlen(Blade::render($template))),
    ];
}

$plain = templateFor('x-icon: static', $repeated);

$rows[] = [
    'x-icon: static (no defer)',
    ms(bench(fn () => Blade::render($plain))),
    bytes(\strlen(Blade::render($plain))),
];

table(['Syntax', '250 identical icons', 'HTML size'], $rows);

// ---------------------------------------------------------------------------
// D — total cost of each realistic setup
// ---------------------------------------------------------------------------

heading('D — Total per-request cost, 9 sets installed, 250 icons on the page');

// components.disabled only skips registerComponents(); it does not change how
// an icon renders, so the render figures above apply to both configurations.
table(
    ['Setup', 'Fixed cost per request', 'Render 250 icons', 'Total'],
    [
        [
            '`<x-fas-camera />` (components enabled)',
            ms(['median' => $fixedCost]),
            ms(['median' => $renderCost['native component'][250]]),
            ms(['median' => $fixedCost + $renderCost['native component'][250]]),
        ],
        [
            '`<x-icon name="…" />` + components.disabled',
            '0.00 ms',
            ms(['median' => $renderCost['native <x-icon>'][250]]),
            ms(['median' => $renderCost['native <x-icon>'][250]]),
        ],
        [
            '`@svg()` + components.disabled (no defer support)',
            '0.00 ms',
            ms(['median' => $renderCost['@svg directive'][250]]),
            ms(['median' => $renderCost['@svg directive'][250]]),
        ],
        [
            '**`<x-icon:fas-camera />` (this package)**',
            '**0.00 ms**',
            '**' . ms(['median' => $renderCost['x-icon: static'][250]]) . '**',
            '**' . ms(['median' => $renderCost['x-icon: static'][250]]) . '**',
        ],
        [
            '**`<x-icon:dynamic …>` (this package)**',
            '**0.00 ms**',
            '**' . ms(['median' => $renderCost['x-icon:dynamic content'][250]]) . '**',
            '**' . ms(['median' => $renderCost['x-icon:dynamic content'][250]]) . '**',
        ],
    ],
);

echo "\n";
