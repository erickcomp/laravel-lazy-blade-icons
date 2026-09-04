<?php

declare(strict_types=1);

namespace ErickComp\LazyBladeIcons;

use BladeUI\Icons\Factory as IconFactory;
use ErickComp\RawBladeComponents\RawComponent;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Factory as ViewFactory;

class LazyBladeIconsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lazy-blade-icons.php', 'lazy-blade-icons');

        $this->app->singleton(IconRenderer::class, function (Application $app) {
            $config = $app->make('config');

            return new IconRenderer(
                $app->make(IconFactory::class),
                $app->make(ViewFactory::class),
                $config->get('lazy-blade-icons.dynamic_keyword', 'dynamic'),
                $config->get('lazy-blade-icons.stack', 'bladeicons'),
                $config->get('lazy-blade-icons.defer', false),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/lazy-blade-icons.php' => $this->app->configPath('lazy-blade-icons.php'),
            ], 'lazy-blade-icons');
        }

        $this->registerIconTag();
    }

    protected function registerIconTag(): void
    {
        $prefix = (string) $this->app->make('config')->get('lazy-blade-icons.prefix', 'icon:');

        RawComponent::rawComponentStartingWith(
            tag: \str_starts_with($prefix, 'x-') ? $prefix : 'x-' . $prefix,
            openingCode: '<?php \ob_start(); ?>',
            closingCode: $this->renderCall(withContent: true),
            selfClosingCode: $this->renderCall(withContent: false),
        );
    }

    protected function renderCall(bool $withContent): string
    {
        $content = $withContent ? ', \ob_get_clean()' : '';

        return '<?php echo \app(\\' . IconRenderer::class . '::class)->renderFromTag('
            . '$__rawComponentTag, $__rawComponentTagPrefix, $__rawComponentAttributes' . $content
            . '); ?>';
    }
}
