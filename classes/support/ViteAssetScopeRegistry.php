<?php

namespace Vernok\Vite\Classes\Support;

/**
 * Request-scoped registry of resolved vite: scope keyed by the registered asset path.
 */
class ViteAssetScopeRegistry
{
    /**
     * @var array<string, array{scope: string, pluginCode: ?string}>
     */
    protected array $resolved = [];

    /**
     * @var string|null
     */
    protected ?string $cachedBasePath = null;

    /**
     * @param  string  $path
     * @param  string|null  $contextClass
     *
     * @return void
     */
    public function remember(string $path, ?string $contextClass): void
    {
        $key = $this->normalizeKey($path);
        if (isset($this->resolved[$key])) {
            return;
        }

        $parsed = ViteAssetToken::parse($path);
        if ($parsed === null) {
            return;
        }

        [$scope, $pluginCode] = ViteScopeResolver::resolve(
            $parsed['scope'],
            $parsed['pluginCode'],
            [$parsed['entry']],
            null,
            $contextClass,
        );

        $this->resolved[$key] = [
            'scope'      => $scope,
            'pluginCode' => $pluginCode,
        ];
    }

    /**
     * @param  string  $path
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function lookup(string $path): array
    {
        $entry = $this->resolved[$this->normalizeKey($path)] ?? null;
        if ($entry === null) {
            return [null, null];
        }

        return [$entry['scope'], $entry['pluginCode']];
    }

    /**
     * @param  string  $path
     *
     * @return string
     */
    protected function normalizeKey(string $path): string
    {
        $basePath = $this->requestBasePath();
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        $path = ltrim($path, '/');

        $vitePosition = strpos($path, ViteAssetToken::IDENTIFIER);
        if ($vitePosition !== false && $vitePosition > 0) {
            $path = substr($path, $vitePosition);
        }

        return $path;
    }

    /**
     * @return string
     */
    protected function requestBasePath(): string
    {
        if ($this->cachedBasePath === null) {
            if (function_exists('app') && app()->bound('request')) {
                $this->cachedBasePath = rtrim((string) app('request')->getBasePath(), '/');
            } else {
                $this->cachedBasePath = '';
            }
        }

        return $this->cachedBasePath;
    }
}
