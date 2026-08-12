<?php

namespace Vernok\Vite\Classes\Traits;

use Vernok\Vite\Classes\Assets\Vite;
use Vernok\Vite\Classes\Support\ViteScopeResolver;

/**
 * Enqueues Vite-built assets via addJs/addCss on components, controllers, or widgets.
 *
 * The consuming class must implement addJs(string $url, array $attributes = []): void
 * and addCss(string $url): void.
 */
trait HasViteAssets
{
    /**
     * Resolve and enqueue Vite entry assets for the current theme or plugin scope.
     *
     * @param  array<int, string>  $entries
     * @param  string|null  $scope
     * @param  string|null  $pluginCode
     *
     * @return void
     */
    protected function addViteAssets(array $entries, ?string $scope = null, ?string $pluginCode = null): void
    {
        [$scope, $pluginCode] = ViteScopeResolver::resolve(
            $scope,
            $pluginCode,
            $entries,
            null,
            $this->viteScopeContextClass(),
        );

        /** @var Vite $vite */
        $vite = app(Vite::class);
        $assets = $vite->resolveAssets($entries, $scope, $pluginCode);

        foreach ($assets['js'] as $js) {
            $attributes = [];
            if (! empty($js['type'])) {
                $attributes['type'] = (string) $js['type'];
            }

            $this->addJs($js['url'], $attributes);
        }

        foreach ($assets['css'] as $css) {
            $this->addCss($css['url']);
        }
    }

    /**
     * Class used to infer plugin scope when scope and plugin code are omitted.
     *
     * @return class-string
     */
    protected function viteScopeContextClass(): string
    {
        return static::class;
    }
}
