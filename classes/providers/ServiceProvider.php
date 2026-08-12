<?php

namespace Vernok\Vite\Classes\Providers;

use Event;
use Illuminate\Support\ServiceProvider as ServiceProviderBase;
use Twig\Environment as TwigEnvironment;
use Twig\TwigFunction;
use Vernok\Vite\Classes\Actions\RegisterViteCmsComponentAssetScopeListener;
use Vernok\Vite\Classes\Actions\RegisterViteAssetScopeListeners;
use Vernok\Vite\Classes\Actions\RegisterViteAssetTokenListener;
use Vernok\Vite\Classes\Assets\Vite;
use Vernok\Vite\Classes\Services\ViteAssetTokenQueue;
use Vernok\Vite\Classes\Services\ViteAssetTokenRenderer;
use Vernok\Vite\Classes\Support\ViteAssetScopeRegistry;

/**
 * Registers Vite helpers, config, and Twig functions.
 */
class ServiceProvider extends ServiceProviderBase
{
    /**
     * @var \WeakMap<TwigEnvironment, true>|null
     */
    protected static ?\WeakMap $twigFunctionsRegistered = null;

    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Vite::class);
        $this->app->singleton(ViteAssetTokenQueue::class);
        $this->app->singleton(ViteAssetTokenRenderer::class);
        $this->app->singleton(ViteAssetScopeRegistry::class);

        require_once plugins_path('vernok/vite/helpers.php');
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        (new RegisterViteCmsComponentAssetScopeListener)->execute();
        (new RegisterViteAssetScopeListeners)->execute();
        (new RegisterViteAssetTokenListener)->execute();

        Event::listen('cms.extendTwig', function (TwigEnvironment $twig): void {
            self::$twigFunctionsRegistered ??= new \WeakMap();

            if (isset(self::$twigFunctionsRegistered[$twig])) {
                return;
            }

            self::$twigFunctionsRegistered[$twig] = true;

            $twig->addFunction(new TwigFunction('vite', function ($entries = [], ?string $scope = null, ?string $plugin = null) {
                return vite((array) $entries, $scope, $plugin);
            }, ['is_safe' => ['html']]));

            $twig->addFunction(new TwigFunction('vite_styles', function ($entries = [], ?string $scope = null, ?string $plugin = null) {
                return vite_styles($entries, $scope, $plugin);
            }, ['is_safe' => ['html']]));

            $twig->addFunction(new TwigFunction('vite_scripts', function ($entries = [], ?string $scope = null, ?string $plugin = null) {
                return vite_scripts($entries, $scope, $plugin);
            }, ['is_safe' => ['html']]));

            $twig->addFunction(new TwigFunction('vite_asset', function (string $path, ?string $scope = null, ?string $plugin = null) {
                return vite_asset($path, $scope, $plugin);
            }));
        });
    }
}
