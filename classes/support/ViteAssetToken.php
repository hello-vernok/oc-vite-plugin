<?php

namespace Vernok\Vite\Classes\Support;

/**
 * Parses vite: asset protocol tokens used with addJs/addCss.
 */
class ViteAssetToken
{
    public const IDENTIFIER = 'vite:';

    /**
     * Whether the given asset path or URL contains a vite: token.
     *
     * @param  string  $value
     *
     * @return bool
     */
    public static function contains(string $value): bool
    {
        return str_contains($value, self::IDENTIFIER);
    }

    /**
     * Parse a vite: token from an asset path or rendered attribute value.
     *
     * Supported payloads:
     * - vite:resources/js/app.ts
     * - vite:theme:resources/js/app.ts
     * - vite:plugin:Vendor.Plugin:resources/js/app.ts
     *
     * @param  string  $value
     *
     * @return array{scope: ?string, pluginCode: ?string, entry: string}|null
     */
    public static function parse(string $value): ?array
    {
        $position = strpos($value, self::IDENTIFIER);
        if ($position === false) {
            return null;
        }

        $payload = substr($value, $position + strlen(self::IDENTIFIER));
        if ($payload === '') {
            return null;
        }

        if (str_starts_with($payload, 'plugin:')) {
            $rest = substr($payload, strlen('plugin:'));
            if (preg_match('#^([^:]+\\.[^:]+):(.+)$#', $rest, $matches) === 1) {
                return [
                    'scope'      => 'plugin',
                    'pluginCode' => $matches[1],
                    'entry'      => $matches[2],
                ];
            }
        }

        if (str_starts_with($payload, 'theme:')) {
            return [
                'scope'      => 'theme',
                'pluginCode' => null,
                'entry'      => substr($payload, strlen('theme:')),
            ];
        }

        return [
            'scope'      => null,
            'pluginCode' => null,
            'entry'      => $payload,
        ];
    }
}
