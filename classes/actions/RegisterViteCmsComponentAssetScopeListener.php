<?php

namespace Vernok\Vite\Classes\Actions;

use Cms\Classes\ComponentBase;
use Vernok\Vite\Classes\Support\ViteAssetScopeRegistry;
use Vernok\Vite\Classes\Support\ViteAssetToken;

/**
 * Registers vite: scope from CMS components after onRun() via component.run.
 */
class RegisterViteCmsComponentAssetScopeListener
{
    /**
     * @return void
     */
    public function execute(): void
    {
        ComponentBase::extend(function (ComponentBase $component): void {
            $component->bindEvent('component.run', function () use ($component): void {
                $paths = $component->getAssetPaths();

                foreach (['js', 'css'] as $type) {
                    foreach ($paths[$type] ?? [] as $path) {
                        if (! ViteAssetToken::contains($path)) {
                            continue;
                        }

                        app(ViteAssetScopeRegistry::class)->remember($path, $component::class);
                    }
                }
            });
        });
    }
}
