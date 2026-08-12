<?php

namespace Vernok\Vite\Classes\Actions;

use Event;
use Vernok\Vite\Classes\Services\ViteAssetTokenRenderer;
use Vernok\Vite\Classes\Services\ViteAssetTokenQueue;
use Vernok\Vite\Classes\Support\ViteAssetScopeRegistry;
use Vernok\Vite\Classes\Support\ViteAssetToken;
use Vernok\Vite\Classes\Support\ViteScopeResolver;

/**
 * Registers vite: asset protocol handling for October's asset pipeline.
 */
class RegisterViteAssetTokenListener
{
    /**
     * @return void
     */
    public function execute(): void
    {
        Event::listen('system.assets.beforeAddAsset', function (string &$type, string &$path, array &$attributes) {
            if (! ViteAssetToken::contains($path)) {
                return;
            }

            $parsed = ViteAssetToken::parse($path);
            $scope = null;
            $pluginCode = null;

            if ($parsed !== null) {
                [$scope, $pluginCode] = app(ViteAssetScopeRegistry::class)->lookup($path);

                if ($scope === null && $pluginCode === null) {
                    $contextClass = ViteScopeResolver::shouldUseBacktraceFallback()
                        ? ViteScopeResolver::detectContextClassFromBacktrace()
                        : null;

                    [$scope, $pluginCode] = ViteScopeResolver::resolve(
                        $parsed['scope'],
                        $parsed['pluginCode'],
                        [$parsed['entry']],
                        null,
                        $contextClass,
                        ViteScopeResolver::shouldUseBacktraceFallback(),
                    );
                }
            }

            app(ViteAssetTokenQueue::class)->push($path, $type, $scope, $pluginCode);

            return false;
        });

        Event::listen('cms.assets.render', function ($type, ?string &$result): void {
            $result = app(ViteAssetTokenRenderer::class)->appendResolved(
                $result ?? '',
                is_string($type) ? $type : null,
            );
        });

        Event::listen('backend.layout.extendHead', function (): string {
            return app(ViteAssetTokenRenderer::class)->renderQueued();
        });
    }
}
