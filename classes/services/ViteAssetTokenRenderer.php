<?php

namespace Vernok\Vite\Classes\Services;

use Vernok\Vite\Classes\Assets\Vite;
use Vernok\Vite\Classes\Support\ViteAssetToken;
use Vernok\Vite\Classes\Support\ViteScopeResolver;

/**
 * Resolves vite: asset tokens into script and link tags.
 */
class ViteAssetTokenRenderer
{
    /**
     * @param  Vite  $vite
     * @param  ViteAssetTokenQueue  $queue
     */
    public function __construct(
        protected Vite $vite,
        protected ViteAssetTokenQueue $queue,
    ) {}

    /**
     * @param  string  $path
     * @param  string|null  $assetType
     *
     * @return string
     */
    public function renderPath(string $path, ?string $assetType = null): string
    {
        $parsed = ViteAssetToken::parse($path);
        if ($parsed === null) {
            return '';
        }

        [$scope, $pluginCode] = ViteScopeResolver::resolve(
            $parsed['scope'],
            $parsed['pluginCode'],
            [$parsed['entry']],
        );

        $renderType = in_array($assetType, ['js', 'css'], true) ? $assetType : null;

        return $this->vite->renderTags([$parsed['entry']], $scope, $pluginCode, $renderType);
    }

    /**
     * @param  string|null  $type
     *
     * @return string
     */
    public function renderQueued(?string $type = null): string
    {
        $tags = [];

        foreach ($this->queue->drain($type) as $item) {
            $rendered = $this->renderPath($item['path'], $item['type']);
            if ($rendered !== '') {
                $tags[] = $rendered;
            }
        }

        return implode("\n", $tags);
    }

    /**
     * @param  string  $html
     * @param  string|null  $type
     *
     * @return string
     */
    public function replaceRenderedAssets(string $html, ?string $type = null): string
    {
        if ($html === '') {
            return '';
        }

        $lines = explode("\n", $html);

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || ! ViteAssetToken::contains($trimmed)) {
                continue;
            }

            $lineType = str_starts_with($trimmed, '<link') ? 'css' : 'js';
            if ($type !== null && strtolower($type) !== $lineType) {
                continue;
            }

            if (preg_match('/(?:src|href)=["\']([^"\']+)["\']/', $trimmed, $matches) !== 1) {
                continue;
            }

            $lines[$index] = $this->renderPath($matches[1], $lineType);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  string  $result
     * @param  string|null  $type
     *
     * @return string
     */
    public function appendResolved(string $result, ?string $type = null): string
    {
        $normalizedType = $type !== null ? strtolower($type) : null;
        $result = $this->replaceRenderedAssets($result, $normalizedType);
        $queued = $this->renderQueued($normalizedType);

        if ($queued === '') {
            return $result;
        }

        return rtrim($result)."\n".$queued;
    }
}
