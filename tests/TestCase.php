<?php

declare(strict_types=1);

namespace ErickComp\LazyBladeIcons\Tests;

use BladeUI\Icons\BladeIconsServiceProvider;
use BladeUI\Icons\Factory as IconFactory;
use ErickComp\LazyBladeIcons\LazyBladeIconsServiceProvider;
use ErickComp\RawBladeComponents\RawBladeComponentsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * Extra options for the test icon set. Set it from a test before the first
     * render — the icon factory is only resolved when an icon is rendered.
     *
     * @var array<string, mixed>
     */
    protected array $iconSetOptions = [];

    protected function getPackageProviders($app)
    {
        return [
            RawBladeComponentsServiceProvider::class,
            BladeIconsServiceProvider::class,
            LazyBladeIconsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app->resolving(IconFactory::class, function (IconFactory $factory) {
            $factory->add('test', [
                'paths' => [__DIR__ . '/Fixtures/icons'],
                'prefix' => 'test',
                ...$this->iconSetOptions,
            ]);
        });
    }
}
