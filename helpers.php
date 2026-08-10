<?php

use Vernok\Vite\Classes\Assets\Vite;
use Vernok\Vite\Classes\Support\ViteScopeResolver;

if (! function_exists('vite')) {
    /**
     * Render HTML script and link tags for Vite entry files.
     *
     * @param  array|string  $entries
     * @param  string|null  $scope
     * @param  string|null  $pluginCode
     * @param  string|null  $type
     *
     * @return string
     */
    function vite(array|string $entries = [], ?string $scope = null, ?string $pluginCode = null, ?string $type = null): string
    {
        /** @var Vite $vite */
        $vite = app(Vite::class);
        $list = is_array($entries) ? $entries : [$entries];
        [$scope, $pluginCode] = ViteScopeResolver::resolve($scope, $pluginCode, $list);

        return $vite->renderTags($list, $scope, $pluginCode, $type);
    }
}

if (! function_exists('vite_styles')) {
    /**
     * @param  array|string  $entries
     * @param  string|null  $scope
     * @param  string|null  $pluginCode
     *
     * @return string
     */
    function vite_styles(array|string $entries = [], ?string $scope = null, ?string $pluginCode = null): string
    {
        return vite($entries, $scope, $pluginCode, 'css');
    }
}

if (! function_exists('vite_scripts')) {
    /**
     * @param  array|string  $entries
     * @param  string|null  $scope
     * @param  string|null  $pluginCode
     *
     * @return string
     */
    function vite_scripts(array|string $entries = [], ?string $scope = null, ?string $pluginCode = null): string
    {
        return vite($entries, $scope, $pluginCode, 'js');
    }
}

if (! function_exists('vite_assets')) {
    /**
     * @param  array|string  $entries
     * @param  string|null  $scope
     * @param  string|null  $pluginCode
     *
     * @return array{
     *     js: array<int, array{url:string, type?:string}>,
     *     css: array<int, array{url:string}>
     * }
     */
    function vite_assets(array|string $entries = [], ?string $scope = null, ?string $pluginCode = null): array
    {
        /** @var Vite $vite */
        $vite = app(Vite::class);
        $list = is_array($entries) ? $entries : [$entries];
        [$scope, $pluginCode] = ViteScopeResolver::resolve($scope, $pluginCode, $list);

        return $vite->resolveAssets($list, $scope, $pluginCode);
    }
}

if (! function_exists('vite_asset')) {
    /**
     * @param  string  $path
     * @param  string|null  $scope
     * @param  string|null  $pluginCode
     *
     * @return string
     */
    function vite_asset(string $path, ?string $scope = null, ?string $pluginCode = null): string
    {
        /** @var Vite $vite */
        $vite = app(Vite::class);
        [$scope, $pluginCode] = ViteScopeResolver::resolve($scope, $pluginCode, [], $path);

        return $vite->assetUrl($path, $scope, $pluginCode);
    }
}
