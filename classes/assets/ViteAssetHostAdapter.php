<?php

namespace Vernok\Vite\Classes\Assets;

use Vernok\Vite\Classes\Traits\HasViteAssets;

/**
 * Delegates addJs/addCss to a host object so HasViteAssets can enqueue from event listeners.
 */
final class ViteAssetHostAdapter
{
    use HasViteAssets;

    /**
     * @param  object  $host
     */
    public function __construct(
        private readonly object $host,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @return void
     */
    public function addJs(string $url, array $attributes = []): void
    {
        $this->host->addJs($url, $attributes);
    }

    /**
     * @return void
     */
    public function addCss(string $url): void
    {
        $this->host->addCss($url);
    }

    /**
     * @return class-string
     */
    protected function viteScopeContextClass(): string
    {
        return get_class($this->host);
    }

    /**
     * @param  array<int, string>  $entries
     *
     * @return void
     */
    public function enqueue(array $entries, ?string $scope = null, ?string $pluginCode = null): void
    {
        $this->addViteAssets($entries, $scope, $pluginCode);
    }
}
