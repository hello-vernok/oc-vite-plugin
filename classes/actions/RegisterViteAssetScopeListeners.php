<?php

namespace Vernok\Vite\Classes\Actions;

use Backend\Classes\Controller as BackendController;
use Event;
use Vernok\Vite\Classes\Support\ViteAssetScopeRegistry;
use Vernok\Vite\Classes\Support\ViteAssetToken;
use Vernok\Vite\Classes\Support\ViteScopeResolver;

/**
 * Binds backend controller asset hooks so vite: scope is captured before HTML rendering.
 */
class RegisterViteAssetScopeListeners
{
    /**
     * @return void
     */
    public function execute(): void
    {
        Event::listen('backend.page.beforeDisplay', function (BackendController $controller): void {
            $controller->bindEvent('assets.beforeAddAsset', function (string $type, string &$path, array $attributes): void {
                if (! ViteAssetToken::contains($path)) {
                    return;
                }

                app(ViteAssetScopeRegistry::class)->remember(
                    $path,
                    ViteScopeResolver::detectContextClassFromBacktrace(),
                );
            });
        });
    }
}
