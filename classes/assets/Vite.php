<?php

namespace Vernok\Vite\Classes\Assets;

use Cms\Classes\Theme as CmsTheme;
use Config;
use Log;

/**
 * Vite 8 asset resolver for October CMS themes and plugins.
 *
 * Generates script and link tags for Vite-powered assets for both
 * OctoberCMS themes and plugins. Works in dev (Vite server) and prod (manifest) modes.
 *
 * Usage in Twig:
 *   {{ vite(['resources/js/app.ts']) }} {# theme scope #}
 *   {{ vite(['resources/js/admin.ts'], 'plugin', 'Acme.Blog') }}
 */
class Vite
{
    protected const MODE_DEV = 'dev';

    protected const MODE_BUILD = 'build';

    protected array $ownerCache = [];

    protected array $devJsonCache = [];

    protected array $fileExistsCache = [];

    protected array $configCache = [];

    protected ?bool $devServerEnabledCache = null;

    /**
     * @var array<string, true>
     */
    protected array $devViteClientUrlsEmitted = [];

    /**
     * Render the HTML tags for the provided entry files.
     *
     * @param  array  $entries  list of input files (as used by Vite), e.g. ['resources/js/app.ts']
     * @param  string  $scope  'theme' or 'plugin'
     * @param  string|null  $pluginCode  Vendor.Plugin (required when scope='plugin')
     * @param  string|null  $type  'css', 'js' or null (both)
     */
    public function renderTags(array $entries, string $scope = 'theme', ?string $pluginCode = null, ?string $type = null): string
    {
        $owner = $this->resolveOwner($scope, $pluginCode);
        if (! empty($owner['error'])) {
            return '<!-- Vite: '.htmlspecialchars((string) $owner['error'], ENT_QUOTES).' -->';
        }

        if ($owner['mode'] === self::MODE_DEV) {
            $html = $this->renderDevTagsWithHost($entries, (string) $owner['origin'], $type);
            if ($this->shouldDebugLogging()) {
                $this->logResolution('renderTags', $owner, [
                    'entries' => $entries,
                    'type'    => $type,
                    'result'  => $this->extractTagUrls($html),
                ]);
            }

            return $html;
        }

        $html = $this->renderFromManifest($entries, (array) $owner['manifest'], (string) $owner['publicBase'], (string) $owner['ownerKey'], $type);
        if ($this->shouldDebugLogging()) {
            $this->logResolution('renderTags', $owner, [
                'entries' => $entries,
                'type'    => $type,
                'result'  => $this->extractTagUrls($html),
            ]);
        }

        return $html;
    }

    /**
     * Resolve Vite assets into URLs so PHP components/controllers can call addJs/addCss.
     *
     * Structure example:
     * [
     *   'js' => [ ['url' => 'https://vite:5173/@vite/client', 'type' => 'module'], ... ],
     *   'css' => [ ['url' => '/themes/..../style.css'] ]
     * ]
     *
     * @return array{js: array<int, array{url:string,type?:string}>, css: array<int, array{url:string}>}
     *
     * In dev mode, CSS is loaded through JS entrypoint imports (Vite module graph); manifest CSS is not injected.
     */
    public function resolveAssets(array $entries, string $scope = 'theme', ?string $pluginCode = null): array
    {
        $owner = $this->resolveOwner($scope, $pluginCode);
        if (! empty($owner['error'])) {
            return ['js' => [], 'css' => []];
        }

        if ($owner['mode'] === self::MODE_DEV) {
            $assets = $this->resolveDevAssetsWithHost($entries, (string) $owner['origin']);

            $this->logResolution('resolveAssets', $owner, ['entries' => $entries, 'result' => $assets]);

            return $assets;
        }

        $assets = $this->resolveProdAssets($entries, (array) $owner['manifest'], (string) $owner['publicBase'], (string) $owner['ownerKey']);
        $this->logResolution('resolveAssets', $owner, ['entries' => $entries, 'result' => $assets]);

        return $assets;
    }

    /**
     * Resolve a single static asset (e.g. image, font) to a public URL.
     *
     * In dev mode (when enabled for the given scope), this will return the Vite dev server URL.
     * In production, it will read the manifest and return the fingerprinted file URL.
     *
     * Note: for production to work, the asset must be known to Vite (imported by TS/SCSS or
     * explicitly copied and listed in the manifest). If it cannot be found, an empty string is returned.
     *
     * @param  string  $path  The source path as used in Vite inputs/imports (e.g. 'resources/images/logo.png')
     * @param  string  $scope  'theme' or 'plugin'
     * @param  string|null  $pluginCode  Required when $scope === 'plugin' (e.g. 'Vendor.Plugin')
     * @return string Public URL suitable for use in <img src> etc., or '' if not resolvable
     */
    public function assetUrl(string $path, string $scope = 'theme', ?string $pluginCode = null): string
    {
        $path = ltrim($path, '/');
        $owner = $this->resolveOwner($scope, $pluginCode);
        if (! empty($owner['error'])) {
            return '';
        }

        if ($owner['mode'] === self::MODE_DEV) {
            $url = rtrim((string) $owner['origin'], '/').'/'.ltrim($path, '/');
            $this->debugLog('assetUrl-dev', [
                'owner'  => $owner['ownerKey'] ?? null,
                'path'   => $path,
                'origin' => $owner['origin'] ?? null,
                'url'    => $url,
            ]);
            $this->logResolution('assetUrl', $owner, ['path' => $path, 'result' => $url]);

            return $url;
        }

        $manifest = (array) $owner['manifest'];
        $item = $manifest[$path] ?? $manifest[ltrim($path, '/')] ?? null;
        if (is_array($item) && ! empty($item['file'])) {
            $url = (string) $owner['publicBase'].$item['file'];
            $this->debugLog('assetUrl-manifest-hit', [
                'owner' => $owner['ownerKey'] ?? null,
                'path'  => $path,
                'file'  => ! empty($item['file']) ? (string) $item['file'] : null,
                'url'   => $url,
            ]);
            $this->logResolution('assetUrl', $owner, ['path' => $path, 'result' => $url]);

            return $url;
        }

        $this->debugLog('missing-manifest-entry', [
            'owner'   => $owner['ownerKey'] ?? null,
            'entry'   => $path,
            'message' => $this->missingManifestEntryMessage((string) $owner['ownerKey'], $path),
        ]);

        return '';
    }

    protected function isDevServerEnabled(): bool
    {
        if ($this->devServerEnabledCache !== null) {
            return $this->devServerEnabledCache;
        }

        $enabled = $this->cfg('enabled', null);
        if ($enabled === null) {
            $this->devServerEnabledCache = true;

            return $this->devServerEnabledCache;
        }

        $this->devServerEnabledCache = (bool) $enabled;

        return $this->devServerEnabledCache;
    }

    /**
     * Render dev tags but with a specific dev host (from discovery file).
     */
    protected function renderDevTagsWithHost(array $entries, string $host, ?string $type = null): string
    {
        $host = rtrim($host, '/');
        $html = [];

        if ($type === null || $type === 'js') {
            $clientUrl = $host.'/@vite/client';
            if (! isset($this->devViteClientUrlsEmitted[$clientUrl])) {
                $this->devViteClientUrlsEmitted[$clientUrl] = true;
                $html[] = '<script type="module" src="'.htmlspecialchars($clientUrl, ENT_QUOTES).'"></script>';
            }
        }

        foreach ($entries as $entry) {
            $entryPath = ltrim((string) $entry, '/');
            $src = $host.'/'.$entryPath;

            if ($type === null) {
                $html[] = '<script type="module" src="'.htmlspecialchars($src, ENT_QUOTES).'"></script>';
            } elseif ($type === 'js') {
                if (str_ends_with($entry, '.ts') || str_ends_with($entry, '.js')) {
                    $html[] = '<script type="module" src="'.htmlspecialchars($src, ENT_QUOTES).'"></script>';
                }
            } elseif ($type === 'css') {
                if (str_ends_with($entry, '.scss') || str_ends_with($entry, '.css')) {
                    $html[] = '<link rel="stylesheet" href="'.htmlspecialchars($src, ENT_QUOTES).'">';
                }
            }
        }

        return implode("\n", $html);
    }

    /**
     * Read a dev_server value from config/vite.php (vernok.vite::vite.dev_server.*).
     */
    protected function cfg(string $key, mixed $default = null): mixed
    {
        $cacheKey = $key.'|'.md5(serialize($default));
        if (array_key_exists($cacheKey, $this->configCache)) {
            return $this->configCache[$cacheKey];
        }

        $value = Config::get('vernok.vite::vite.dev_server.'.$key, $default);

        $this->configCache[$cacheKey] = $value;

        return $value;
    }

    protected function renderFromManifest(array $entries, array $manifest, string $publicBase, string $ownerKey, ?string $type = null): string
    {
        $tags = [];
        $visitedKeys = [];
        $visitedUrls = ['js' => [], 'css' => []];

        foreach ($entries as $entry) {
            $collected = $this->collectManifestEntryAssets(
                (string) $entry,
                $manifest,
                $publicBase,
                $ownerKey,
                $visitedKeys,
                $visitedUrls,
            );

            if ($collected === null) {
                $tags[] = '<!-- Vite: '.htmlspecialchars($this->missingManifestEntryMessage($ownerKey, (string) $entry), ENT_QUOTES).' -->';

                continue;
            }

            if ($this->shouldDebugLogging()) {
                $this->debugLog('manifest-entry-tags-resolved', [
                    'owner' => $ownerKey,
                    'entry' => (string) $entry,
                    'js'    => array_column($collected['js'], 'path'),
                    'css'   => array_column($collected['css'], 'path'),
                ]);
            }

            if ($type === null || $type === 'js') {
                foreach ($collected['js'] as $js) {
                    $integrity = ! empty($js['integrity'])
                        ? ' integrity="'.htmlspecialchars((string) $js['integrity'], ENT_QUOTES).'"'
                        : '';
                    $tags[] = '<script type="module" src="'.htmlspecialchars($js['url'], ENT_QUOTES).'"'.$integrity.'></script>';
                }
            }

            if ($type === null || $type === 'css' || $type === 'js') {
                foreach ($collected['css'] as $css) {
                    $integrity = ! empty($css['integrity'])
                        ? ' integrity="'.htmlspecialchars((string) $css['integrity'], ENT_QUOTES).'"'
                        : '';
                    $tags[] = '<link rel="stylesheet" href="'.htmlspecialchars($css['url'], ENT_QUOTES).'"'.$integrity.'>';
                }
            }
        }

        return implode("\n", $tags);
    }

    /**
     * Resolve theme manifest at assets/.vite/manifest.json.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function getThemeManifestAndBase(): array
    {
        $dir = $this->getActiveThemeDir();
        $manifestPath = base_path('themes/'.$dir.'/assets/.vite/manifest.json');
        $manifest = $this->readManifest($manifestPath);
        if ($manifest) {
            return [$manifest, '/themes/'.$dir.'/assets/'];
        }

        return [[], ''];
    }

    protected function getActiveThemeDir(): string
    {
        $theme = CmsTheme::getActiveTheme();

        return $theme->getDirName();
    }

    protected function readManifest(string $path): array
    {
        static $cache = [];
        if (isset($cache[$path])) {
            return $cache[$path];
        }
        if (! is_file($path)) {
            $cache[$path] = [];

            return [];
        }
        $json = @file_get_contents($path) ?: '';
        $data = json_decode($json, true);
        $cache[$path] = is_array($data) ? $data : [];

        return $cache[$path];
    }

    /**
     * Resolve plugin manifest at assets/.vite/manifest.json.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function getPluginManifestAndBase(string $author, string $name): array
    {
        $author = strtolower($author);
        $name = strtolower($name);
        $manifestPath = plugins_path($author.'/'.$name.'/assets/.vite/manifest.json');
        $manifest = $this->readManifest($manifestPath);
        if ($manifest) {
            return [$manifest, '/plugins/'.$author.'/'.$name.'/assets/'];
        }

        return [[], ''];
    }

    /**
     * Resolve production assets by reading the appropriate manifest.
     *
     * @param  string  $scope  'theme' or 'plugin'
     * @param  string|null  $pluginCode  Required when scope='plugin'
     * @return array{js: array<int, array{url:string,type?:string}>, css: array<int, array{url:string}>}
     */
    protected function resolveProdAssets(array $entries, array $manifest, string $publicBase, string $ownerKey): array
    {
        $assets = ['js' => [], 'css' => []];
        $visitedKeys = [];
        $visitedUrls = ['js' => [], 'css' => []];

        foreach ($entries as $entry) {
            $collected = $this->collectManifestEntryAssets(
                (string) $entry,
                $manifest,
                $publicBase,
                $ownerKey,
                $visitedKeys,
                $visitedUrls,
            );

            if ($collected === null) {
                $this->debugLog('missing-manifest-entry', [
                    'owner'   => $ownerKey,
                    'entry'   => (string) $entry,
                    'message' => $this->missingManifestEntryMessage($ownerKey, (string) $entry),
                ]);

                continue;
            }

            if ($this->shouldDebugLogging()) {
                $this->debugLog('manifest-entry-assets-resolved', [
                    'owner' => $ownerKey,
                    'entry' => (string) $entry,
                    'js'    => array_column($collected['js'], 'path'),
                    'css'   => array_column($collected['css'], 'path'),
                ]);
            }

            foreach ($collected['js'] as $js) {
                $assets['js'][] = [
                    'url'  => $js['url'],
                    'type' => 'module',
                ];
            }

            foreach ($collected['css'] as $css) {
                $assets['css'][] = ['url' => $css['url']];
            }
        }

        return $assets;
    }

    /**
     * Collect JS/CSS for a manifest entry and its `imports` (shared chunks) in dependency order.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<string, true>  $visitedKeys
     * @param  array{js: array<string, true>, css: array<string, true>}  $visitedUrls
     * @return array{js: array<int, array{url: string, path: string, integrity?: string}>, css: array<int, array{url: string, path: string, integrity?: string}>}|null
     */
    protected function collectManifestEntryAssets(
        string $entry,
        array $manifest,
        string $publicBase,
        string $ownerKey,
        array &$visitedKeys,
        array &$visitedUrls,
    ): ?array {
        $manifestKey = $this->resolveManifestKey($entry, $manifest);
        if ($manifestKey === null) {
            return null;
        }

        if (isset($visitedKeys[$manifestKey])) {
            return ['js' => [], 'css' => []];
        }

        $visitedKeys[$manifestKey] = true;
        $item = $manifest[$manifestKey];
        if (! is_array($item)) {
            return null;
        }

        $collected = ['js' => [], 'css' => []];

        if (! empty($item['imports']) && is_array($item['imports'])) {
            foreach ($item['imports'] as $import) {
                $imported = $this->collectManifestEntryAssets(
                    (string) $import,
                    $manifest,
                    $publicBase,
                    $ownerKey,
                    $visitedKeys,
                    $visitedUrls,
                );
                if ($imported !== null) {
                    $collected['js'] = array_merge($collected['js'], $imported['js']);
                    $collected['css'] = array_merge($collected['css'], $imported['css']);
                }
            }
        }

        $integrity = ! empty($item['integrity']) ? (string) $item['integrity'] : null;

        if (! empty($item['file'])) {
            $path = (string) $item['file'];
            $url = $publicBase.$path;
            if (! isset($visitedUrls['js'][$url])) {
                $visitedUrls['js'][$url] = true;
                $js = ['url' => $url, 'path' => $path];
                if ($integrity !== null) {
                    $js['integrity'] = $integrity;
                }
                $collected['js'][] = $js;
            }
        }

        if (! empty($item['css']) && is_array($item['css'])) {
            foreach ($item['css'] as $css) {
                $path = (string) $css;
                $url = $publicBase.$path;
                if (! isset($visitedUrls['css'][$url])) {
                    $visitedUrls['css'][$url] = true;
                    $cssItem = ['url' => $url, 'path' => $path];
                    if ($integrity !== null) {
                        $cssItem['integrity'] = $integrity;
                    }
                    $collected['css'][] = $cssItem;
                }
            }
        }

        if (! empty($item['assets']) && is_array($item['assets'])) {
            foreach ($item['assets'] as $asset) {
                $path = (string) $asset;
                $url = $publicBase.$path;
                if (str_ends_with($path, '.css')) {
                    if (! isset($visitedUrls['css'][$url])) {
                        $visitedUrls['css'][$url] = true;
                        $collected['css'][] = ['url' => $url, 'path' => $path];
                    }

                    continue;
                }

                if ((str_ends_with($path, '.js') || str_ends_with($path, '.mjs')) && ! isset($visitedUrls['js'][$url])) {
                    $visitedUrls['js'][$url] = true;
                    $collected['js'][] = ['url' => $url, 'path' => $path];
                }
            }
        }

        return $collected;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function resolveManifestKey(string $entry, array $manifest): ?string
    {
        if (isset($manifest[$entry])) {
            return $entry;
        }

        $trimmed = ltrim($entry, '/');
        if (isset($manifest[$trimmed])) {
            return $trimmed;
        }

        return null;
    }

    /**
     * Build dev assets array for a specific dev host (from discovery).
     *
     * @return array{js: array<int, array{url:string,type?:string}>, css: array<int, array{url:string}>}
     */
    protected function resolveDevAssetsWithHost(array $entries, string $host): array
    {
        $host = rtrim($host, '/');

        $assets = ['js' => [], 'css' => []];
        $clientUrl = $host.'/@vite/client';
        if (! isset($this->devViteClientUrlsEmitted[$clientUrl])) {
            $this->devViteClientUrlsEmitted[$clientUrl] = true;
            $assets['js'][] = ['url' => $clientUrl, 'type' => 'module'];
        }

        foreach ($entries as $entry) {
            $entryPath = ltrim((string) $entry, '/');
            $assets['js'][] = [
                'url'  => $host.'/'.$entryPath,
                'type' => 'module',
            ];
        }

        return $assets;
    }

    protected function getThemeDevJsonFile(string $themeDir): string
    {
        return base_path('themes/'.strtolower($themeDir).'/.vite-dev.json');
    }

    protected function getPluginDevJsonFile(string $author, string $name): string
    {
        $author = strtolower($author);
        $name = strtolower($name);

        return plugins_path($author.'/'.$name.'/.vite-dev.json');
    }

    /**
     * Resolve the active owner (theme or plugin), dev/build mode, and production manifest metadata.
     *
     * @return array{
     *     scope?: string,
     *     ownerKey?: string,
     *     mode?: string,
     *     origin?: string|null,
     *     manifest?: array<string, mixed>,
     *     publicBase?: string,
     *     error?: string
     * }
     */
    protected function resolveOwner(string $scope, ?string $pluginCode): array
    {
        $scope = strtolower($scope) === 'plugin' ? 'plugin' : 'theme';
        $pluginCode = $pluginCode !== null ? trim($pluginCode) : null;
        $cacheKey = $scope.'|'.strtolower((string) $pluginCode);
        if (isset($this->ownerCache[$cacheKey])) {
            return $this->ownerCache[$cacheKey];
        }

        if ($scope === 'plugin' && ! $pluginCode) {
            $this->ownerCache[$cacheKey] = ['error' => 'plugin code missing'];

            return $this->ownerCache[$cacheKey];
        }

        if ($scope === 'plugin') {
            [$author, $name] = $this->splitPluginCode((string) $pluginCode);
            $author = strtolower($author);
            $name = strtolower($name);
            $ownerKey = 'plugin:'.$author.'.'.$name;
            $dev = $this->resolveOwnerDevState(
                $ownerKey,
                $this->getPluginDevJsonFile($author, $name),
            );
            [$manifest, $publicBase] = $dev['active']
                ? [[], '']
                : $this->getPluginManifestAndBase($author, $name);

            $resolved = [
                'scope'      => 'plugin',
                'ownerKey'   => $ownerKey,
                'mode'       => $dev['active'] ? self::MODE_DEV : self::MODE_BUILD,
                'origin'     => $dev['origin'],
                'manifest'   => $manifest,
                'publicBase' => $publicBase,
            ];
            $this->ownerCache[$cacheKey] = $resolved;

            return $resolved;
        }

        $theme = strtolower($this->getActiveThemeDir());
        $ownerKey = 'theme:'.$theme;
        $dev = $this->resolveOwnerDevState(
            $ownerKey,
            $this->getThemeDevJsonFile($theme),
        );
        [$manifest, $publicBase] = $dev['active']
            ? [[], '']
            : $this->getThemeManifestAndBase();

        $resolved = [
            'scope'      => 'theme',
            'ownerKey'   => $ownerKey,
            'mode'       => $dev['active'] ? self::MODE_DEV : self::MODE_BUILD,
            'origin'     => $dev['origin'],
            'manifest'   => $manifest,
            'publicBase' => $publicBase,
        ];
        $this->ownerCache[$cacheKey] = $resolved;

        return $resolved;
    }

    protected function resolveOwnerDevState(string $ownerKey, string $jsonPath): array
    {
        if (! $this->isDevServerEnabled()) {
            $this->debugLog('dev-server-disabled', ['owner' => $ownerKey]);

            return ['active' => false, 'origin' => null];
        }

        $this->debugLog('dev-json-check', [
            'owner'      => $ownerKey,
            'jsonPath'   => $jsonPath,
            'jsonExists' => $this->fileExists($jsonPath),
        ]);

        if (! $this->fileExists($jsonPath)) {
            return ['active' => false, 'origin' => null];
        }

        $devJson = $this->readDevJson($jsonPath);
        if ($devJson['valid']) {
            $this->debugLog('dev-json-valid', [
                'owner'    => $ownerKey,
                'jsonPath' => $jsonPath,
                'origin'   => $devJson['origin'],
            ]);

            return ['active' => true, 'origin' => (string) $devJson['origin']];
        }

        $this->debugLog('invalid-vite-dev-json', [
            'owner' => $ownerKey,
            'path'  => $jsonPath,
            'error' => $devJson['error'],
        ]);

        return ['active' => false, 'origin' => null];
    }

    protected function readDevJson(string $path): array
    {
        if (isset($this->devJsonCache[$path])) {
            return $this->devJsonCache[$path];
        }

        $raw = @file_get_contents($path);
        if (! is_string($raw) || trim($raw) === '') {
            $this->devJsonCache[$path] = ['valid' => false, 'origin' => null, 'error' => 'empty .vite-dev.json'];

            return $this->devJsonCache[$path];
        }

        $json = json_decode($raw, true);
        if (! is_array($json)) {
            $this->devJsonCache[$path] = ['valid' => false, 'origin' => null, 'error' => 'malformed .vite-dev.json'];

            return $this->devJsonCache[$path];
        }

        $origin = isset($json['origin']) ? trim((string) $json['origin']) : '';
        if (! $this->isValidOrigin($origin)) {
            $this->devJsonCache[$path] = ['valid' => false, 'origin' => null, 'error' => 'missing or invalid origin in .vite-dev.json'];

            return $this->devJsonCache[$path];
        }

        $this->devJsonCache[$path] = ['valid' => true, 'origin' => rtrim($origin, '/'), 'error' => null];

        return $this->devJsonCache[$path];
    }

    /**
     * Cache filesystem existence checks to avoid repeated stat calls.
     */
    protected function fileExists(string $path): bool
    {
        if (isset($this->fileExistsCache[$path])) {
            return $this->fileExistsCache[$path];
        }

        $this->fileExistsCache[$path] = is_file($path);

        return $this->fileExistsCache[$path];
    }

    protected function isValidOrigin(string $origin): bool
    {
        if ($origin === '') {
            return false;
        }
        $parts = parse_url($origin);
        if (! is_array($parts)) {
            return false;
        }

        return ! empty($parts['scheme']) && ! empty($parts['host']);
    }

    protected function missingManifestEntryMessage(string $ownerKey, string $entry): string
    {
        return 'entry not found in manifest for '.$ownerKey.': '.$entry;
    }

    protected function debugLog(string $message, array $context = []): void
    {
        if (! $this->shouldDebugLogging()) {
            return;
        }

        Log::debug('[Vernok.Vite] '.$message, $context);
    }

    protected function shouldDebugLogging(): bool
    {
        if (! config('app.debug')) {
            return false;
        }

        return (bool) $this->cfg('debug_logging', false);
    }

    protected function logResolution(string $action, array $owner, array $extra = []): void
    {
        $this->debugLog('resolved-owner-assets', array_merge([
            'action'     => $action,
            'owner'      => $owner['ownerKey'] ?? null,
            'mode'       => $owner['mode'] ?? null,
            'origin'     => $owner['mode'] === self::MODE_DEV ? ($owner['origin'] ?? null) : null,
            'publicBase' => $owner['mode'] === self::MODE_BUILD ? ($owner['publicBase'] ?? null) : null,
        ], $extra));
    }

    protected function extractTagUrls(string $html): array
    {
        $matches = [];
        preg_match_all('/(?:src|href)="([^"]+)"/', $html, $matches);

        return $matches[1] ?? [];
    }

    /**
     * @param  string  $pluginCode  Vendor.Plugin
     * @return array{0:string,1:string}
     */
    protected function splitPluginCode(string $pluginCode): array
    {
        $parts = explode('.', $pluginCode);
        $author = $parts[0] ?? '';
        $name = $parts[1] ?? '';

        return [$author, $name];
    }
}
