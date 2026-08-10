<?php

namespace Vernok\Vite\Classes\Support;

/**
 * Resolves Vite owner scope (theme vs plugin) for helpers and traits.
 *
 * Priority when scope is omitted or "auto":
 * 1. Explicit scope and/or plugin code from the caller
 * 2. Plugin namespace from $contextClass (components, controllers, widgets)
 * 3. Theme heuristics (resources/entrypoint.{ts,js}, resources/images|fonts/*)
 * 4. Plugin path in the call stack (Twig/partials)
 * 5. Default: theme
 */
class ViteScopeResolver
{
    private static ?string $ownPluginRootLower = null;

    /**
     * @param  array<int, string>  $entries
     *
     * @return array{0: string, 1: string|null}
     */
    public static function resolve(
        ?string $scope,
        ?string $pluginCode,
        array $entries = [],
        ?string $assetPath = null,
        ?string $contextClass = null,
    ): array {
        $normalizedScope = self::normalizeScope($scope);
        $pluginCode = self::normalizePluginCode($pluginCode);

        if ($normalizedScope === 'theme') {
            return ['theme', null];
        }

        if ($normalizedScope === 'plugin' || $pluginCode !== null) {
            if ($pluginCode === null) {
                $pluginCode = self::detectPluginCodeFromClass($contextClass)
                    ?? self::detectPluginCodeFromBacktrace();
            }

            return ['plugin', $pluginCode];
        }

        $pluginCodeFromClass = self::detectPluginCodeFromClass($contextClass);
        if ($pluginCodeFromClass !== null) {
            return ['plugin', $pluginCodeFromClass];
        }

        if ($assetPath !== null && self::isThemeStaticAssetPath($assetPath)) {
            return ['theme', null];
        }

        foreach ($entries as $entry) {
            if (self::isThemeRootEntrypoint((string) $entry)) {
                return ['theme', null];
            }
        }

        $pluginCodeFromBacktrace = self::detectPluginCodeFromBacktrace();
        if ($pluginCodeFromBacktrace !== null) {
            return ['plugin', $pluginCodeFromBacktrace];
        }

        return ['theme', null];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    public static function autodetectScope(?string $scope, ?string $pluginCode, ?string $contextClass = null): array
    {
        return self::resolve($scope, $pluginCode, [], null, $contextClass);
    }

    /**
     * @return string|null
     */
    public static function detectPluginCodeFromClass(?string $class): ?string
    {
        if ($class === null || $class === '') {
            return null;
        }

        $parts = explode('\\', $class);
        if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return $parts[0].'.'.$parts[1];
    }

    /**
     * @return string|null
     */
    public static function detectPluginCodeFromBacktrace(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);

        $foundPluginCode = null;
        $foundThemeInCallstack = false;

        foreach ($trace as $frame) {
            $file = isset($frame['file']) ? (string) $frame['file'] : '';
            if ($file === '') {
                continue;
            }

            $normalized = str_replace('\\', '/', $file);
            if (self::isOwnPluginFile($normalized)) {
                continue;
            }

            if (preg_match('#/themes/[^/]+/#i', $normalized) === 1) {
                $foundThemeInCallstack = true;

                continue;
            }

            if (preg_match('#/plugins/([^/]+)/([^/]+)/#i', $normalized, $matches) === 1) {
                $author = trim((string) ($matches[1] ?? ''));
                $name = trim((string) ($matches[2] ?? ''));
                if ($author !== '' && $name !== '') {
                    $foundPluginCode = $author.'.'.$name;
                }
            }
        }

        if ($foundThemeInCallstack) {
            return null;
        }

        return $foundPluginCode;
    }

    private static function normalizeScope(?string $scope): ?string
    {
        if ($scope === null || $scope === '') {
            return null;
        }

        $normalized = strtolower($scope);

        return $normalized === 'auto' ? null : $normalized;
    }

    private static function normalizePluginCode(?string $pluginCode): ?string
    {
        if ($pluginCode === null || $pluginCode === '') {
            return null;
        }

        return trim($pluginCode);
    }

    private static function isThemeStaticAssetPath(string $path): bool
    {
        return preg_match('#^resources/(images|fonts)/#i', ltrim($path, '/')) === 1;
    }

    private static function isThemeRootEntrypoint(string $entry): bool
    {
        return preg_match('#^resources/entrypoint\.(ts|js)$#i', ltrim($entry, '/')) === 1;
    }

    /**
     * @return string
     */
    private static function ownPluginRootLower(): string
    {
        if (self::$ownPluginRootLower === null) {
            $resolverFile = (string) (new \ReflectionClass(self::class))->getFileName();
            self::$ownPluginRootLower = strtolower(str_replace('\\', '/', dirname($resolverFile, 3)));
        }

        return self::$ownPluginRootLower;
    }

    private static function isOwnPluginFile(string $normalizedPath): bool
    {
        $root = self::ownPluginRootLower();
        $lower = strtolower(str_replace('\\', '/', $normalizedPath));

        return $lower === $root || str_starts_with($lower, $root.'/');
    }
}
