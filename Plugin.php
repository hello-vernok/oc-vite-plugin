<?php

namespace Vernok\Vite;

use System\Classes\PluginBase;
use Vernok\Vite\Classes\Providers\ServiceProvider;

/**
 * Vite plugin registration.
 */
class Plugin extends PluginBase
{
    /**
     * @return array<string, mixed>
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'vernok.vite::lang.plugin.name',
            'description' => 'vernok.vite::lang.plugin.description',
            'author'      => 'Vernok',
            'icon'        => 'icon-bolt',
        ];
    }

    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->register(ServiceProvider::class);
    }
}
