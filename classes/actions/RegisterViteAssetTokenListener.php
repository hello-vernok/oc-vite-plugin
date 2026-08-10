<?php

namespace Vernok\Vite\Classes\Actions;

use Event;
use Vernok\Vite\Classes\Services\ViteAssetTokenRenderer;
use Vernok\Vite\Classes\Services\ViteAssetTokenQueue;
use Vernok\Vite\Classes\Support\ViteAssetToken;

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
        Event::listen('system.assets.beforeAddAsset', function (string &$type, string &$path, array &$attributes): bool|void {
            if (! ViteAssetToken::contains($path)) {
                return;
            }

            app(ViteAssetTokenQueue::class)->push($path, $type);

            return false;
        });

        Event::listen('cms.assets.render', function ($type, string &$result): void {
            $result = app(ViteAssetTokenRenderer::class)->appendResolved($result, is_string($type) ? $type : null);
        });

        Event::listen('backend.layout.extendHead', function (): string {
            return app(ViteAssetTokenRenderer::class)->renderQueued();
        });
    }
}
